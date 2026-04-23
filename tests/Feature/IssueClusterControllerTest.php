<?php

use App\Models\Agency;
use App\Models\IssueCluster;
use App\Models\Organization;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);
    $this->property = Property::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
    ]);
    $this->cluster = IssueCluster::factory()->completed()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
    ]);
    $this->actor = User::factory()->create(['agency_id' => $this->agency->id]);
});

// ── show ──────────────────────────────────────────────────────────────────────

it('renders the issue cluster show page for an authenticated user', function (): void {
    $this->actingAs($this->actor)
        ->get(route('issue-clusters.show', $this->cluster))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('issue-clusters/show')
            ->has('cluster')
        );
});

it('includes cluster data in the show page', function (): void {
    $this->actingAs($this->actor)
        ->get(route('issue-clusters.show', $this->cluster))
        ->assertInertia(fn (Assert $page) => $page
            ->where('cluster.id', $this->cluster->id)
            ->where('cluster.status', 'completed')
            ->has('cluster.clusters')
            ->has('cluster.property')
        );
});

it('returns 404 when a user from another agency tries to view the cluster (tenant scope)', function (): void {
    $otherAgency = Agency::factory()->create();
    $otherUser = User::factory()->create(['agency_id' => $otherAgency->id]);

    $this->actingAs($otherUser)
        ->get(route('issue-clusters.show', $this->cluster))
        ->assertNotFound();
});

it('redirects unauthenticated users to the login page', function (): void {
    $this->get(route('issue-clusters.show', $this->cluster))
        ->assertRedirect(route('login'));
});
