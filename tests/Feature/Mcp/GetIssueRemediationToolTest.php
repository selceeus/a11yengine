<?php

use App\Domain\Issues\AiRemediationService;
use App\Enums\IssueStatus;
use App\Mcp\Servers\PropertyAccessibilityServer;
use App\Mcp\Tools\GetIssueRemediationTool;
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
        ->create();

    $this->issue = Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => IssueStatus::Open,
        'rule_key' => 'image-alt',
        'wcag_criteria' => '1.1.1',
    ]);
});

it('returns ai remediation for an issue', function (): void {
    $suggestions = [
        'explanation' => 'Image missing alt text.',
        'wcag_reference' => '1.1.1 Non-text Content (Level A)',
        'wcag_level' => 'A',
        'user_impact' => 'Screen reader users cannot understand the image.',
        'severity_rating' => 'critical',
        'code_fix' => '<img src="hero.jpg" alt="Hero banner">',
        'aria_fix' => null,
        'remediation_steps' => ['Add an alt attribute to the img element.'],
        'testing_guidance' => 'Use a screen reader to verify.',
        'estimated_effort' => 'low',
        'resources' => [],
    ];

    $this->mock(AiRemediationService::class)
        ->shouldReceive('generate')
        ->once()
        ->andReturn($suggestions);

    PropertyAccessibilityServer::tool(GetIssueRemediationTool::class, [
        'issue_id' => $this->issue->id,
    ])->assertOk()->assertSee('explanation');
});

it('returns an error for an issue not belonging to the agency', function (): void {
    $otherAgency = Agency::factory()->create();
    $otherOrg = Organization::factory()->create(['agency_id' => $otherAgency->id]);
    $otherProperty = Property::factory()->for($otherAgency)->for($otherOrg)->create();
    $otherIssue = Issue::factory()->create([
        'agency_id' => $otherAgency->id,
        'organization_id' => $otherOrg->id,
        'property_id' => $otherProperty->id,
    ]);

    PropertyAccessibilityServer::tool(GetIssueRemediationTool::class, [
        'issue_id' => $otherIssue->id,
    ])->assertSee('Issue not found');
});
