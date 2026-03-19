<?php

use App\Enums\RiskAdvisoryStatus;
use App\Models\Agency;
use App\Models\Organization;
use App\Models\Property;
use App\Models\RiskAdvisory;
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
    $this->getJson(route('api.properties.risk-advisory', $this->property))
        ->assertUnauthorized();
});

// ── Authorization ─────────────────────────────────────────────────────────────

it('returns 404 for a user from another agency (TenantScope hides the property)', function (): void {
    $outsider = User::factory()->create(['agency_id' => Agency::factory()->create()->id]);

    $this->actingAs($outsider)
        ->getJson(route('api.properties.risk-advisory', $this->property))
        ->assertNotFound();
});

// ── Empty state ───────────────────────────────────────────────────────────────

it('returns empty state when no advisory has been generated', function (): void {
    $this->actingAs($this->actor)
        ->getJson(route('api.properties.risk-advisory', $this->property))
        ->assertOk()
        ->assertJson([
            'status' => null,
            'priorities' => [],
            'total_recommendations' => 0,
            'issues_analyzed' => 0,
            'generated_at' => null,
        ]);
});

// ── Data retrieval ────────────────────────────────────────────────────────────

it('returns the latest risk advisory for the property', function (): void {
    RiskAdvisory::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => RiskAdvisoryStatus::Completed,
        'total_recommendations' => 5,
        'issues_analyzed' => 30,
    ]);

    $this->actingAs($this->actor)
        ->getJson(route('api.properties.risk-advisory', $this->property))
        ->assertOk()
        ->assertJsonPath('status', 'completed')
        ->assertJsonPath('total_recommendations', 5)
        ->assertJsonPath('issues_analyzed', 30);
});

it('returns only the most recent advisory when multiple exist', function (): void {
    RiskAdvisory::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => RiskAdvisoryStatus::Completed,
        'total_recommendations' => 2,
        'created_at' => now()->subDay(),
    ]);

    $latest = RiskAdvisory::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => RiskAdvisoryStatus::Completed,
        'total_recommendations' => 8,
        'created_at' => now(),
    ]);

    $this->actingAs($this->actor)
        ->getJson(route('api.properties.risk-advisory', $this->property))
        ->assertOk()
        ->assertJsonPath('id', $latest->id)
        ->assertJsonPath('total_recommendations', 8);
});

it('exposes the error_message for a failed advisory', function (): void {
    RiskAdvisory::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => RiskAdvisoryStatus::Failed,
        'error_message' => 'AI quota exceeded.',
    ]);

    $this->actingAs($this->actor)
        ->getJson(route('api.properties.risk-advisory', $this->property))
        ->assertOk()
        ->assertJsonPath('status', 'failed')
        ->assertJsonPath('error_message', 'AI quota exceeded.');
});
