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

    /**
     * Maps PDF/UA-1 (Matterhorn Protocol) clause numbers to WCAG success criteria
     * and a default severity. Clauses not listed fall back to wcag_criteria = null
     * and severity = minor.
     *
     * @var array<string, array{wcag_criteria: string|null, severity: string, description: string}>
     */
    private const CLAUSE_META = [
        '6.1' => ['wcag_criteria' => '4.1.1', 'severity' => 'serious', 'description' => 'PDF syntax is not valid.'],
        '6.2' => ['wcag_criteria' => '1.3.1', 'severity' => 'critical', 'description' => 'Document is not tagged.'],
        '7.1' => ['wcag_criteria' => '2.4.2', 'severity' => 'serious', 'description' => 'Document title is missing or not displayed.'],
        '7.2' => ['wcag_criteria' => '3.1.1', 'severity' => 'serious', 'description' => 'Document natural language is not specified.'],
        '7.3' => ['wcag_criteria' => '1.3.2', 'severity' => 'serious', 'description' => 'Tab order is inconsistent with structure order.'],
        '7.4' => ['wcag_criteria' => '1.3.1', 'severity' => 'moderate', 'description' => 'Artifact is tagged as content or content is tagged as artifact.'],
        '7.5' => ['wcag_criteria' => '1.3.1', 'severity' => 'serious', 'description' => 'Content is not in the Marked Content sequence.'],
        '7.6' => ['wcag_criteria' => '1.3.1', 'severity' => 'serious', 'description' => 'Tagged element is not in the structure tree.'],
        '7.7' => ['wcag_criteria' => '1.3.1', 'severity' => 'moderate', 'description' => 'Structure element type is not valid.'],
        '7.8' => ['wcag_criteria' => '1.3.1', 'severity' => 'moderate', 'description' => 'Heading levels are not consistent.'],
        '7.9' => ['wcag_criteria' => '1.3.1', 'severity' => 'moderate', 'description' => 'Punctuation in structure element hinders text extraction.'],
        '7.10' => ['wcag_criteria' => '1.3.1', 'severity' => 'moderate', 'description' => 'Paragraph structure is not explicit.'],
        '7.11' => ['wcag_criteria' => '1.3.1', 'severity' => 'serious', 'description' => 'List structure is incorrect.'],
        '7.12' => ['wcag_criteria' => '1.3.1', 'severity' => 'serious', 'description' => 'Table structure is incorrect.'],
        '7.13' => ['wcag_criteria' => '1.3.1', 'severity' => 'serious', 'description' => 'Table header cell has no associated data cells.'],
        '7.14' => ['wcag_criteria' => '1.3.1', 'severity' => 'moderate', 'description' => 'Non-standard structure type is not mapped to a standard type.'],
        '7.15' => ['wcag_criteria' => '1.4.5', 'severity' => 'serious', 'description' => 'A real content image is not tagged as a Figure.'],
        '7.16' => ['wcag_criteria' => '1.3.1', 'severity' => 'moderate', 'description' => 'Optional content has no accessible name.'],
        '7.17' => ['wcag_criteria' => '1.3.1', 'severity' => 'moderate', 'description' => 'Optional content state affects page content.'],
        '7.18.1' => ['wcag_criteria' => '2.4.2', 'severity' => 'moderate', 'description' => 'Annotation alternative description is missing.'],
        '7.18.2' => ['wcag_criteria' => '1.3.1', 'severity' => 'moderate', 'description' => 'Annotation is not tagged.'],
        '7.18.3' => ['wcag_criteria' => '1.3.1', 'severity' => 'moderate', 'description' => 'Annotation is not present in the structure tree.'],
        '7.18.4' => ['wcag_criteria' => '1.3.1', 'severity' => 'critical', 'description' => 'Annotation appearance is missing.'],
        '7.18.5' => ['wcag_criteria' => '4.1.2', 'severity' => 'serious', 'description' => 'Widget annotation has no label.'],
        '7.18.6' => ['wcag_criteria' => '2.1.1', 'severity' => 'serious', 'description' => 'Keyboard trap in annotation.'],
        '7.18.7' => ['wcag_criteria' => '1.3.1', 'severity' => 'moderate', 'description' => 'Link annotation is not associated with a structure element.'],
        '7.19' => ['wcag_criteria' => '1.3.1', 'severity' => 'serious', 'description' => 'Logical structure is not correct.'],
        '7.20' => ['wcag_criteria' => '1.3.1', 'severity' => 'moderate', 'description' => 'Inline element contains block-level element.'],
        '7.21' => ['wcag_criteria' => '3.1.2', 'severity' => 'moderate', 'description' => 'Natural language of content cannot be determined.'],
        '9' => ['wcag_criteria' => '1.3.1', 'severity' => 'serious', 'description' => 'Content is not marked with a structure element.'],
        '14' => ['wcag_criteria' => '1.1.1', 'severity' => 'serious', 'description' => 'Character cannot be mapped to Unicode.'],
        '28' => ['wcag_criteria' => '1.1.1', 'severity' => 'serious', 'description' => 'Figure is missing alternative text.'],
        '29' => ['wcag_criteria' => '4.1.2', 'severity' => 'serious', 'description' => 'Form field is missing an accessible label.'],
        '30' => ['wcag_criteria' => '2.4.4', 'severity' => 'moderate', 'description' => 'Link has no description.'],
    ];

    public function __construct(public PdfDocument $pdfDocument)
    {
        $this->onQueue('pdf');
    }

    /**
     * Execute the job.
     *
     * Posts the PDF URL to the veraPDF REST microservice for PDF/UA-1 validation,
     * translates the response into PdfViolation records, and transitions the
     * PdfDocument status to Completed (or Failed on error).
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
            ->accept('application/json')
            ->asForm()
            ->post("{$url}/api/validate/url/ua1", ['url' => $this->pdfDocument->url]);

        if ($response->failed()) {
            $error = $response->json('message') ?? $response->body();
            $this->pdfDocument->update([
                'status' => PdfScanStatus::Failed,
                'error_message' => substr((string) $error, 0, 500),
                'scanned_at' => now(),
            ]);

            return;
        }

        $violations = $this->translateVeraPdfResponse($response->json() ?? []);

        foreach ($violations as $v) {
            PdfViolation::create([
                'pdf_document_id' => $this->pdfDocument->id,
                'rule_key' => $v['rule_key'],
                'severity' => FindingSeverity::from($v['severity'])->value,
                'wcag_criteria' => $v['wcag_criteria'],
                'description' => $v['description'],
                'element_context' => $v['element_context'],
                'page_number' => $v['page_number'],
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

    /**
     * Translate a veraPDF REST JSON report into a flat array of violation arrays
     * ready to be persisted as PdfViolation records.
     *
     * veraPDF groups failures by rule (ruleSummary) and then lists individual
     * occurrences (checks). We emit one violation per check so that page-level
     * detail is preserved.
     *
     * @param  array<string, mixed>  $body
     * @return array<int, array{rule_key: string, severity: string, wcag_criteria: string|null, description: string, element_context: string|null, page_number: int|null}>
     */
    private function translateVeraPdfResponse(array $body): array
    {
        $ruleSummaries = $body['report']['jobs'][0]['validationReport']['details']['ruleSummaries'] ?? [];

        $violations = [];

        foreach ($ruleSummaries as $rule) {
            if (($rule['status'] ?? '') !== 'failed') {
                continue;
            }

            $clause = (string) ($rule['clause'] ?? '');
            $testNumber = (int) ($rule['testNumber'] ?? 0);
            $ruleKey = 'ua1/'.$clause.'-'.$testNumber;

            $meta = self::CLAUSE_META[$clause] ?? [
                'wcag_criteria' => null,
                'severity' => 'minor',
                'description' => $rule['description'] ?? 'PDF/UA-1 violation.',
            ];

            $description = self::CLAUSE_META[$clause]['description'] ?? ($rule['description'] ?? 'PDF/UA-1 violation.');

            foreach (($rule['checks'] ?? []) as $check) {
                if (($check['status'] ?? '') !== 'failed') {
                    continue;
                }

                $context = (string) ($check['context'] ?? '');
                $pageNumber = $this->extractPageNumber($context);

                $violations[] = [
                    'rule_key' => $ruleKey,
                    'severity' => $meta['severity'],
                    'wcag_criteria' => $meta['wcag_criteria'],
                    'description' => $description,
                    'element_context' => $context !== '' ? $context : null,
                    'page_number' => $pageNumber,
                ];
            }
        }

        return $violations;
    }

    /**
     * Extract a 1-based page number from a veraPDF context string.
     *
     * veraPDF context strings use 0-based page indexes, e.g.:
     *   root/document[0]/pages[2]/...
     *
     * Returns null when no page index is found.
     */
    private function extractPageNumber(string $context): ?int
    {
        if (preg_match('/pages\[(\d+)\]/', $context, $matches)) {
            return (int) $matches[1] + 1;
        }

        return null;
    }
}
