<?php

use App\Enums\IssueStatus;
use App\Mcp\Servers\PropertyAccessibilityServer;
use App\Mcp\Tools\GetPropertyIssuesTool;
use App\Models\Agency;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Property;

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    app()->instance(Agency::class, $this->agency);

    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);
    $this->property = Property::factory()
        ->for($this->agency)
        ->for($this->organization)
        ->create(['slug' => 'acme-site']);
});

it('returns open issues for a property slug', function (): void {
    Issue::factory()->count(3)->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => IssueStatus::Open,
    ]);

    PropertyAccessibilityServer::tool(GetPropertyIssuesTool::class, [
        'property_slug' => 'acme-site',
    ])->assertOk()->assertSee('acme-site');
});

it('filters issues by severity', function (): void {
    Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => IssueStatus::Open,
        'severity' => 'critical',
    ]);

    Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => IssueStatus::Open,
        'severity' => 'low',
    ]);

    PropertyAccessibilityServer::tool(GetPropertyIssuesTool::class, [
        'property_slug' => 'acme-site',
        'severity' => 'critical',
    ])->assertOk()->assertSee('critical');
});

it('returns an error for an unknown slug', function (): void {
    PropertyAccessibilityServer::tool(GetPropertyIssuesTool::class, [
        'property_slug' => 'nonexistent-slug',
    ])->assertSee('Property not found');
});

it('does not return issues from another agency', function (): void {
    $otherAgency = Agency::factory()->create();
    $otherOrg = Organization::factory()->create(['agency_id' => $otherAgency->id]);
    $otherProperty = Property::factory()
        ->for($otherAgency)
        ->for($otherOrg)
        ->create(['slug' => 'other-agency-site']);

    Issue::factory()->count(2)->create([
        'agency_id' => $otherAgency->id,
        'organization_id' => $otherOrg->id,
        'property_id' => $otherProperty->id,
        'status' => IssueStatus::Open,
    ]);

    // The current agency has no issues; other agency's property with same slug is invisible
    PropertyAccessibilityServer::tool(GetPropertyIssuesTool::class, [
        'property_slug' => 'acme-site',
    ])->assertOk()->assertSee('"total":0');
});
