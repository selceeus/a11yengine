<?php

use App\Enums\ClusterStatus;
use App\Models\Agency;
use App\Models\IssueCluster;
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
    $this->getJson(route('api.properties.issue-clusters', $this->property))
        ->assertUnauthorized();
});

// ── Authorization ─────────────────────────────────────────────────────────────

it('returns 404 for a user from another agency (TenantScope hides the property)', function (): void {
    $outsider = User::factory()->create(['agency_id' => Agency::factory()->create()->id]);

    $this->actingAs($outsider)
        ->getJson(route('api.properties.issue-clusters', $this->property))
        ->assertNotFound();
});

// ── Empty state ───────────────────────────────────────────────────────────────

it('returns empty state when no clusters have been generated', function (): void {
    $this->actingAs($this->actor)
        ->getJson(route('api.properties.issue-clusters', $this->property))
        ->assertOk()
        ->assertJson([
            'status' => null,
            'clusters' => [],
            'total_clusters' => 0,
            'open_issues_analyzed' => 0,
            'generated_at' => null,
        ]);
});

// ── Data retrieval ────────────────────────────────────────────────────────────

it('returns the latest cluster report for the property', function (): void {
    IssueCluster::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => ClusterStatus::Completed,
        'total_clusters' => 3,
        'open_issues_analyzed' => 12,
    ]);

    $this->actingAs($this->actor)
        ->getJson(route('api.properties.issue-clusters', $this->property))
        ->assertOk()
        ->assertJsonPath('status', 'completed')
        ->assertJsonPath('total_clusters', 3)
        ->assertJsonPath('open_issues_analyzed', 12);
});

it('returns only the most recent report when multiple exist', function (): void {
    IssueCluster::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => ClusterStatus::Completed,
        'total_clusters' => 2,
        'created_at' => now()->subDay(),
    ]);

    $latest = IssueCluster::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => ClusterStatus::Completed,
        'total_clusters' => 5,
        'created_at' => now(),
    ]);

    $this->actingAs($this->actor)
        ->getJson(route('api.properties.issue-clusters', $this->property))
        ->assertOk()
        ->assertJsonPath('id', $latest->id)
        ->assertJsonPath('total_clusters', 5);
});

it('exposes the error_message for a failed cluster report', function (): void {
    IssueCluster::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => ClusterStatus::Failed,
        'error_message' => 'AI quota exceeded.',
    ]);

    $this->actingAs($this->actor)
        ->getJson(route('api.properties.issue-clusters', $this->property))
        ->assertOk()
        ->assertJsonPath('status', 'failed')
        ->assertJsonPath('error_message', 'AI quota exceeded.');
});
