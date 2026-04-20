<?php

namespace App\Jobs;

use App\Enums\FindingSeverity;
use App\Enums\PdfScanStatus;
use App\Models\PdfDocument;
use App\Models\PdfViolation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Throwable;

class ScanPdfJob implements ShouldQueue
{
    use Queueable;

    /**
     * Maximum seconds this job may run before being killed by the queue worker.
     */
    public int $timeout = 120;

    /**
     * Number of times the job may be attempted before it is marked as failed.
     */
    public int $tries = 2;

    /**
     * Seconds to wait between retry attempts.
     *
     * @var array<int, int>
     */
    public array $backoff = [30, 60];

    public function __construct(public PdfDocument $pdfDocument)
    {
        $this->onQueue('pdf');
    }

    /**
     * Execute the job.
     *
     * Posts the PDF URL to the configured scanner microservice, persists any
     * violations that are returned, and transitions the PdfDocument status to
     * Completed (or Failed on error).
     */
    public function handle(): void
    {
        if (! config('services.pdf_scanner.enabled')) {
            $this->pdfDocument->update(['status' => PdfScanStatus::Failed, 'error_message' => 'PDF scanner is not enabled.']);

            return;
        }

        $this->pdfDocument->update(['status' => PdfScanStatus::Scanning]);

        $url = rtrim((string) config('services.pdf_scanner.url'), '/');
        $timeout = (int) config('services.pdf_scanner.timeout', 120);

        $response = Http::timeout($timeout)
            ->post("{$url}/scan", ['url' => $this->pdfDocument->url]);

        if ($response->failed()) {
            $error = $response->json('detail') ?? $response->body();
            $this->pdfDocument->update([
                'status' => PdfScanStatus::Failed,
                'error_message' => substr((string) $error, 0, 500),
                'scanned_at' => now(),
            ]);

            return;
        }

        $violations = $response->json('violations') ?? [];

        foreach ($violations as $v) {
            PdfViolation::create([
                'pdf_document_id' => $this->pdfDocument->id,
                'rule_key' => $v['rule_key'],
                'severity' => FindingSeverity::from($v['severity'])->value,
                'wcag_criteria' => $v['wcag_criteria'] ?? null,
                'description' => $v['description'],
                'element_context' => $v['element_context'] ?? null,
                'page_number' => $v['page_number'] ?? null,
            ]);
        }

        $this->pdfDocument->update([
            'status' => PdfScanStatus::Completed,
            'violation_count' => count($violations),
            'scanned_at' => now(),
        ]);
    }

    /**
     * Handle final job failure after all retries are exhausted.
     */
    public function failed(Throwable $exception): void
    {
        $doc = PdfDocument::withoutGlobalScopes()->find($this->pdfDocument->id);

        if ($doc && ! $doc->status->isTerminal()) {
            $doc->update([
                'status' => PdfScanStatus::Failed,
                'error_message' => substr($exception->getMessage(), 0, 500),
                'scanned_at' => now(),
            ]);
        }
    }
}
