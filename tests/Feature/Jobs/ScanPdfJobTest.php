<?php

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

it('creates a PdfViolation record for each violation returned by the scanner', function (): void {
    Http::fake([
        '*/scan' => Http::response([
            'violations' => [
                [
                    'rule_key' => 'pdf/untagged',
                    'severity' => 'critical',
                    'wcag_criteria' => '1.3.1',
                    'description' => 'PDF is not tagged.',
                    'element_context' => null,
                    'page_number' => null,
                ],
                [
                    'rule_key' => 'pdf/no-title',
                    'severity' => 'serious',
                    'wcag_criteria' => '2.4.2',
                    'description' => 'PDF has no title.',
                    'element_context' => null,
                    'page_number' => 1,
                ],
            ],
        ], 200),
    ]);

    (new ScanPdfJob($this->doc))->handle();

    expect(PdfViolation::query()->count())->toBe(2);
});

it('transitions the document status to Completed on success', function (): void {
    Http::fake([
        '*/scan' => Http::response(['violations' => []], 200),
    ]);

    (new ScanPdfJob($this->doc))->handle();

    $fresh = $this->doc->fresh();
    expect($fresh->status)->toBe(PdfScanStatus::Completed)
        ->and($fresh->scanned_at)->not->toBeNull();
});

it('sets violation_count to the number of violations returned', function (): void {
    Http::fake([
        '*/scan' => Http::response([
            'violations' => [
                ['rule_key' => 'pdf/untagged', 'severity' => 'critical', 'wcag_criteria' => '1.3.1', 'description' => 'Not tagged.', 'element_context' => null, 'page_number' => null],
                ['rule_key' => 'pdf/no-title', 'severity' => 'serious', 'wcag_criteria' => '2.4.2', 'description' => 'No title.', 'element_context' => null, 'page_number' => null],
            ],
        ], 200),
    ]);

    (new ScanPdfJob($this->doc))->handle();

    expect($this->doc->fresh()->violation_count)->toBe(2);
});

// ─── Scanner error responses ──────────────────────────────────────────────────

it('marks the document as failed when the scanner returns a 5xx response', function (): void {
    Http::fake([
        '*/scan' => Http::response(['detail' => 'Internal error'], 500),
    ]);

    (new ScanPdfJob($this->doc))->handle();

    $fresh = $this->doc->fresh();
    expect($fresh->status)->toBe(PdfScanStatus::Failed)
        ->and($fresh->error_message)->not->toBeNull();
});

it('marks the document as failed when the scanner returns a 422 response', function (): void {
    Http::fake([
        '*/scan' => Http::response(['detail' => 'Could not download PDF.'], 422),
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
