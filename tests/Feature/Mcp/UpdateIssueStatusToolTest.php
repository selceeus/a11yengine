<?php

use App\Enums\IssueStatus;
use App\Mcp\Servers\PropertyAccessibilityServer;
use App\Mcp\Tools\UpdateIssueStatusTool;
use App\Models\Agency;
use App\Models\Issue;
use App\Models\IssueActivity;
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

    $this->issue = Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => IssueStatus::Open,
        'resolved_at' => null,
    ]);
});

it('resolves an issue and sets resolved_at', function (): void {
    PropertyAccessibilityServer::tool(UpdateIssueStatusTool::class, [
        'issue_id' => $this->issue->id,
        'status' => 'resolved',
    ])->assertOk()->assertSee('resolved');

    $this->issue->refresh();
    expect($this->issue->status)->toBe(IssueStatus::Resolved)
        ->and($this->issue->resolved_at)->not->toBeNull();
});

it('clears resolved_at when moving back to open', function (): void {
    $this->issue->update(['status' => IssueStatus::Resolved, 'resolved_at' => now()]);

    PropertyAccessibilityServer::tool(UpdateIssueStatusTool::class, [
        'issue_id' => $this->issue->id,
        'status' => 'open',
    ])->assertOk();

    $this->issue->refresh();
    expect($this->issue->status)->toBe(IssueStatus::Open)
        ->and($this->issue->resolved_at)->toBeNull();
});

it('stores resolution notes when provided', function (): void {
    PropertyAccessibilityServer::tool(UpdateIssueStatusTool::class, [
        'issue_id' => $this->issue->id,
        'status' => 'ignored',
        'resolution_notes' => 'Not applicable for this context.',
    ])->assertOk();

    $this->issue->refresh();
    expect($this->issue->resolution_notes)->toBe('Not applicable for this context.');
});

it('logs a status change activity via the observer', function (): void {
    PropertyAccessibilityServer::tool(UpdateIssueStatusTool::class, [
        'issue_id' => $this->issue->id,
        'status' => 'in_progress',
    ])->assertOk();

    expect(
        IssueActivity::where('issue_id', $this->issue->id)
            ->where('type', 'status_change')
            ->exists()
    )->toBeTrue();
});

it('returns an error for an unknown issue id', function (): void {
    PropertyAccessibilityServer::tool(UpdateIssueStatusTool::class, [
        'issue_id' => 999999,
        'status' => 'resolved',
    ])->assertSee('Issue not found');
});

it('does not update issues from another agency', function (): void {
    $otherAgency = Agency::factory()->create();
    $otherOrg = Organization::factory()->create(['agency_id' => $otherAgency->id]);
    $otherProperty = Property::factory()->for($otherAgency)->for($otherOrg)->create();
    $otherIssue = Issue::factory()->create([
        'agency_id' => $otherAgency->id,
        'organization_id' => $otherOrg->id,
        'property_id' => $otherProperty->id,
        'status' => IssueStatus::Open,
    ]);

    PropertyAccessibilityServer::tool(UpdateIssueStatusTool::class, [
        'issue_id' => $otherIssue->id,
        'status' => 'resolved',
    ])->assertSee('Issue not found');

    $otherIssue->refresh();
    expect($otherIssue->status)->toBe(IssueStatus::Open);
});
