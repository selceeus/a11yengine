<?php

use App\Domain\Risk\CalculatePropertyRiskScore;
use App\Enums\IssueStatus;
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
    ]);

    $user = User::factory()->create(['agency_id' => $this->agency->id]);
    $this->actingAs($user);

    $this->service = app(CalculatePropertyRiskScore::class);
});

it('returns zero score and count when there are no issues', function (): void {
    $result = $this->service->handle($this->property);

    expect($result['risk_score'])->toBe(0)
        ->and($result['open_issue_count'])->toBe(0);
});

it('calculates risk_score as sum of risk_weight times occurrence_count', function (): void {
    Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => IssueStatus::Open,
        'risk_weight' => 10,
        'occurrence_count' => 3,
    ]);

    Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => IssueStatus::InProgress,
        'risk_weight' => 5,
        'occurrence_count' => 2,
    ]);

    $result = $this->service->handle($this->property);

    expect($result['risk_score'])->toBe(40) // (10*3) + (5*2)
        ->and($result['open_issue_count'])->toBe(2);
});

it('excludes terminal status issues from score and count', function (): void {
    Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => IssueStatus::Resolved,
        'risk_weight' => 100,
        'occurrence_count' => 10,
    ]);

    Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => IssueStatus::Ignored,
        'risk_weight' => 50,
        'occurrence_count' => 5,
    ]);

    $result = $this->service->handle($this->property);

    expect($result['risk_score'])->toBe(0)
        ->and($result['open_issue_count'])->toBe(0);
});

it('accepts a property id instead of a model', function (): void {
    Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => IssueStatus::Open,
        'risk_weight' => 7,
        'occurrence_count' => 2,
    ]);

    $result = $this->service->handle($this->property->id);

    expect($result['risk_score'])->toBe(14)
        ->and($result['open_issue_count'])->toBe(1);
});

it('does not include issues from other properties', function (): void {
    $otherProperty = Property::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
    ]);

    Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $otherProperty->id,
        'status' => IssueStatus::Open,
        'risk_weight' => 50,
        'occurrence_count' => 10,
    ]);

    $result = $this->service->handle($this->property);

    expect($result['risk_score'])->toBe(0)
        ->and($result['open_issue_count'])->toBe(0);
});
