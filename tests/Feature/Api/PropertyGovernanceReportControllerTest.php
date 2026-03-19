<?php

use App\Enums\GovernanceReportStatus;
use App\Models\Agency;
use App\Models\GovernanceReport;
use App\Models\Organization;
use App\Models\Property;
use App\Models\User;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);
    $this->property = Property::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
    ]);
    $this->actor = User::factory()->create(['agency_id' => $this->agency->id]);
});

// ── Authentication ────────────────────────────────────────────────────────────

it('requires authentication', function (): void {
    $this->getJson(route('api.properties.governance-report', $this->property))
        ->assertUnauthorized();
});

// ── Authorization ─────────────────────────────────────────────────────────────

it('returns 404 for a user from another agency (TenantScope hides the property)', function (): void {
    $outsider = User::factory()->create(['agency_id' => Agency::factory()->create()->id]);

    $this->actingAs($outsider)
        ->getJson(route('api.properties.governance-report', $this->property))
        ->assertNotFound();
});

// ── Empty state ───────────────────────────────────────────────────────────────

it('returns empty state when no report has been generated', function (): void {
    $this->actingAs($this->actor)
        ->getJson(route('api.properties.governance-report', $this->property))
        ->assertOk()
        ->assertJson([
            'status' => null,
            'report_scope' => 'property',
            'executive_narrative' => null,
            'summary_cards' => [],
            'risk_trend' => [],
            'recommendations' => [],
            'generated_at' => null,
        ]);
});

// ── Data retrieval ────────────────────────────────────────────────────────────

it('returns the latest governance report for the property', function (): void {
    $report = GovernanceReport::factory()->completed()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
    ]);

    $this->actingAs($this->actor)
        ->getJson(route('api.properties.governance-report', $this->property))
        ->assertOk()
        ->assertJsonPath('id', $report->id)
        ->assertJsonPath('status', GovernanceReportStatus::Completed->value)
        ->assertJsonPath('report_scope', 'property');
});

it('returns the most recently created report when multiple exist', function (): void {
    GovernanceReport::factory()->completed()->create([
        'agency_id' => $this->agency->id,
        'property_id' => $this->property->id,
        'created_at' => now()->subDays(10),
    ]);

    $latest = GovernanceReport::factory()->completed()->create([
        'agency_id' => $this->agency->id,
        'property_id' => $this->property->id,
        'created_at' => now(),
    ]);

    $this->actingAs($this->actor)
        ->getJson(route('api.properties.governance-report', $this->property))
        ->assertOk()
        ->assertJsonPath('id', $latest->id);
});
