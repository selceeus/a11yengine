<?php

use App\Enums\FindingSeverity;
use App\Enums\PdfScanStatus;
use App\Jobs\ScanPdfJob;
use App\Models\Agency;
use App\Models\Organization;
use App\Models\PdfDocument;
use App\Models\PdfViolation;
use App\Models\Property;
use App\Models\Scan;
use App\Models\User;
use Illuminate\Support\Facades\Http;

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * Build a minimal veraPDF REST JSON response body.
 *
 * @param  array<int, array{clause: string, testNumber: int, description: string, checks: list<array{status: string, context: string}>}>  $failedRules
 */
function veraResponse(array $failedRules = [], ?bool $isCompliant = null): array
{
    $isCompliant ??= empty($failedRules);

    return [
        'report' => [
            'jobs' => [[
                'validationReport' => [
                    'isCompliant' => $isCompliant,
                    'details' => [
                        'ruleSummaries' => array_map(
                            fn (array $r) => [
                                'clause' => $r['clause'],
                                'testNumber' => $r['testNumber'],
                                'status' => 'failed',
                                'description' => $r['description'],
                                'checks' => $r['checks'],
                            ],
                            $failedRules
                        ),
                    ],
                ],
            ]],
        ],
    ];
}

// ─── Setup ───────────────────────────────────────────────────────────────────

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);
    $this->property = Property::factory()
        ->for($this->agency)
        ->for($this->organization)
        ->create();

    $user = User::factory()->create(['agency_id' => $this->agency->id]);
    $this->actingAs($user);

    $this->scan = Scan::factory()
        ->for($this->agency)
        ->for($this->organization)
        ->for($this->property)
        ->create();

    $this->doc = PdfDocument::create([
        'scan_id' => $this->scan->id,
        'property_id' => $this->property->id,
        'agency_id' => $this->agency->id,
        'url' => 'https://example.com/report.pdf',
        'status' => PdfScanStatus::Pending,
    ]);

    config(['services.pdf_scanner.enabled' => true, 'services.pdf_scanner.url' => 'http://pdf-scanner:8080']);
});

// ─── Feature flag guard ───────────────────────────────────────────────────────

it('marks the document as failed immediately when the pdf scanner is disabled', function (): void {
    config(['services.pdf_scanner.enabled' => false]);
    Http::fake();

    (new ScanPdfJob($this->doc))->handle();

    expect($this->doc->fresh()->status)->toBe(PdfScanStatus::Failed);
    Http::assertNothingSent();
});

// ─── Happy path ───────────────────────────────────────────────────────────────

it('creates a PdfViolation record for each check occurrence returned by the scanner', function (): void {
    Http::fake([
        '*/api/validate/*' => Http::response(veraResponse([
            [
                'clause' => '6.2',
                'testNumber' => 1,
                'description' => 'Document is not tagged.',
                'checks' => [
                    ['status' => 'failed', 'context' => 'root/document[0]'],
                ],
            ],
            [
                'clause' => '7.1',
                'testNumber' => 1,
                'description' => 'Document title is missing.',
                'checks' => [
                    ['status' => 'failed', 'context' => 'root/document[0]/pages[0]/annots[0]'],
                ],
            ],
        ]), 200),
    ]);

    (new ScanPdfJob($this->doc))->handle();

    expect(PdfViolation::query()->count())->toBe(2);
});

it('maps clause 6.2 to rule_key ua1/6.2-1 with wcag_criteria 1.3.1 and critical severity', function (): void {
    Http::fake([
        '*/api/validate/*' => Http::response(veraResponse([
            [
                'clause' => '6.2',
                'testNumber' => 1,
                'description' => 'Document is not tagged.',
                'checks' => [['status' => 'failed', 'context' => 'root/document[0]']],
            ],
        ]), 200),
    ]);

    (new ScanPdfJob($this->doc))->handle();

    $violation = PdfViolation::query()->first();
    expect($violation->rule_key)->toBe('ua1/6.2-1')
        ->and($violation->wcag_criteria)->toBe('1.3.1')
        ->and($violation->severity)->toBe(FindingSeverity::CRITICAL);
});

it('extracts the 1-based page number from the veraPDF context string', function (): void {
    Http::fake([
        '*/api/validate/*' => Http::response(veraResponse([
            [
                'clause' => '28',
                'testNumber' => 1,
                'description' => 'Figure missing alt text.',
                'checks' => [['status' => 'failed', 'context' => 'root/document[0]/pages[2]/content/Figure[0]']],
            ],
        ]), 200),
    ]);

    (new ScanPdfJob($this->doc))->handle();

    // pages[2] is 0-indexed → page_number should be 3
    expect(PdfViolation::query()->first()->page_number)->toBe(3);
});

it('falls back gracefully for unknown clauses', function (): void {
    Http::fake([
        '*/api/validate/*' => Http::response(veraResponse([
            [
                'clause' => '99.99',
                'testNumber' => 99,
                'description' => 'Some future rule.',
                'checks' => [['status' => 'failed', 'context' => '']],
            ],
        ]), 200),
    ]);

    (new ScanPdfJob($this->doc))->handle();

    $violation = PdfViolation::query()->first();
    expect($violation->rule_key)->toBe('ua1/99.99-99')
        ->and($violation->wcag_criteria)->toBeNull()
        ->and($violation->severity)->toBe(FindingSeverity::MINOR);
});

it('transitions the document status to Completed on success', function (): void {
    Http::fake([
        '*/api/validate/*' => Http::response(veraResponse(), 200),
    ]);

    (new ScanPdfJob($this->doc))->handle();

    $fresh = $this->doc->fresh();
    expect($fresh->status)->toBe(PdfScanStatus::Completed)
        ->and($fresh->scanned_at)->not->toBeNull();
});

it('sets violation_count to the number of check occurrences returned', function (): void {
    Http::fake([
        '*/api/validate/*' => Http::response(veraResponse([
            [
                'clause' => '6.2',
                'testNumber' => 1,
                'description' => 'Not tagged.',
                'checks' => [
                    ['status' => 'failed', 'context' => 'root/document[0]'],
                    ['status' => 'failed', 'context' => 'root/document[0]/pages[0]'],
                ],
            ],
        ]), 200),
    ]);

    (new ScanPdfJob($this->doc))->handle();

    expect($this->doc->fresh()->violation_count)->toBe(2);
});

// ─── Scanner error responses ──────────────────────────────────────────────────

it('marks the document as failed when the scanner returns a 5xx response', function (): void {
    Http::fake([
        '*/api/validate/*' => Http::response(['message' => 'Internal error'], 500),
    ]);

    (new ScanPdfJob($this->doc))->handle();

    $fresh = $this->doc->fresh();
    expect($fresh->status)->toBe(PdfScanStatus::Failed)
        ->and($fresh->error_message)->not->toBeNull();
});

it('marks the document as failed when the scanner returns a 422 response', function (): void {
    Http::fake([
        '*/api/validate/*' => Http::response(['message' => 'Could not download PDF.'], 422),
    ]);

    (new ScanPdfJob($this->doc))->handle();

    expect($this->doc->fresh()->status)->toBe(PdfScanStatus::Failed);
});

// ─── failed() handler ─────────────────────────────────────────────────────────

it('marks the document as failed via the failed() handler when an exception propagates', function (): void {
    $exception = new RuntimeException('Connection refused');

    (new ScanPdfJob($this->doc))->failed($exception);

    $fresh = $this->doc->fresh();
    expect($fresh->status)->toBe(PdfScanStatus::Failed)
        ->and($fresh->error_message)->toContain('Connection refused');
});

it('does not overwrite a terminal status via the failed() handler', function (): void {
    $this->doc->update(['status' => PdfScanStatus::Completed]);

    (new ScanPdfJob($this->doc))->failed(new RuntimeException('Late failure'));

    expect($this->doc->fresh()->status)->toBe(PdfScanStatus::Completed);
});
