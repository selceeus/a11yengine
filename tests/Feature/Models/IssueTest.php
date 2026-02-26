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
    expect(IssueStatus::cases())->toHaveCount(4)
        ->and(IssueStatus::Open->value)->toBe('open')
        ->and(IssueStatus::InProgress->value)->toBe('in_progress')
        ->and(IssueStatus::Resolved->value)->toBe('resolved')
        ->and(IssueStatus::AcceptedRisk->value)->toBe('accepted_risk');
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
