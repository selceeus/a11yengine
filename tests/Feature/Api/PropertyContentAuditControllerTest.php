<?php

use App\Enums\ContentAuditStatus;
use App\Models\Agency;
use App\Models\ContentAudit;
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
    $this->getJson(route('api.properties.content-audit', $this->property))
        ->assertUnauthorized();
});

// ── Authorization ─────────────────────────────────────────────────────────────

it('returns 404 for a user from another agency (TenantScope hides the property)', function (): void {
    $outsider = User::factory()->create(['agency_id' => Agency::factory()->create()->id]);

    $this->actingAs($outsider)
        ->getJson(route('api.properties.content-audit', $this->property))
        ->assertNotFound();
});

// ── Empty state ───────────────────────────────────────────────────────────────

it('returns empty state when no audit has been generated', function (): void {
    $this->actingAs($this->actor)
        ->getJson(route('api.properties.content-audit', $this->property))
        ->assertOk()
        ->assertJson([
            'status' => null,
            'content_issues' => [],
            'total_issues' => 0,
            'pages_analyzed' => 0,
            'generated_at' => null,
        ]);
});

// ── Data retrieval ────────────────────────────────────────────────────────────

it('returns the latest content audit for the property', function (): void {
    ContentAudit::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => ContentAuditStatus::Completed,
        'total_issues' => 8,
        'pages_analyzed' => 5,
    ]);

    $this->actingAs($this->actor)
        ->getJson(route('api.properties.content-audit', $this->property))
        ->assertOk()
        ->assertJsonPath('status', 'completed')
        ->assertJsonPath('total_issues', 8)
        ->assertJsonPath('pages_analyzed', 5);
});

it('exposes error_message on a failed audit', function (): void {
    ContentAudit::factory()->failed()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
    ]);

    $this->actingAs($this->actor)
        ->getJson(route('api.properties.content-audit', $this->property))
        ->assertOk()
        ->assertJsonPath('status', 'failed')
        ->assertJsonPath('error_message', 'AI provider returned an unexpected response.');
});
