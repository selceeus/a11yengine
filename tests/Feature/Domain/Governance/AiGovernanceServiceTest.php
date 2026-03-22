<?php

use App\Domain\Governance\AiGovernanceService;
use App\Enums\IssueStatus;
use App\Models\Agency;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Property;
use App\Models\User;

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);
    $this->property = Property::factory()->for($this->agency)->for($this->organization)->create();

    $user = User::factory()->create(['agency_id' => $this->agency->id]);
    $this->actingAs($user);

    $this->service = app(AiGovernanceService::class);
});

// ─── buildComplianceStatus (accessed via reflection) ────────────────────────

function complianceStatus(AiGovernanceService $service, ?int $propertyId, ?int $agencyId): array
{
    $method = new ReflectionMethod($service, 'buildComplianceStatus');

    return $method->invoke($service, $propertyId, $agencyId);
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('shows 100% pass rate when there are no active issues with wcag_criteria', function (): void {
    $status = complianceStatus($this->service, $this->property->id, null);

    expect($status['wcag_a']['fail'])->toBe(0)
        ->and($status['wcag_aa']['fail'])->toBe(0)
        ->and($status['wcag_aaa']['fail'])->toBe(0);
});

it('correctly buckets a Level A criterion violation', function (): void {
    Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => IssueStatus::Open,
        'wcag_criteria' => '1.1.1 A',
    ]);

    $status = complianceStatus($this->service, $this->property->id, null);

    expect($status['wcag_a']['fail'])->toBe(1)
        ->and($status['wcag_aa']['fail'])->toBe(0)
        ->and($status['wcag_aaa']['fail'])->toBe(0);
});

it('correctly buckets a Level AA criterion violation', function (): void {
    Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => IssueStatus::Open,
        'wcag_criteria' => '1.4.3 AA',
    ]);

    $status = complianceStatus($this->service, $this->property->id, null);

    expect($status['wcag_a']['fail'])->toBe(0)
        ->and($status['wcag_aa']['fail'])->toBe(1)
        ->and($status['wcag_aaa']['fail'])->toBe(0);
});

it('correctly buckets a Level AAA criterion violation', function (): void {
    Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => IssueStatus::Open,
        'wcag_criteria' => '1.4.6 AAA',
    ]);

    $status = complianceStatus($this->service, $this->property->id, null);

    expect($status['wcag_a']['fail'])->toBe(0)
        ->and($status['wcag_aa']['fail'])->toBe(0)
        ->and($status['wcag_aaa']['fail'])->toBe(1);
});

it('counts distinct criteria, not raw issue rows, per level', function (): void {
    // Three separate issues all violating the same AA criterion.
    Issue::factory()->count(3)->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => IssueStatus::Open,
        'wcag_criteria' => '1.4.3 AA',
    ]);

    $status = complianceStatus($this->service, $this->property->id, null);

    // The same criterion failing multiple times should count as 1 failing criterion.
    expect($status['wcag_aa']['fail'])->toBe(1);
});

it('does not count resolved issues as failing criteria', function (): void {
    Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => IssueStatus::Resolved,
        'wcag_criteria' => '1.4.3 AA',
    ]);

    $status = complianceStatus($this->service, $this->property->id, null);

    expect($status['wcag_aa']['fail'])->toBe(0);
});

it('does not count issues with null wcag_criteria', function (): void {
    Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => IssueStatus::Open,
        'wcag_criteria' => null,
    ]);

    $status = complianceStatus($this->service, $this->property->id, null);

    expect($status['wcag_a']['fail'])->toBe(0)
        ->and($status['wcag_aa']['fail'])->toBe(0)
        ->and($status['wcag_aaa']['fail'])->toBe(0);
});

it('does not include issues from other properties', function (): void {
    $otherProperty = Property::factory()->for($this->agency)->for($this->organization)->create();

    Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $otherProperty->id,
        'status' => IssueStatus::Open,
        'wcag_criteria' => '1.4.3 AA',
    ]);

    $status = complianceStatus($this->service, $this->property->id, null);

    expect($status['wcag_aa']['fail'])->toBe(0);
});

it('computes pass count as total criteria minus failing criteria', function (): void {
    Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'status' => IssueStatus::Open,
        'wcag_criteria' => '1.1.1 A',
    ]);

    $status = complianceStatus($this->service, $this->property->id, null);

    // Level A total = 30, 1 failing → 29 passing.
    expect($status['wcag_a']['pass'])->toBe(29)
        ->and($status['wcag_a']['fail'])->toBe(1);
});
