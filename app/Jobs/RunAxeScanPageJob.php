<?php

namespace App\Jobs;

use App\Domain\Issues\ProcessHtmlScan;
use App\Enums\ScanPageStatus;
use App\Events\ScanProgressUpdated;
use App\Models\Scan;
use App\Models\ScanPage;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class RunAxeScanPageJob implements ShouldQueue
{
    use Batchable, Queueable;

    /**
     * Maximum seconds this job may run before being killed by the queue worker.
     */
    public int $timeout = 120;

    /**
     * Number of times the job may be attempted before it is marked as failed.
     */
    public int $tries = 2;

    /**
     * Seconds to wait between retry attempts (progressive back-off).
     *
     * @var array<int, int>
     */
    public array $backoff = [10, 30];

    /**
     * @param  array<int, mixed>  $violations
     */
    public function __construct(
        public Scan $scan,
        public string $url,
        public array $violations,
    ) {}

    /**
     * Execute the job.
     *
     * Delegates to ProcessHtmlScan which persists Findings, deduplicates
     * Issues, records risk snapshots, and updates the ScanPage record
     * (via updateOrCreate) with the final violation count and Scanned status.
     */
    public function handle(ProcessHtmlScan $processHtmlScan): void
    {
        $processHtmlScan->handle($this->scan, [
            'url' => $this->url,
            'violations' => $this->violations,
        ]);

        $scannedCount = ScanPage::withoutGlobalScopes()
            ->where('scan_id', $this->scan->id)
            ->where('status', ScanPageStatus::Scanned)
            ->count();

        ScanProgressUpdated::dispatch(
            $this->scan->id,
            $this->scan->agency_id,
            $scannedCount,
            $this->scan->status->value,
        );
    }

    /**
     * Handle the final job failure after all retries are exhausted.
     *
     * Marks the pre-created ScanPage stub as Failed with axe_completed=true
     * so the batch finalizer can correctly aggregate results. Only updates
     * pages that are still Pending (guards against a partial success where
     * record() was already called before a later step threw).
     */
    public function failed(Throwable $e): void
    {
        ScanPage::withoutGlobalScopes()
            ->where('scan_id', $this->scan->id)
            ->where('url', $this->url)
            ->where('status', ScanPageStatus::Pending)
            ->update(['status' => ScanPageStatus::Failed, 'axe_completed' => true]);
    }
}
