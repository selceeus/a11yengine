<?php

namespace App\Jobs;

use App\Domain\Issues\ProcessHtmlScan;
use App\Domain\Scans\Scan as ScanDomain;
use App\Enums\ScanStatus;
use App\Exceptions\ScanProcessException;
use App\Models\Scan;
use App\Services\CrawlerRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class RunScanJob implements ShouldQueue
{
    use Queueable;

    /**
     * Maximum seconds this job may run before being killed by the queue worker.
     * Set high enough to allow large sites to complete crawling.
     */
    public int $timeout = 600;

    /**
     * Number of times the job may be attempted before it is marked as failed.
     */
    public int $tries = 3;

    /**
     * Seconds to wait between retry attempts (progressive back-off).
     *
     * @var array<int, int>
     */
    public array $backoff = [10, 30];

    public function __construct(public Scan $scan) {}

    /**
     * Execute the job.
     *
     * Orchestrates the full scan lifecycle:
     *  1. Transition scan to Running.
     *  2. Invoke the Node.js axe-core crawler for the property's base URL.
     *  3. Pass each page result through ProcessHtmlScan, which persists
     *     Findings, deduplicates Issues, and records risk snapshots.
     *  4. Transition scan to Completed with aggregated page/violation counts.
     *
     * If the crawler process fails the scan is immediately transitioned to
     * Failed, and the exception is re-thrown so the queue worker can apply
     * the configured retry / backoff policy.
     */
    public function handle(
        ScanDomain $scanDomain,
        ProcessHtmlScan $processHtmlScan,
        CrawlerRunner $crawlerRunner,
    ): void {
        $scanDomain->start($this->scan);

        try {
            $pageResults = $crawlerRunner->run(
                $this->scan->property->base_url,
                config('crawler.timeout', 300),
            );
        } catch (ScanProcessException $e) {
            $scanDomain->fail($this->scan);
            throw $e;
        }

        $maxLighthousePages = config('lighthouse.max_pages', 10);

        if ($maxLighthousePages > 0) {
            foreach (array_slice($pageResults, 0, $maxLighthousePages) as $pageResult) {
                RunLighthouseScanJob::dispatch($this->scan, $pageResult['url']);
            }
        }

        $pagesScanned = 0;
        $totalViolations = 0;

        foreach ($pageResults as $pageResult) {
            $scanPage = $processHtmlScan->handle($this->scan, $pageResult);

            $pagesScanned++;
            $totalViolations += $scanPage->violations_count;
        }

        $scanDomain->complete(
            $this->scan,
            pagesScanned: $pagesScanned,
            totalViolations: $totalViolations,
        );
    }

    /**
     * Handle the final job failure (called after all retries are exhausted).
     *
     * Ensures the scan is always left in a terminal Failed state regardless
     * of which exception surface caused the job to give up. Uses
     * withoutGlobalScopes because queue workers run outside of any
     * authenticated request context.
     */
    public function failed(Throwable $exception): void
    {
        $scan = Scan::withoutGlobalScopes()->find($this->scan->id);

        if ($scan && $scan->status !== ScanStatus::Failed) {
            (new ScanDomain)->fail($scan);
        }
    }
}
