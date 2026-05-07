<?php

namespace App\Jobs;

use App\Domain\Scans\Scan as ScanDomain;
use App\Domain\Scans\ScanConfig;
use App\Enums\ScanStatus;
use App\Events\ScanFailed;
use App\Models\PdfDocument;
use App\Models\Scan;
use App\Services\CrawlerRunner;
use App\Services\ScanPageDispatcher;
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

    public function __construct(public Scan $scan)
    {
        // Prevent a race where this job starts before the dispatching transaction commits
        // and Scan::find() returns null inside handle().
        $this->afterCommit();
    }

    /**
     * Execute the job.
     *
     * Orchestrates the scan lifecycle:
     *  1. Transition scan to Running.
     *  2. Invoke the Node.js axe-core crawler for the property's base URL.
     *  3. Delegate to ScanPageDispatcher which creates per-page stubs and
     *     dispatches a Bus::batch() of RunAxeScanPageJob + RunLighthouseScanJob
     *     for each discovered page.
     *  4. The batch then() callback transitions the scan to Completed once
     *     all per-page jobs finish.
     *
     * If the crawler process fails the scan is immediately transitioned to
     * Failed, and the exception is re-thrown so the queue worker can apply
     * the configured retry / backoff policy.
     */
    public function handle(
        ScanDomain $scanDomain,
        ScanPageDispatcher $dispatcher,
        CrawlerRunner $crawlerRunner,
    ): void {
        $fresh = Scan::withoutGlobalScopes()->find($this->scan->id);
        if (! $fresh || $fresh->status !== ScanStatus::Running) {
            $scanDomain->start($this->scan);
        }

        $scanConfig = $this->scan->scan_config
            ? ScanConfig::fromArray($this->scan->scan_config)
            : new ScanConfig;

        $crawlUrl = $this->scan->target_url ?? $this->scan->property->base_url;

        if ($this->scan->target_url !== null) {
            $scanConfig = new ScanConfig(maxPages: 1, maxDepth: 1, wcagVersion: $scanConfig->wcagVersion);
        }

        if ($this->scan->scan_journey_id !== null) {
            $steps = $this->scan->scanJourney->steps()->orderBy('position')->pluck('url')->all();
            $scanConfig = new ScanConfig(orderedUrls: $steps, wcagVersion: $scanConfig->wcagVersion);
        }

        try {
            $pageResults = $crawlerRunner->run(
                $crawlUrl,
                config('crawler.timeout', 300),
                $scanConfig,
            );
        } catch (Throwable $e) {
            $scanDomain->fail($this->scan, $e->getMessage());
            throw $e;
        }

        $dispatcher->dispatch($this->scan, $pageResults['pages']);

        foreach ($pageResults['pdfs'] ?? [] as $pdfUrl) {
            $doc = PdfDocument::create([
                'scan_id' => $this->scan->id,
                'property_id' => $this->scan->property_id,
                'agency_id' => $this->scan->agency_id,
                'url' => $pdfUrl,
                'filename' => basename(parse_url($pdfUrl, PHP_URL_PATH) ?? $pdfUrl) ?: null,
            ]);

            ScanPdfJob::dispatch($doc);
        }
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
            (new ScanDomain)->fail($scan, $exception->getMessage());
        }

        if ($scan) {
            ScanFailed::dispatch($scan->fresh());
        }
    }
}
