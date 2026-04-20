<?php

use App\Enums\PdfScanStatus;
use App\Models\Agency;
use App\Models\Organization;
use App\Models\PdfDocument;
use App\Models\PdfViolation;
use App\Models\Property;
use App\Models\Scan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);
    $this->property = Property::factory()
        ->for($this->agency)
        ->for($this->organization)
        ->create();

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
        'filename' => 'report.pdf',
        'status' => PdfScanStatus::Completed,
        'violation_count' => 0,
    ]);

    $this->actor = User::factory()->create(['agency_id' => $this->agency->id]);
});

// ─── Auth ─────────────────────────────────────────────────────────────────────

it('redirects unauthenticated requests to the login page', function (): void {
    $this->get(route('pdf-documents.show', $this->doc))
        ->assertRedirect(route('login'));
});

// ─── Authorisation ────────────────────────────────────────────────────────────

it('returns 404 when the authenticated user belongs to a different agency', function (): void {
    $otherAgency = Agency::factory()->create();
    $outsider = User::factory()->create(['agency_id' => $otherAgency->id]);

    $this->actingAs($outsider)
        ->get(route('pdf-documents.show', $this->doc))
        ->assertNotFound();
});

// ─── Show ─────────────────────────────────────────────────────────────────────

it('renders the pdf-documents/show page for an authorised user', function (): void {
    $this->actingAs($this->actor)
        ->get(route('pdf-documents.show', $this->doc))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('pdf-documents/show')
            ->has('document')
        );
});

it('passes the document id, url, status, and violation_count to the page', function (): void {
    $this->actingAs($this->actor)
        ->get(route('pdf-documents.show', $this->doc))
        ->assertInertia(fn (Assert $page) => $page
            ->where('document.id', $this->doc->id)
            ->where('document.url', 'https://example.com/report.pdf')
            ->where('document.status', 'completed')
            ->where('document.violation_count', 0)
        );
});

it('includes property and scan in the document payload', function (): void {
    $this->actingAs($this->actor)
        ->get(route('pdf-documents.show', $this->doc))
        ->assertInertia(fn (Assert $page) => $page
            ->has('document.property')
            ->has('document.scan')
            ->where('document.property.id', $this->property->id)
            ->where('document.scan.id', $this->scan->id)
        );
});

it('includes violations loaded from the database', function (): void {
    PdfViolation::create([
        'pdf_document_id' => $this->doc->id,
        'rule_key' => 'pdf/untagged',
        'severity' => 'critical',
        'wcag_criteria' => '1.3.1',
        'description' => 'PDF is not tagged.',
        'element_context' => null,
        'page_number' => null,
    ]);

    $this->actingAs($this->actor)
        ->get(route('pdf-documents.show', $this->doc))
        ->assertInertia(fn (Assert $page) => $page
            ->has('document.violations', 1)
            ->where('document.violations.0.rule_key', 'pdf/untagged')
            ->where('document.violations.0.severity', 'critical')
            ->where('document.violations.0.wcag_criteria', '1.3.1')
        );
});

it('returns an empty violations array when none have been recorded', function (): void {
    $this->actingAs($this->actor)
        ->get(route('pdf-documents.show', $this->doc))
        ->assertInertia(fn (Assert $page) => $page
            ->has('document.violations', 0)
        );
});
