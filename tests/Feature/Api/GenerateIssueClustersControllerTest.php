<?php

use App\Jobs\GenerateIssueClusteringJob;
use App\Models\Agency;
use App\Models\Organization;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

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

it('requires authentication to generate clusters', function (): void {
    $this->postJson(route('api.properties.issue-clusters.generate', $this->property))
        ->assertUnauthorized();
});

// ── Authorization ─────────────────────────────────────────────────────────────

it('returns 404 for a user from another agency (TenantScope hides the property)', function (): void {
    $outsider = User::factory()->create(['agency_id' => Agency::factory()->create()->id]);

    Queue::fake();

    $this->actingAs($outsider)
        ->postJson(route('api.properties.issue-clusters.generate', $this->property))
        ->assertNotFound();
});

// ── Success ───────────────────────────────────────────────────────────────────

it('creates a pending IssueCluster record', function (): void {
    Queue::fake();

    $this->actingAs($this->actor)
        ->postJson(route('api.properties.issue-clusters.generate', $this->property))
        ->assertStatus(202);

    $this->assertDatabaseHas('issue_clusters', [
        'property_id' => $this->property->id,
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'status' => 'pending',
    ]);
});

it('dispatches a GenerateIssueClusteringJob for the property', function (): void {
    Queue::fake();

    $this->actingAs($this->actor)
        ->postJson(route('api.properties.issue-clusters.generate', $this->property))
        ->assertStatus(202);

    Queue::assertPushed(GenerateIssueClusteringJob::class, function (GenerateIssueClusteringJob $job): bool {
        return $job->issueCluster->property_id === $this->property->id;
    });
});

it('returns the cluster id and pending status in the response', function (): void {
    Queue::fake();

    $this->actingAs($this->actor)
        ->postJson(route('api.properties.issue-clusters.generate', $this->property))
        ->assertStatus(202)
        ->assertJsonStructure(['id', 'status'])
        ->assertJsonPath('status', 'pending');
});
