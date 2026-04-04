<?php

use App\Enums\IssueStatus;
use App\Mcp\Resources\PropertyComplianceResource;
use App\Models\Agency;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Property;
use Laravel\Mcp\Request;

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    app()->instance(Agency::class, $this->agency);

    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);
    $this->property = Property::factory()
        ->for($this->agency)
        ->for($this->organization)
        ->create(['slug' => 'compliance-site']);
});

it('returns 100% pass rate when no active issues exist', function (): void {
    $resource = app(PropertyComplianceResource::class);
    $data = json_decode((string) $resource->handle(new Request(['slug' => 'compliance-site']))->content(), true);

    expect($data['overall_pass_rate'])->toEqual(100)
        ->and($data['wcag_a']['fail'])->toBe(0)
        ->and($data['wcag_aa']['fail'])->toBe(0)
        ->and($data['wcag_aaa']['fail'])->toBe(0)
        ->and($data['failing_criteria'])->toBeEmpty();
});

it('correctly buckets failing criteria by level', function (): void {
    Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => IssueStatus::Open,
        'wcag_criteria' => '1.1.1 A',
    ]);

    Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => IssueStatus::Open,
        'wcag_criteria' => '1.4.3 AA',
    ]);

    Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => IssueStatus::Open,
        'wcag_criteria' => '1.4.6 AAA',
    ]);

    $resource = app(PropertyComplianceResource::class);
    $data = json_decode((string) $resource->handle(new Request(['slug' => 'compliance-site']))->content(), true);

    expect($data['wcag_a']['fail'])->toBe(1)
        ->and($data['wcag_a']['pass'])->toBe(29)
        ->and($data['wcag_a']['total'])->toBe(30)
        ->and($data['wcag_aa']['fail'])->toBe(1)
        ->and($data['wcag_aa']['pass'])->toBe(19)
        ->and($data['wcag_aa']['total'])->toBe(20)
        ->and($data['wcag_aaa']['fail'])->toBe(1)
        ->and($data['wcag_aaa']['pass'])->toBe(27)
        ->and($data['wcag_aaa']['total'])->toBe(28);
});

it('counts the same criterion only once even with multiple issues', function (): void {
    Issue::factory()->count(5)->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => IssueStatus::Open,
        'wcag_criteria' => '1.1.1 A',
    ]);

    $resource = app(PropertyComplianceResource::class);
    $data = json_decode((string) $resource->handle(new Request(['slug' => 'compliance-site']))->content(), true);

    expect($data['wcag_a']['fail'])->toBe(1);
});

it('excludes resolved issues from failing criteria', function (): void {
    Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => IssueStatus::Resolved,
        'wcag_criteria' => '1.1.1 A',
    ]);

    $resource = app(PropertyComplianceResource::class);
    $data = json_decode((string) $resource->handle(new Request(['slug' => 'compliance-site']))->content(), true);

    expect($data['wcag_a']['fail'])->toBe(0)
        ->and($data['overall_pass_rate'])->toEqual(100);
});

it('returns an error for an unknown slug', function (): void {
    $resource = app(PropertyComplianceResource::class);
    $response = $resource->handle(new Request(['slug' => 'no-such-slug']));

    expect((string) $response->content())->toContain('Property not found');
});

it('does not include data from other properties', function (): void {
    $otherOrg = Organization::factory()->create(['agency_id' => $this->agency->id]);
    $otherProperty = Property::factory()->for($this->agency)->for($otherOrg)->create(['slug' => 'other-site']);

    Issue::factory()->count(3)->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $otherOrg->id,
        'property_id' => $otherProperty->id,
        'status' => IssueStatus::Open,
        'wcag_criteria' => '1.1.1 A',
    ]);

    $resource = app(PropertyComplianceResource::class);
    $data = json_decode((string) $resource->handle(new Request(['slug' => 'compliance-site']))->content(), true);

    expect($data['wcag_a']['fail'])->toBe(0);
});
