<?php

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Models\Agency;
use App\Models\Finding;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Scan;
use App\Models\Scopes\TenantScope;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('belongs to an agency', function (): void {
    $agency = Agency::factory()->create();
    $organization = Organization::factory()->create(['agency_id' => $agency->id]);
    $property = Property::factory()->for($agency)->for($organization)->create();

    $issue = Issue::factory()->for($agency)->for($organization)->for($property)->create();

    expect($issue->agency)->toBeInstanceOf(Agency::class)
        ->and($issue->agency->is($agency))->toBeTrue();
});

it('belongs to an organization', function (): void {
    $agency = Agency::factory()->create();
    $organization = Organization::factory()->create(['agency_id' => $agency->id]);
    $property = Property::factory()->for($agency)->for($organization)->create();

    $issue = Issue::factory()->for($agency)->for($organization)->for($property)->create();

    expect($issue->organization)->toBeInstanceOf(Organization::class)
        ->and($issue->organization->is($organization))->toBeTrue();
});

it('belongs to a property', function (): void {
    $agency = Agency::factory()->create();
    $organization = Organization::factory()->create(['agency_id' => $agency->id]);
    $property = Property::factory()->for($agency)->for($organization)->create();

    $issue = Issue::factory()->for($agency)->for($organization)->for($property)->create();

    expect($issue->property)->toBeInstanceOf(Property::class)
        ->and($issue->property->is($property))->toBeTrue();
});

it('has many findings', function (): void {
    $agency = Agency::factory()->create();
    $organization = Organization::factory()->create(['agency_id' => $agency->id]);
    $property = Property::factory()->for($agency)->for($organization)->create();
    $scan = Scan::factory()->for($agency)->for($organization)->for($property)->create();
    $issue = Issue::factory()->for($agency)->for($organization)->for($property)->create();

    Finding::factory()->count(3)
        ->for($agency)
        ->for($scan)
        ->for($property)
        ->for($issue)
        ->create();

    expect($issue->findings)->toHaveCount(3)
        ->each->toBeInstanceOf(Finding::class);
});

it('casts severity to IssueSeverity enum', function (): void {
    $agency = Agency::factory()->create();
    $organization = Organization::factory()->create(['agency_id' => $agency->id]);
    $property = Property::factory()->for($agency)->for($organization)->create();

    $issue = Issue::factory()->for($agency)->for($organization)->for($property)->create([
        'severity' => IssueSeverity::Critical,
    ]);

    expect($issue->severity)->toBeInstanceOf(IssueSeverity::class)
        ->and($issue->severity)->toBe(IssueSeverity::Critical);
});

it('casts status to IssueStatus enum', function (): void {
    $agency = Agency::factory()->create();
    $organization = Organization::factory()->create(['agency_id' => $agency->id]);
    $property = Property::factory()->for($agency)->for($organization)->create();

    $issue = Issue::factory()->for($agency)->for($organization)->for($property)->create([
        'status' => IssueStatus::InProgress,
    ]);

    expect($issue->status)->toBeInstanceOf(IssueStatus::class)
        ->and($issue->status)->toBe(IssueStatus::InProgress);
});

it('has default occurrence_count of 1 and risk_weight of 0', function (): void {
    $agency = Agency::factory()->create();
    $organization = Organization::factory()->create(['agency_id' => $agency->id]);
    $property = Property::factory()->for($agency)->for($organization)->create();

    $issue = Issue::factory()->for($agency)->for($organization)->for($property)->create([
        'occurrence_count' => 1,
        'risk_weight' => 0,
    ]);

    expect($issue->occurrence_count)->toBe(1)
        ->and($issue->risk_weight)->toBe(0);
});

it('severity enum has expected cases', function (): void {
    expect(IssueSeverity::cases())->toHaveCount(4)
        ->and(IssueSeverity::Low->value)->toBe('low')
        ->and(IssueSeverity::Medium->value)->toBe('medium')
        ->and(IssueSeverity::High->value)->toBe('high')
        ->and(IssueSeverity::Critical->value)->toBe('critical');
});

it('status enum has expected cases', function (): void {
    expect(IssueStatus::cases())->toHaveCount(5)
        ->and(IssueStatus::Open->value)->toBe('open')
        ->and(IssueStatus::InProgress->value)->toBe('in_progress')
        ->and(IssueStatus::Resolved->value)->toBe('resolved')
        ->and(IssueStatus::Ignored->value)->toBe('ignored')
        ->and(IssueStatus::FalsePositive->value)->toBe('false_positive');
});

it('applies tenant scope to only return issues for the authenticated users agency', function (): void {
    $agencyA = Agency::factory()->create();
    $agencyB = Agency::factory()->create();

    $organizationA = Organization::factory()->create(['agency_id' => $agencyA->id]);
    $organizationB = Organization::factory()->create(['agency_id' => $agencyB->id]);

    $propertyA = Property::factory()->for($agencyA)->for($organizationA)->create();
    $propertyB = Property::factory()->for($agencyB)->for($organizationB)->create();

    $user = User::factory()->create(['agency_id' => $agencyA->id]);

    test()->actingAs($user);

    Issue::factory()->count(2)->create([
        'agency_id' => $agencyA->id,
        'organization_id' => $organizationA->id,
        'property_id' => $propertyA->id,
    ]);

    Issue::factory()->create([
        'agency_id' => $agencyB->id,
        'organization_id' => $organizationB->id,
        'property_id' => $propertyB->id,
    ]);

    expect(Issue::query()->count())->toBe(2)
        ->and(Issue::withoutGlobalScope(TenantScope::class)->count())->toBe(3);
});

it('tenant scope does not return issues from another agency', function (): void {
    $agencyA = Agency::factory()->create();
    $agencyB = Agency::factory()->create();

    $organizationB = Organization::factory()->create(['agency_id' => $agencyB->id]);
    $propertyB = Property::factory()->for($agencyB)->for($organizationB)->create();

    $user = User::factory()->create(['agency_id' => $agencyA->id]);

    test()->actingAs($user);

    Issue::factory()->create([
        'agency_id' => $agencyB->id,
        'organization_id' => $organizationB->id,
        'property_id' => $propertyB->id,
    ]);

    expect(Issue::query()->count())->toBe(0);
});

it('can be assigned to a user and transitions status to in_progress', function (): void {
    $agency = Agency::factory()->create();
    $organization = Organization::factory()->create(['agency_id' => $agency->id]);
    $property = Property::factory()->for($agency)->for($organization)->create();
    $user = User::factory()->create(['agency_id' => $agency->id]);

    $issue = Issue::factory()->for($agency)->for($organization)->for($property)->create([
        'status' => IssueStatus::Open,
    ]);

    $issue->assignToUser($user);

    $issue->refresh();

    expect($issue->assigned_user_id)->toBe($user->id)
        ->and($issue->status)->toBe(IssueStatus::InProgress);
});

it('assignToUser does not change status when already in_progress or resolved', function (): void {
    $agency = Agency::factory()->create();
    $organization = Organization::factory()->create(['agency_id' => $agency->id]);
    $property = Property::factory()->for($agency)->for($organization)->create();
    $user = User::factory()->create(['agency_id' => $agency->id]);

    $issue = Issue::factory()->for($agency)->for($organization)->for($property)->create([
        'status' => IssueStatus::Resolved,
    ]);

    $issue->assignToUser($user);

    $issue->refresh();

    expect($issue->status)->toBe(IssueStatus::Resolved);
});

it('belongs to assigned user', function (): void {
    $agency = Agency::factory()->create();
    $organization = Organization::factory()->create(['agency_id' => $agency->id]);
    $property = Property::factory()->for($agency)->for($organization)->create();
    $user = User::factory()->create(['agency_id' => $agency->id]);

    $issue = Issue::factory()->for($agency)->for($organization)->for($property)->create([
        'assigned_user_id' => $user->id,
    ]);

    expect($issue->assignedUser)->toBeInstanceOf(User::class)
        ->and($issue->assignedUser->is($user))->toBeTrue();
});

it('markResolved sets status, resolved_at, and resolution_notes', function (): void {
    $agency = Agency::factory()->create();
    $organization = Organization::factory()->create(['agency_id' => $agency->id]);
    $property = Property::factory()->for($agency)->for($organization)->create();

    $issue = Issue::factory()->for($agency)->for($organization)->for($property)->create([
        'status' => IssueStatus::InProgress,
        'resolved_at' => null,
    ]);

    $issue->markResolved('Fixed by updating alt attributes.');

    $issue->refresh();

    expect($issue->status)->toBe(IssueStatus::Resolved)
        ->and($issue->resolved_at)->not->toBeNull()
        ->and($issue->resolution_notes)->toBe('Fixed by updating alt attributes.');
});

it('markResolved works without resolution notes', function (): void {
    $agency = Agency::factory()->create();
    $organization = Organization::factory()->create(['agency_id' => $agency->id]);
    $property = Property::factory()->for($agency)->for($organization)->create();

    $issue = Issue::factory()->for($agency)->for($organization)->for($property)->create();

    $issue->markResolved();

    $issue->refresh();

    expect($issue->status)->toBe(IssueStatus::Resolved)
        ->and($issue->resolution_notes)->toBeNull();
});

it('increments occurrence_count when a matching finding is created', function (): void {
    $agency = Agency::factory()->create();
    $organization = Organization::factory()->create(['agency_id' => $agency->id]);
    $property = Property::factory()->for($agency)->for($organization)->create();
    $scan = Scan::factory()->for($agency)->for($organization)->for($property)->create();

    $issue = Issue::withoutGlobalScope(TenantScope::class)->create([
        'agency_id' => $agency->id,
        'organization_id' => $organization->id,
        'property_id' => $property->id,
        'rule_key' => 'wcag-1.1.1',
        'page_url' => 'https://example.com',
        'severity' => 'critical',
        'status' => IssueStatus::Open,
        'occurrence_count' => 1,
        'risk_weight' => 0,
        'first_detected_at' => now(),
        'last_detected_at' => now(),
    ]);

    Finding::withoutGlobalScope(TenantScope::class)->create([
        'agency_id' => $agency->id,
        'scan_id' => $scan->id,
        'property_id' => $property->id,
        'rule_key' => 'wcag-1.1.1',
        'severity' => 'critical',
        'element_identifier' => 'img.logo',
        'page_url' => 'https://example.com',
        'message' => 'Missing alt text.',
        'detected_at' => now(),
    ]);

    expect($issue->fresh()->occurrence_count)->toBe(2);
});

it('does not increment occurrence_count for resolved issues', function (): void {
    $agency = Agency::factory()->create();
    $organization = Organization::factory()->create(['agency_id' => $agency->id]);
    $property = Property::factory()->for($agency)->for($organization)->create();
    $scan = Scan::factory()->for($agency)->for($organization)->for($property)->create();

    $issue = Issue::withoutGlobalScope(TenantScope::class)->create([
        'agency_id' => $agency->id,
        'organization_id' => $organization->id,
        'property_id' => $property->id,
        'rule_key' => 'wcag-1.1.1',
        'page_url' => 'https://example.com',
        'severity' => 'critical',
        'status' => IssueStatus::Resolved,
        'occurrence_count' => 5,
        'risk_weight' => 0,
        'first_detected_at' => now(),
        'last_detected_at' => now(),
        'resolved_at' => now(),
    ]);

    Finding::withoutGlobalScope(TenantScope::class)->create([
        'agency_id' => $agency->id,
        'scan_id' => $scan->id,
        'property_id' => $property->id,
        'rule_key' => 'wcag-1.1.1',
        'severity' => 'critical',
        'element_identifier' => null,
        'page_url' => 'https://example.com',
        'message' => 'Missing alt text.',
        'detected_at' => now(),
    ]);

    expect($issue->fresh()->occurrence_count)->toBe(5);
});

it('does not increment occurrence_count when rule_key does not match', function (): void {
    $agency = Agency::factory()->create();
    $organization = Organization::factory()->create(['agency_id' => $agency->id]);
    $property = Property::factory()->for($agency)->for($organization)->create();
    $scan = Scan::factory()->for($agency)->for($organization)->for($property)->create();

    $issue = Issue::withoutGlobalScope(TenantScope::class)->create([
        'agency_id' => $agency->id,
        'organization_id' => $organization->id,
        'property_id' => $property->id,
        'rule_key' => 'wcag-1.1.1',
        'page_url' => 'https://example.com',
        'severity' => 'critical',
        'status' => IssueStatus::Open,
        'occurrence_count' => 1,
        'risk_weight' => 0,
        'first_detected_at' => now(),
        'last_detected_at' => now(),
    ]);

    Finding::withoutGlobalScope(TenantScope::class)->create([
        'agency_id' => $agency->id,
        'scan_id' => $scan->id,
        'property_id' => $property->id,
        'rule_key' => 'wcag-2.1.1',
        'severity' => 'critical',
        'element_identifier' => null,
        'page_url' => 'https://example.com',
        'message' => 'Keyboard trap.',
        'detected_at' => now(),
    ]);

    expect($issue->fresh()->occurrence_count)->toBe(1);
});

it('does not increment occurrence_count for ignored issues', function (): void {
    $agency = Agency::factory()->create();
    $organization = Organization::factory()->create(['agency_id' => $agency->id]);
    $property = Property::factory()->for($agency)->for($organization)->create();
    $scan = Scan::factory()->for($agency)->for($organization)->for($property)->create();

    $issue = Issue::withoutGlobalScope(TenantScope::class)->create([
        'agency_id' => $agency->id,
        'organization_id' => $organization->id,
        'property_id' => $property->id,
        'rule_key' => 'wcag-1.1.1',
        'page_url' => 'https://example.com',
        'severity' => 'critical',
        'status' => IssueStatus::Ignored,
        'occurrence_count' => 3,
        'risk_weight' => 0,
        'first_detected_at' => now(),
        'last_detected_at' => now(),
    ]);

    Finding::withoutGlobalScope(TenantScope::class)->create([
        'agency_id' => $agency->id,
        'scan_id' => $scan->id,
        'property_id' => $property->id,
        'rule_key' => 'wcag-1.1.1',
        'severity' => 'critical',
        'element_identifier' => null,
        'page_url' => 'https://example.com',
        'message' => 'Missing alt text.',
        'detected_at' => now(),
    ]);

    expect($issue->fresh()->occurrence_count)->toBe(3);
});

it('does not increment occurrence_count for false_positive issues', function (): void {
    $agency = Agency::factory()->create();
    $organization = Organization::factory()->create(['agency_id' => $agency->id]);
    $property = Property::factory()->for($agency)->for($organization)->create();
    $scan = Scan::factory()->for($agency)->for($organization)->for($property)->create();

    $issue = Issue::withoutGlobalScope(TenantScope::class)->create([
        'agency_id' => $agency->id,
        'organization_id' => $organization->id,
        'property_id' => $property->id,
        'rule_key' => 'wcag-1.1.1',
        'page_url' => 'https://example.com',
        'severity' => 'critical',
        'status' => IssueStatus::FalsePositive,
        'occurrence_count' => 2,
        'risk_weight' => 0,
        'first_detected_at' => now(),
        'last_detected_at' => now(),
    ]);

    Finding::withoutGlobalScope(TenantScope::class)->create([
        'agency_id' => $agency->id,
        'scan_id' => $scan->id,
        'property_id' => $property->id,
        'rule_key' => 'wcag-1.1.1',
        'severity' => 'critical',
        'element_identifier' => null,
        'page_url' => 'https://example.com',
        'message' => 'Missing alt text.',
        'detected_at' => now(),
    ]);

    expect($issue->fresh()->occurrence_count)->toBe(2);
});

it('isTerminal returns true for resolved, ignored, and false_positive', function (): void {
    expect(IssueStatus::Resolved->isTerminal())->toBeTrue()
        ->and(IssueStatus::Ignored->isTerminal())->toBeTrue()
        ->and(IssueStatus::FalsePositive->isTerminal())->toBeTrue()
        ->and(IssueStatus::Open->isTerminal())->toBeFalse()
        ->and(IssueStatus::InProgress->isTerminal())->toBeFalse();
});
