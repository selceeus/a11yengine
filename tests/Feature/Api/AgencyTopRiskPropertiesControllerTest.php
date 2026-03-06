<?php

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Models\Agency;
use App\Models\Issue;
use App\Models\Organization;
use App\Models\Property;
use App\Models\User;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->user = User::factory()->create(['agency_id' => $this->agency->id]);
    $this->org = Organization::factory()->create(['agency_id' => $this->agency->id]);
    $this->property = Property::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->org->id,
    ]);
});

it('requires authentication', function (): void {
    $this->getJson(route('api.agencies.properties.top-risk', $this->agency->id))
        ->assertUnauthorized();
});

it('returns 403 when the user belongs to a different agency', function (): void {
    $other = Agency::factory()->create();

    $this->actingAs($this->user)
        ->getJson(route('api.agencies.properties.top-risk', $other->id))
        ->assertForbidden();
});

it('returns the correct top-level response structure', function (): void {
    $this->actingAs($this->user)
        ->getJson(route('api.agencies.properties.top-risk', $this->agency->id))
        ->assertOk()
        ->assertJsonStructure(['properties', 'generated_at']);
});

it('returns an empty properties array when the agency has no properties', function (): void {
    $emptyAgency = Agency::factory()->create();
    $emptyUser = User::factory()->create(['agency_id' => $emptyAgency->id]);

    $this->actingAs($emptyUser)
        ->getJson(route('api.agencies.properties.top-risk', $emptyAgency->id))
        ->assertOk()
        ->assertJson(['properties' => []]);
});

it('returns each property with the expected keys', function (): void {
    $response = $this->actingAs($this->user)
        ->getJson(route('api.agencies.properties.top-risk', $this->agency->id))
        ->assertOk();

    $property = collect($response->json('properties'))->first();

    expect($property)->toHaveKeys([
        'id',
        'name',
        'organization_id',
        'organization_name',
        'risk_score',
        'open_issue_count',
        'highest_severity',
    ]);
});

it('returns zero risk_score and open_issue_count for a property with no issues', function (): void {
    $response = $this->actingAs($this->user)
        ->getJson(route('api.agencies.properties.top-risk', $this->agency->id))
        ->assertOk();

    $entry = collect($response->json('properties'))->firstWhere('id', $this->property->id);

    expect($entry)->not->toBeNull()
        ->and($entry['risk_score'])->toBe(0)
        ->and($entry['open_issue_count'])->toBe(0)
        ->and($entry['highest_severity'])->toBeNull();
});

it('calculates risk_score as the sum of risk_weight from open issues', function (): void {
    Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->org->id,
        'property_id' => $this->property->id,
        'status' => IssueStatus::Open,
        'risk_weight' => 30,
    ]);

    Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->org->id,
        'property_id' => $this->property->id,
        'status' => IssueStatus::Open,
        'risk_weight' => 20,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('api.agencies.properties.top-risk', $this->agency->id))
        ->assertOk();

    $entry = collect($response->json('properties'))->firstWhere('id', $this->property->id);

    expect($entry['risk_score'])->toBe(50)
        ->and($entry['open_issue_count'])->toBe(2);
});

it('excludes resolved issues from risk_score and open_issue_count', function (): void {
    Issue::factory()->resolved()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->org->id,
        'property_id' => $this->property->id,
        'risk_weight' => 100,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('api.agencies.properties.top-risk', $this->agency->id))
        ->assertOk();

    $entry = collect($response->json('properties'))->firstWhere('id', $this->property->id);

    expect($entry['risk_score'])->toBe(0)
        ->and($entry['open_issue_count'])->toBe(0);
});

it('sets highest_severity to critical when a critical open issue exists', function (): void {
    Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->org->id,
        'property_id' => $this->property->id,
        'severity' => IssueSeverity::Low,
        'status' => IssueStatus::Open,
    ]);

    Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->org->id,
        'property_id' => $this->property->id,
        'severity' => IssueSeverity::Critical,
        'status' => IssueStatus::Open,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('api.agencies.properties.top-risk', $this->agency->id))
        ->assertOk();

    $entry = collect($response->json('properties'))->firstWhere('id', $this->property->id);

    expect($entry['highest_severity'])->toBe('critical');
});

it('orders properties by risk_score descending', function (): void {
    $lowProperty = Property::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->org->id,
    ]);

    Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->org->id,
        'property_id' => $this->property->id,
        'status' => IssueStatus::Open,
        'risk_weight' => 100,
    ]);

    Issue::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->org->id,
        'property_id' => $lowProperty->id,
        'status' => IssueStatus::Open,
        'risk_weight' => 10,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('api.agencies.properties.top-risk', $this->agency->id))
        ->assertOk();

    $ids = collect($response->json('properties'))->pluck('id')->toArray();

    expect($ids[0])->toBe($this->property->id);
});

it('returns at most 10 properties', function (): void {
    Property::factory()->count(15)->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->org->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('api.agencies.properties.top-risk', $this->agency->id))
        ->assertOk();

    expect(count($response->json('properties')))->toBeLessThanOrEqual(10);
});

it('does not return properties from another agency', function (): void {
    $otherAgency = Agency::factory()->create();
    $otherOrg = Organization::factory()->create(['agency_id' => $otherAgency->id]);
    $otherProperty = Property::factory()->create([
        'agency_id' => $otherAgency->id,
        'organization_id' => $otherOrg->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('api.agencies.properties.top-risk', $this->agency->id))
        ->assertOk();

    $ids = collect($response->json('properties'))->pluck('id')->toArray();

    expect($ids)->not->toContain($otherProperty->id);
});

it('allows a super user to access any agency', function (): void {
    $superUser = User::factory()->create(['agency_id' => null]);
    $superUser->roles()->create(['role' => 'super_user']);

    $this->actingAs($superUser)
        ->getJson(route('api.agencies.properties.top-risk', $this->agency->id))
        ->assertOk();
});

it('includes the organization_name for each property', function (): void {
    $response = $this->actingAs($this->user)
        ->getJson(route('api.agencies.properties.top-risk', $this->agency->id))
        ->assertOk();

    $entry = collect($response->json('properties'))->firstWhere('id', $this->property->id);

    expect($entry['organization_name'])->toBe($this->org->name);
});
