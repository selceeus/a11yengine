<?php

use App\Domain\Risk\RecordPropertyRiskSnapshot;
use App\Enums\IssueStatus;
use App\Models\Agency;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Property;
use App\Models\PropertyRiskSnapshot;
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

    $this->service = app(RecordPropertyRiskSnapshot::class);
});

it('persists a property risk snapshot with the correct fields', function (): void {
    Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => IssueStatus::Open,
        'risk_weight' => 10,
        'occurrence_count' => 4,
    ]);

    Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => IssueStatus::Open,
        'risk_weight' => 5,
        'occurrence_count' => 2,
    ]);

    $snapshot = $this->service->handle($this->property);

    expect(PropertyRiskSnapshot::query()->count())->toBe(1)
        ->and($snapshot)->toBeInstanceOf(PropertyRiskSnapshot::class)
        ->and($snapshot->property_id)->toBe($this->property->id)
        ->and($snapshot->risk_score)->toBe(50) // (10*4) + (5*2)
        ->and($snapshot->open_issue_count)->toBe(2)
        ->and($snapshot->snapshot_date->toDateString())->toBe(now()->toDateString())
        ->and($snapshot->created_at)->not->toBeNull();
});

it('records zero score and count when there are no open issues', function (): void {
    $snapshot = $this->service->handle($this->property);

    expect($snapshot->risk_score)->toBe(0)
        ->and($snapshot->open_issue_count)->toBe(0);
});

it('accepts a property id instead of a model', function (): void {
    Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => IssueStatus::Open,
        'risk_weight' => 8,
        'occurrence_count' => 5,
    ]);

    $snapshot = $this->service->handle($this->property->id);

    expect($snapshot->property_id)->toBe($this->property->id)
        ->and($snapshot->risk_score)->toBe(40);
});
