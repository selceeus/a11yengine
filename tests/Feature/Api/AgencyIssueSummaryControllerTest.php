<?php

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Property;
use App\Models\User;
use App\Models\UserRole as UserRoleModel;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->user = User::factory()->create(['agency_id' => $this->agency->id]);
    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);
    $this->property = Property::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
    ]);
});

it('requires authentication', function (): void {
    $this->getJson(route('api.agencies.issues.summary', $this->agency->id))
        ->assertUnauthorized();
});

it('returns 403 when the user belongs to a different agency', function (): void {
    $otherAgency = Agency::factory()->create();

    $this->actingAs($this->user)
        ->getJson(route('api.agencies.issues.summary', $otherAgency->id))
        ->assertForbidden();
});

it('returns the correct response structure', function (): void {
    $this->actingAs($this->user)
        ->getJson(route('api.agencies.issues.summary', $this->agency->id))
        ->assertOk()
        ->assertJsonStructure(['critical', 'high', 'medium', 'low', 'total', 'generated_at']);
});

it('returns zero counts when no issues exist', function (): void {
    $this->actingAs($this->user)
        ->getJson(route('api.agencies.issues.summary', $this->agency->id))
        ->assertOk()
        ->assertJson(['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0, 'total' => 0]);
});

it('aggregates active issues by severity', function (): void {
    Issue::factory()->count(2)->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'severity' => IssueSeverity::Critical,
        'status' => IssueStatus::Open,
    ]);

    Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'severity' => IssueSeverity::High,
        'status' => IssueStatus::InProgress,
    ]);

    Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'severity' => IssueSeverity::Medium,
        'status' => IssueStatus::Open,
    ]);

    $this->actingAs($this->user)
        ->getJson(route('api.agencies.issues.summary', $this->agency->id))
        ->assertOk()
        ->assertJson(['critical' => 2, 'high' => 1, 'medium' => 1, 'low' => 0, 'total' => 4]);
});

it('excludes resolved, ignored and false_positive issues', function (): void {
    Issue::factory()->resolved()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'severity' => IssueSeverity::Critical,
    ]);

    Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'severity' => IssueSeverity::High,
        'status' => IssueStatus::Ignored,
    ]);

    Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'severity' => IssueSeverity::Low,
        'status' => IssueStatus::Open,
    ]);

    $this->actingAs($this->user)
        ->getJson(route('api.agencies.issues.summary', $this->agency->id))
        ->assertOk()
        ->assertJson(['critical' => 0, 'high' => 0, 'low' => 1, 'total' => 1]);
});

it('restricts prop_admin to only their assigned properties', function (): void {
    $propAdmin = User::factory()->create(['agency_id' => $this->agency->id]);
    UserRoleModel::factory()->create([
        'user_id' => $propAdmin->id,
        'role' => UserRole::PropAdmin,
        'agency_id' => $this->agency->id,
        'property_id' => $this->property->id,
    ]);

    $otherProperty = Property::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
    ]);

    Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'severity' => IssueSeverity::Critical,
        'status' => IssueStatus::Open,
    ]);

    Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $otherProperty->id,
        'severity' => IssueSeverity::High,
        'status' => IssueStatus::Open,
    ]);

    $this->actingAs($propAdmin)
        ->getJson(route('api.agencies.issues.summary', $this->agency->id))
        ->assertOk()
        ->assertJson(['critical' => 1, 'high' => 0, 'total' => 1]);
});

it('allows super_user to see all issues across any agency', function (): void {
    $superUser = User::factory()->create(['agency_id' => $this->agency->id]);
    UserRoleModel::factory()->create([
        'user_id' => $superUser->id,
        'role' => UserRole::SuperUser,
        'agency_id' => $this->agency->id,
    ]);

    Issue::factory()->count(3)->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'severity' => IssueSeverity::High,
        'status' => IssueStatus::Open,
    ]);

    $this->actingAs($superUser)
        ->getJson(route('api.agencies.issues.summary', $this->agency->id))
        ->assertOk()
        ->assertJson(['high' => 3, 'total' => 3]);
});

it('does not leak issues from another agency', function (): void {
    $otherAgency = Agency::factory()->create();
    $otherOrg = Organization::factory()->create(['agency_id' => $otherAgency->id]);
    $otherProp = Property::factory()->create(['agency_id' => $otherAgency->id, 'organization_id' => $otherOrg->id]);

    Issue::factory()->create([
        'agency_id' => $otherAgency->id,
        'organization_id' => $otherOrg->id,
        'property_id' => $otherProp->id,
        'severity' => IssueSeverity::Critical,
        'status' => IssueStatus::Open,
    ]);

    $this->actingAs($this->user)
        ->getJson(route('api.agencies.issues.summary', $this->agency->id))
        ->assertOk()
        ->assertJson(['critical' => 0, 'total' => 0]);
});
