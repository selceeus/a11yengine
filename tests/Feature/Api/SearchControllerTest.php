<?php

use App\Models\Agency;
use App\Models\Issue;
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
        'name' => 'Carbon Base Digital',
        'base_url' => 'https://carbonbasedigital.com',
    ]);
    $this->actor = User::factory()->create(['agency_id' => $this->agency->id]);
});

// ── Authentication ────────────────────────────────────────────────────────────

it('returns 401 for unauthenticated requests', function (): void {
    $this->getJson(route('api.search', ['q' => 'carbon']))
        ->assertUnauthorized();
});

// ── Empty / short queries ─────────────────────────────────────────────────────

it('returns empty groups when no query is provided', function (): void {
    app()->instance(Agency::class, $this->agency);

    $this->actingAs($this->actor)
        ->getJson(route('api.search'))
        ->assertOk()
        ->assertExactJson([
            'properties' => [],
            'organizations' => [],
            'issues' => [],
        ]);
});

it('returns empty groups when the query is a single character', function (): void {
    app()->instance(Agency::class, $this->agency);

    $this->actingAs($this->actor)
        ->getJson(route('api.search', ['q' => 'a']))
        ->assertOk()
        ->assertExactJson([
            'properties' => [],
            'organizations' => [],
            'issues' => [],
        ]);
});

// ── Property search ───────────────────────────────────────────────────────────

it('returns matching properties by name', function (): void {
    app()->instance(Agency::class, $this->agency);

    $this->actingAs($this->actor)
        ->getJson(route('api.search', ['q' => 'Carbon Base']))
        ->assertOk()
        ->assertJsonPath('properties.0.id', $this->property->id)
        ->assertJsonPath('properties.0.name', 'Carbon Base Digital');
});

it('returns matching properties by base_url', function (): void {
    app()->instance(Agency::class, $this->agency);

    $this->actingAs($this->actor)
        ->getJson(route('api.search', ['q' => 'carbonbasedigital']))
        ->assertOk()
        ->assertJsonPath('properties.0.id', $this->property->id);
});

// ── Organization search ───────────────────────────────────────────────────────

it('returns matching organizations by name', function (): void {
    app()->instance(Agency::class, $this->agency);

    $org = Organization::factory()->create([
        'agency_id' => $this->agency->id,
        'name' => 'Acme Corp',
    ]);

    $this->actingAs($this->actor)
        ->getJson(route('api.search', ['q' => 'Acme']))
        ->assertOk()
        ->assertJsonPath('organizations.0.id', $org->id);
});

// ── Issue search ──────────────────────────────────────────────────────────────

it('returns matching issues by rule_key', function (): void {
    app()->instance(Agency::class, $this->agency);

    $issue = Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'rule_key' => 'color-contrast',
    ]);

    $this->actingAs($this->actor)
        ->getJson(route('api.search', ['q' => 'color-contrast']))
        ->assertOk()
        ->assertJsonPath('issues.0.id', $issue->id);
});

it('returns matching issues by description', function (): void {
    app()->instance(Agency::class, $this->agency);

    $issue = Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'description' => 'Images must have alternate text',
    ]);

    $this->actingAs($this->actor)
        ->getJson(route('api.search', ['q' => 'alternate text']))
        ->assertOk()
        ->assertJsonPath('issues.0.id', $issue->id);
});

// ── Tenant isolation ──────────────────────────────────────────────────────────

it('does not return records from other agencies', function (): void {
    $otherAgency = Agency::factory()->create();
    $otherOrg = Organization::factory()->create(['agency_id' => $otherAgency->id]);
    Property::factory()->create([
        'agency_id' => $otherAgency->id,
        'organization_id' => $otherOrg->id,
        'name' => 'Carbon Base Digital',
    ]);

    app()->instance(Agency::class, $this->agency);

    // The actor's agency owns only $this->property. The other agency's property
    // should be hidden by TenantScope even though the name matches.
    $this->actingAs($this->actor)
        ->getJson(route('api.search', ['q' => 'Carbon Base']))
        ->assertOk()
        ->assertJsonCount(1, 'properties')
        ->assertJsonPath('properties.0.id', $this->property->id);
});

// ── Result structure ──────────────────────────────────────────────────────────

it('returns the expected JSON structure', function (): void {
    app()->instance(Agency::class, $this->agency);

    $this->actingAs($this->actor)
        ->getJson(route('api.search', ['q' => 'Carbon Base']))
        ->assertOk()
        ->assertJsonStructure([
            'properties' => [['id', 'name', 'base_url']],
            'organizations',
            'issues',
        ]);
});
