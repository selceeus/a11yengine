<?php

use App\Enums\FindingSeverity;
use App\Models\Agency;
use App\Models\Finding;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Scan;
use App\Models\User;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);
    $this->property = Property::factory()
        ->for($this->agency)
        ->for($this->organization)
        ->create();

    $this->user = User::factory()->create(['agency_id' => $this->agency->id]);
    $this->actingAs($this->user);
});

// ─── page render ─────────────────────────────────────────────────────────────

it('returns the scan diff page', function (): void {
    $scan = Scan::factory()->for($this->agency)->for($this->organization)->for($this->property)->completed()->create();

    $this->get(route('scans.diff', $scan))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('scans/diff'));
});

it('passes the scan to the diff page', function (): void {
    $scan = Scan::factory()->for($this->agency)->for($this->organization)->for($this->property)->completed()->create();

    $this->get(route('scans.diff', $scan))
        ->assertInertia(fn ($page) => $page
            ->where('scan.id', $scan->id)
            ->where('scan.property.id', $this->property->id)
        );
});

// ─── no prior scan ───────────────────────────────────────────────────────────

it('passes comparableScan as null when there is no prior completed scan', function (): void {
    $scan = Scan::factory()->for($this->agency)->for($this->organization)->for($this->property)->completed()->create();

    $this->get(route('scans.diff', $scan))
        ->assertInertia(fn ($page) => $page
            ->where('comparableScan', null)
            ->where('unchangedCount', 0)
            ->has('newFindings', 0)
            ->has('resolvedFindings', 0)
        );
});

it('ignores a prior scan that is not completed', function (): void {
    Scan::factory()->for($this->agency)->for($this->organization)->for($this->property)->create(); // pending — should be ignored
    $scan = Scan::factory()->for($this->agency)->for($this->organization)->for($this->property)->completed()->create();

    $this->get(route('scans.diff', $scan))
        ->assertInertia(fn ($page) => $page->where('comparableScan', null));
});

// ─── with prior scan ─────────────────────────────────────────────────────────

it('passes comparableScan data when a prior completed scan exists', function (): void {
    $priorScan = Scan::factory()->for($this->agency)->for($this->organization)->for($this->property)->completed()->create();
    $currentScan = Scan::factory()->for($this->agency)->for($this->organization)->for($this->property)->completed()->create();

    $this->get(route('scans.diff', $currentScan))
        ->assertInertia(fn ($page) => $page
            ->where('comparableScan.id', $priorScan->id)
        );
});

// ─── fingerprint diff logic ───────────────────────────────────────────────────

it('correctly classifies new, resolved, and unchanged findings', function (): void {
    $priorScan = Scan::factory()->for($this->agency)->for($this->organization)->for($this->property)->completed()->create();
    $currentScan = Scan::factory()->for($this->agency)->for($this->organization)->for($this->property)->completed()->create();

    $sharedData = [
        'agency_id' => $this->agency->id,
        'property_id' => $this->property->id,
        'rule_key' => 'color-contrast',
        'element_identifier' => '#shared-header',
        'page_url' => 'https://example.com/shared',
        'severity' => FindingSeverity::SERIOUS,
    ];

    // Shared finding (same fingerprint in both scans → unchanged)
    Finding::factory()->create([...$sharedData, 'scan_id' => $priorScan->id]);
    Finding::factory()->create([...$sharedData, 'scan_id' => $currentScan->id]);

    // Finding only in current scan → new
    Finding::factory()->create([
        'agency_id' => $this->agency->id,
        'property_id' => $this->property->id,
        'scan_id' => $currentScan->id,
        'rule_key' => 'image-alt',
        'element_identifier' => '#only-new',
        'page_url' => 'https://example.com/new',
    ]);

    // Finding only in prior scan → resolved
    Finding::factory()->create([
        'agency_id' => $this->agency->id,
        'property_id' => $this->property->id,
        'scan_id' => $priorScan->id,
        'rule_key' => 'label',
        'element_identifier' => '#only-old',
        'page_url' => 'https://example.com/old',
    ]);

    $this->get(route('scans.diff', $currentScan))
        ->assertInertia(fn ($page) => $page
            ->where('unchangedCount', 1)
            ->has('newFindings', 1)
            ->has('resolvedFindings', 1)
            ->where('newFindings.0.rule_key', 'image-alt')
            ->where('resolvedFindings.0.rule_key', 'label')
        );
});

it('counts all shared findings as unchanged', function (): void {
    $priorScan = Scan::factory()->for($this->agency)->for($this->organization)->for($this->property)->completed()->create();
    $currentScan = Scan::factory()->for($this->agency)->for($this->organization)->for($this->property)->completed()->create();

    $sharedData = [
        'agency_id' => $this->agency->id,
        'property_id' => $this->property->id,
        'rule_key' => 'color-contrast',
        'element_identifier' => '#persistent',
        'page_url' => 'https://example.com/',
    ];

    Finding::factory()->create([...$sharedData, 'scan_id' => $priorScan->id]);
    Finding::factory()->create([...$sharedData, 'scan_id' => $currentScan->id]);

    $this->get(route('scans.diff', $currentScan))
        ->assertInertia(fn ($page) => $page
            ->where('unchangedCount', 1)
            ->has('newFindings', 0)
            ->has('resolvedFindings', 0)
        );
});

// ─── tenant isolation ────────────────────────────────────────────────────────

it('returns 404 when viewing a diff for a scan from another agency', function (): void {
    $otherScan = Scan::factory()->completed()->create();

    $this->get(route('scans.diff', $otherScan))->assertNotFound();
});

it('redirects unauthenticated users from the diff page', function (): void {
    $scan = Scan::factory()->for($this->agency)->for($this->organization)->for($this->property)->completed()->create();

    $this->post('/logout');

    $this->get(route('scans.diff', $scan))->assertRedirect(route('login'));
});
