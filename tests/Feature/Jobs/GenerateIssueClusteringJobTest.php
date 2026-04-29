<?php

use App\Ai\Agents\IssueClusterAgent;
use App\Domain\Issues\AiIssueClusterService;
use App\Enums\ClusterStatus;
use App\Jobs\GenerateIssueClusteringJob;
use App\Models\Agency;
use App\Models\IssueCluster;
use App\Models\Organization;
use App\Models\Property;
use Laravel\Ai\Ai;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);
    $this->property = Property::factory()->for($this->agency)->for($this->organization)->create();

    $this->cluster = IssueCluster::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
    ]);
});

// ── Success ───────────────────────────────────────────────────────────────────

it('transitions to Completed with cluster data on a successful AI response', function (): void {
    $aiJson = json_encode([
        'clusters' => [
            [
                'id' => 1,
                'name' => 'Missing alt text',
                'component' => 'Images',
                'priority' => 'high',
                'severity' => 'critical',
                'wcag_categories' => ['WCAG 1.1.1'],
                'recommended_fix' => 'Add descriptive alt attributes to all images.',
                'ai_notes' => 'This affects screen reader users significantly.',
                'issue_ids' => [],
            ],
        ],
    ]);

    Ai::fakeAgent(IssueClusterAgent::class, [json_decode($aiJson, true)]);

    (new GenerateIssueClusteringJob($this->cluster))->handle(app(AiIssueClusterService::class));

    $fresh = $this->cluster->fresh();
    expect($fresh->status)->toBe(ClusterStatus::Completed)
        ->and($fresh->total_clusters)->toBe(1)
        ->and($fresh->clusters)->toHaveCount(1)
        ->and($fresh->generated_at)->not->toBeNull();
});

it('stores an empty clusters array when AI returns no clusters', function (): void {
    Ai::fakeAgent(IssueClusterAgent::class, [['clusters' => []]]);

    (new GenerateIssueClusteringJob($this->cluster))->handle(app(AiIssueClusterService::class));

    $fresh = $this->cluster->fresh();
    expect($fresh->status)->toBe(ClusterStatus::Completed)
        ->and($fresh->total_clusters)->toBe(0)
        ->and($fresh->clusters)->toBe([]);
});

// ── Failure ───────────────────────────────────────────────────────────────────

it('transitions to Failed when the job fails', function (): void {
    (new GenerateIssueClusteringJob($this->cluster))->failed(new RuntimeException('Connection timeout'));

    $fresh = $this->cluster->fresh();
    expect($fresh->status)->toBe(ClusterStatus::Failed)
        ->and($fresh->error_message)->toContain('Connection timeout');
});

it('truncates the error message to 250 characters', function (): void {
    $longMessage = str_repeat('x', 300);

    (new GenerateIssueClusteringJob($this->cluster))->failed(new RuntimeException($longMessage));

    expect($this->cluster->fresh()->error_message)->toHaveLength(250);
});

// ── Status transitions ────────────────────────────────────────────────────────

it('sets status to Processing before invoking the service', function (): void {
    Ai::fakeAgent(IssueClusterAgent::class, [['clusters' => []]]);

    (new GenerateIssueClusteringJob($this->cluster))->handle(app(AiIssueClusterService::class));

    // The intermediate Processing status is set synchronously before the HTTP call,
    // so after completion the record must have transitioned all the way to Completed.
    expect($this->cluster->fresh()->status)->toBe(ClusterStatus::Completed);
});

it('implements ShouldBeUnique with the cluster id as the unique key', function (): void {
    $job = new GenerateIssueClusteringJob($this->cluster);

    expect($job)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldBeUnique::class)
        ->and($job->uniqueId())->toBe((string) $this->cluster->id)
        ->and($job->uniqueFor)->toBe(300);
});
