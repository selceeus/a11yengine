<?php

namespace App\Jobs;

use App\Domain\Issues\ProcessHtmlScan;
use App\Models\Scan;
use App\Models\ScanPage;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunScreenReaderAuditJob implements ShouldQueue
{
    use Batchable, Queueable;

    /**
     * Maximum seconds this job may run before being killed by the queue worker.
     * SR violations are pre-computed by the crawler — this job only persists.
     */
    public int $timeout = 60;

    /**
     * Number of times the job may be attempted before it is marked as failed.
     */
    public int $tries = 2;

    /**
     * Seconds to wait between retry attempts.
     *
     * @var array<int, int>
     */
    public array $backoff = [15];

    /**
     * @param  array<int, array{id: string, impact: string|null, description?: string, helpUrl?: string, tags?: list<string>, nodes: list<array{target: list<string>, html?: string, failureSummary?: string}>}>  $violations
     */
    public function __construct(
        public Scan $scan,
        public string $pageUrl,
        public array $violations,
    ) {}

    /**
     * Execute the job.
     *
     * Delegates to ProcessHtmlScan which persists SR Findings through
     * the same Finding + Issue pipeline used by the axe-core scan.
     *
     * Failures are treated as soft failures: any Throwable is caught and
     * logged as a warning so the job always succeeds from the queue's
     * perspective. This ensures SR unavailability never blocks the main scan.
     */
    public function handle(ProcessHtmlScan $processor): void
    {
        try {
            $processor->handle($this->scan, ['url' => $this->pageUrl, 'violations' => $this->violations], updateScanPage: false);
        } catch (Throwable $e) {
            Log::warning('Screen reader audit failed', [
                'url' => $this->pageUrl,
                'scan_id' => $this->scan->id,
                'error' => $e->getMessage(),
            ]);
        }

        ScanPage::withoutGlobalScopes()
            ->where('scan_id', $this->scan->id)
            ->where('url', $this->pageUrl)
            ->first()
            ?->update(['screen_reader_completed' => true]);
    }

    /**
     * Handle the final job failure after all retries are exhausted.
     */
    public function failed(Throwable $e): void
    {
        Log::warning('Screen reader audit job permanently failed', [
            'url' => $this->pageUrl,
            'scan_id' => $this->scan->id,
            'error' => $e->getMessage(),
        ]);

        ScanPage::withoutGlobalScopes()
            ->where('scan_id', $this->scan->id)
            ->where('url', $this->pageUrl)
            ->first()
            ?->update(['screen_reader_completed' => true]);
    }
}
