<?php

use App\Enums\IssueStatus;
use App\Mcp\Resources\PropertyRiskSummaryResource;
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
        ->create(['slug' => 'my-site']);
});

it('returns a risk summary with severity counts', function (): void {
    Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => IssueStatus::Open,
        'severity' => 'critical',
        'risk_weight' => 10.0,
    ]);

    Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => IssueStatus::Open,
        'severity' => 'high',
        'risk_weight' => 5.0,
    ]);

    $resource = app(PropertyRiskSummaryResource::class);
    $request = new Request(['slug' => 'my-site']);
    $data = json_decode((string) $resource->handle($request)->content(), true);

    expect($data['open_issue_counts']['critical'])->toBe(1)
        ->and($data['open_issue_counts']['high'])->toBe(1)
        ->and($data['total_open_issues'])->toBe(2);
});

it('returns zeros when no open issues exist', function (): void {
    $resource = app(PropertyRiskSummaryResource::class);
    $request = new Request(['slug' => 'my-site']);
    $data = json_decode((string) $resource->handle($request)->content(), true);

    expect($data['total_open_issues'])->toBe(0);
});

it('returns an error for an unknown slug', function (): void {
    $resource = app(PropertyRiskSummaryResource::class);
    $request = new Request(['slug' => 'unknown']);
    $response = $resource->handle($request);

    expect((string) $response->content())->toContain('Property not found');
});
