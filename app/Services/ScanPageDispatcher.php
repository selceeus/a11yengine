<?php

namespace App\Services;

use App\Domain\Risk\RecordOrganizationRiskSnapshot;
use App\Domain\Risk\RecordPropertyRiskSnapshot;
use App\Domain\Scans\Scan as ScanDomain;
use App\Enums\ScanPageStatus;
use App\Enums\ScanStatus;
use App\Jobs\RunAxeScanPageJob;
use App\Jobs\RunLighthouseScanJob;
use App\Jobs\RunScreenReaderAuditJob;
use App\Models\Scan as ScanModel;
use App\Models\ScanPage as ScanPageModel;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;

class ScanPageDispatcher
{
    /**
     * Create per-page ScanPage stubs then dispatch a batch containing one
     * RunAxeScanPageJob (and optionally RunLighthouseScanJob) per page.
     *
     * The batch uses allowFailures() so individual page failures do not abort
     * the rest of the scan. The then() callback fires when all jobs complete
     * (success or failure) and transitions the scan to Completed.
     *
     * @param  array<int, array{url: string, violations: array<int, mixed>}>  $pageResults
     */
    public function dispatch(ScanModel $scan, array $pageResults): void
    {
        $lighthouseEnabled = config('lighthouse.enabled', true);
        $screenReaderEnabled = config('screen_reader.enabled', true);
        $jobs = [];
        $scan->update(['pages_discovered' => count($pageResults)]);

        foreach ($pageResults as $pageResult) {
            if ($pageResult['error'] ?? false) {
                ScanPageModel::withoutGlobalScopes()->updateOrCreate(
                    ['agency_id' => $scan->agency_id, 'scan_id' => $scan->id, 'url' => $pageResult['url']],
                    [
                        'violations_count' => 0,
                        'status' => ScanPageStatus::Failed,
                        'axe_completed' => true,
                        'lighthouse_completed' => $lighthouseEnabled ? true : null,
                        'screen_reader_completed' => $screenReaderEnabled ? true : null,
                    ],
                );

                continue;
            }

            ScanPageModel::withoutGlobalScopes()->updateOrCreate(
                ['agency_id' => $scan->agency_id, 'scan_id' => $scan->id, 'url' => $pageResult['url']],
                [
                    'violations_count' => 0,
                    'status' => ScanPageStatus::Pending,
                    'axe_completed' => false,
                    'lighthouse_completed' => $lighthouseEnabled ? false : null,
                    'screen_reader_completed' => $screenReaderEnabled ? false : null,
                ],
            );

            $jobs[] = new RunAxeScanPageJob($scan, $pageResult['url'], $pageResult['violations']);

            if ($lighthouseEnabled) {
                $jobs[] = new RunLighthouseScanJob($scan, $pageResult['url'], 'mobile');
                $jobs[] = new RunLighthouseScanJob($scan, $pageResult['url'], 'desktop');
            }

            if ($screenReaderEnabled) {
                $jobs[] = new RunScreenReaderAuditJob($scan, $pageResult['url'], $pageResult['screenReaderViolations'] ?? []);
            }
        }

        if (empty($jobs)) {
            (new ScanDomain)->complete($scan, 0, 0);

            return;
        }

        $scanId = $scan->id;

        Bus::batch($jobs)
            ->name("scan:{$scan->id}")
            ->allowFailures()
            ->finally(function (Batch $batch) use ($scanId): void {
                $scan = ScanModel::withoutGlobalScopes()->find($scanId);

                if ($scan === null || $scan->status === ScanStatus::Failed || $scan->status === ScanStatus::Completed) {
                    return;
                }

                $pages = ScanPageModel::withoutGlobalScopes()
                    ->where('scan_id', $scanId)
                    ->where('status', ScanPageStatus::Scanned)
                    ->get();

                (new ScanDomain)->complete($scan, $pages->count(), $pages->sum('violations_count'));

                app(CalculateScanMetrics::class)->handle($scan->fresh() ?? $scan);
                app(RecordPropertyRiskSnapshot::class)->handle($scan->property_id);
                app(RecordOrganizationRiskSnapshot::class)->handle($scan->organization_id);

                event(new \App\Events\ScanCompleted($scan->fresh() ?? $scan));
            })
            ->dispatch();
    }
}
