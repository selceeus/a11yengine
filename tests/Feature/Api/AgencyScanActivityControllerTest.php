<?php

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Scan;
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
    $this->getJson(route('api.agencies.scans.activity', $this->agency->id))
        ->assertUnauthorized();
});

it('returns 403 when the user belongs to a different agency', function (): void {
    $otherAgency = Agency::factory()->create();

    $this->actingAs($this->user)
        ->getJson(route('api.agencies.scans.activity', $otherAgency->id))
        ->assertForbidden();
});

it('returns the correct response structure', function (): void {
    $this->actingAs($this->user)
        ->getJson(route('api.agencies.scans.activity', $this->agency->id))
        ->assertOk()
        ->assertJsonStructure(['days', 'generated_at'])
        ->assertJsonCount(30, 'days');
});

it('always returns exactly 30 day points', function (): void {
    $this->actingAs($this->user)
        ->getJson(route('api.agencies.scans.activity', $this->agency->id))
        ->assertOk()
        ->assertJsonCount(30, 'days');
});

it('each day point has the expected shape', function (): void {
    $this->actingAs($this->user)
        ->getJson(route('api.agencies.scans.activity', $this->agency->id))
        ->assertOk()
        ->assertJsonStructure(['days' => [['date', 'scans', 'violations']]]);
});

it('returns zero counts when no completed scans exist', function (): void {
    $response = $this->actingAs($this->user)
        ->getJson(route('api.agencies.scans.activity', $this->agency->id))
        ->assertOk();

    $days = collect($response->json('days'));
    expect($days->sum('scans'))->toBe(0)
        ->and($days->sum('violations'))->toBe(0);
});

it('counts completed scans and aggregates violations per day', function (): void {
    Scan::factory()->completed()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'completed_at' => now()->subDays(1),
        'total_violations' => 10,
    ]);

    Scan::factory()->completed()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'completed_at' => now()->subDays(1),
        'total_violations' => 5,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('api.agencies.scans.activity', $this->agency->id))
        ->assertOk();

    $days = collect($response->json('days'));
    $yesterday = now()->subDays(1)->toDateString();
    $day = $days->firstWhere('date', $yesterday);

    expect($day)->not->toBeNull()
        ->and($day['scans'])->toBe(2)
        ->and($day['violations'])->toBe(15);
});

it('excludes non-completed scans', function (): void {
    Scan::factory()->running()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
    ]);

    Scan::factory()->failed()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('api.agencies.scans.activity', $this->agency->id))
        ->assertOk();

    expect(collect($response->json('days'))->sum('scans'))->toBe(0);
});

it('excludes scans older than 30 days', function (): void {
    Scan::factory()->completed()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'completed_at' => now()->subDays(31),
        'total_violations' => 99,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('api.agencies.scans.activity', $this->agency->id))
        ->assertOk();

    expect(collect($response->json('days'))->sum('scans'))->toBe(0);
});

it('restricts prop_admin to their assigned properties', function (): void {
    $propAdmin = User::factory()->create(['agency_id' => $this->agency->id]);
    UserRoleModel::factory()->create([
        'user_id' => $propAdmin->id,
        'role' => UserRole::PropAdmin,
        'agency_id' => $this->agency->id,
        'property_id' => $this->property->id,
    ]);

    $otherProp = Property::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
    ]);

    Scan::factory()->completed()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'completed_at' => now()->subDays(2),
        'total_violations' => 3,
    ]);

    Scan::factory()->completed()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $otherProp->id,
        'completed_at' => now()->subDays(2),
        'total_violations' => 99,
    ]);

    $response = $this->actingAs($propAdmin)
        ->getJson(route('api.agencies.scans.activity', $this->agency->id))
        ->assertOk();

    expect(collect($response->json('days'))->sum('scans'))->toBe(1)
        ->and(collect($response->json('days'))->sum('violations'))->toBe(3);
});

it('allows super_user to see all scans', function (): void {
    $superUser = User::factory()->create(['agency_id' => $this->agency->id]);
    UserRoleModel::factory()->create([
        'user_id' => $superUser->id,
        'role' => UserRole::SuperUser,
        'agency_id' => $this->agency->id,
    ]);

    Scan::factory()->completed()->count(3)->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
        'completed_at' => now()->subDays(1),
    ]);

    $response = $this->actingAs($superUser)
        ->getJson(route('api.agencies.scans.activity', $this->agency->id))
        ->assertOk();

    expect(collect($response->json('days'))->sum('scans'))->toBe(3);
});

it('does not leak scans from another agency', function (): void {
    $otherAgency = Agency::factory()->create();
    $otherOrg = Organization::factory()->create(['agency_id' => $otherAgency->id]);
    $otherProp = Property::factory()->create(['agency_id' => $otherAgency->id, 'organization_id' => $otherOrg->id]);

    Scan::factory()->completed()->create([
        'agency_id' => $otherAgency->id,
        'organization_id' => $otherOrg->id,
        'property_id' => $otherProp->id,
        'completed_at' => now()->subDays(1),
        'total_violations' => 50,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('api.agencies.scans.activity', $this->agency->id))
        ->assertOk();

    expect(collect($response->json('days'))->sum('scans'))->toBe(0);
});
