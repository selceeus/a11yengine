<?php

use App\Enums\IssueStatus;
use App\Mcp\Resources\PropertyIssuesResource;
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

it('returns open issues as a markdown document', function (): void {
    Issue::factory()->count(2)->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => IssueStatus::Open,
    ]);

    $resource = app(PropertyIssuesResource::class);
    $request = new Request(['slug' => 'my-site']);
    $response = $resource->handle($request);

    expect((string) $response->content())->toContain('Open Issues')->toContain('Issue #');
});

it('returns a no-issues message when property has no open issues', function (): void {
    $resource = app(PropertyIssuesResource::class);
    $request = new Request(['slug' => 'my-site']);
    $response = $resource->handle($request);

    expect((string) $response->content())->toContain('No open issues found');
});

it('returns an error for an unknown slug', function (): void {
    $resource = app(PropertyIssuesResource::class);
    $request = new Request(['slug' => 'unknown']);
    $response = $resource->handle($request);

    expect((string) $response->content())->toContain('Property not found');
});
