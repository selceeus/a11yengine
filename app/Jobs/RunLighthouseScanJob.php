<?php

namespace App\Jobs;

use App\Exceptions\ScanProcessException;
use App\Models\LighthouseResult;
use App\Models\Scan;
use App\Services\LighthouseRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RunLighthouseScanJob implements ShouldQueue
{
    use Queueable;

    /**
     * Maximum seconds this job may run before being killed by the queue worker.
     * Per-page Lighthouse audits are slower than axe crawls — budgeted generously.
     */
    public int $timeout = 180;

    /**
     * Number of times the job may be attempted before it is marked as failed.
     */
    public int $tries = 2;

    /**
     * Seconds to wait between retry attempts.
     *
     * @var array<int, int>
     */
    public array $backoff = [30];

    public function __construct(public Scan $scan, public string $pageUrl) {}

    /**
     * Execute the job.
     *
     * Runs the Lighthouse CLI against a single page URL and persists the
     * extracted metrics as a LighthouseResult. Failures are treated as soft
     * failures: a ScanProcessException is caught and logged as a warning so
     * the job succeeds from the queue's perspective and never pollutes the
     * failed_jobs table. This ensures Lighthouse unavailability (e.g. in
     * environments without Chromium) never impacts the main axe scan pipeline.
     */
    public function handle(LighthouseRunner $runner): void
    {
        try {
            $metrics = $runner->run($this->pageUrl, config('lighthouse.timeout', 120));

            LighthouseResult::create([
                'agency_id' => $this->scan->agency_id,
                'scan_id' => $this->scan->id,
                ...$metrics,
            ]);
        } catch (ScanProcessException $e) {
            Log::warning('Lighthouse scan failed', [
                'url' => $this->pageUrl,
                'scan_id' => $this->scan->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
