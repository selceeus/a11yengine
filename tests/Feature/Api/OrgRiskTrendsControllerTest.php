<?php

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\Organization;
use App\Models\RiskSnapshot;
use App\Models\User;
use App\Models\UserRole as UserRoleModel;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->user = User::factory()->create(['agency_id' => $this->agency->id]);
    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);
});

it('requires authentication', function (): void {
    $this->getJson(route('api.organizations.risk-trends', $this->organization->id))
        ->assertUnauthorized();
});

it('returns 404 when the user belongs to a different agency', function (): void {
    $other = Agency::factory()->create();
    $otherOrg = Organization::factory()->create(['agency_id' => $other->id]);

    $this->actingAs($this->user)
        ->getJson(route('api.organizations.risk-trends', $otherOrg->id))
        ->assertNotFound();
});

it('returns 422 for an invalid days parameter', function (): void {
    $this->actingAs($this->user)
        ->getJson(route('api.organizations.risk-trends', $this->organization->id).'?days=14')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('days');
});

it('accepts valid days values', function (int $days): void {
    $this->actingAs($this->user)
        ->getJson(route('api.organizations.risk-trends', $this->organization->id)."?days={$days}")
        ->assertOk();
})->with([7, 30, 90]);

it('returns the correct response structure with organizations array', function (): void {
    $this->actingAs($this->user)
        ->getJson(route('api.organizations.risk-trends', $this->organization->id))
        ->assertOk()
        ->assertJsonStructure(['organizations', 'days', 'generated_at'])
        ->assertJsonCount(1, 'organizations');
});

it('returns the organization with id, name and series keys', function (): void {
    RiskSnapshot::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'snapshot_date' => now()->subDays(1)->toDateString(),
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('api.organizations.risk-trends', $this->organization->id))
        ->assertOk();

    $org = $response->json('organizations.0');

    expect($org)->toHaveKeys(['id', 'name', 'series'])
        ->and($org['id'])->toBe($this->organization->id)
        ->and($org['series'][0])->toHaveKeys(['date', 'risk_score', 'open_issues']);
});

it('always returns the full date spine for the requested window', function (int $days): void {
    $response = $this->actingAs($this->user)
        ->getJson(route('api.organizations.risk-trends', $this->organization->id)."?days={$days}")
        ->assertOk();

    expect($response->json('days'))->toHaveCount($days)
        ->and($response->json('organizations.0.series'))->toHaveCount($days);
})->with([7, 30, 90]);

it('zero-fills dates that have no snapshot', function (): void {
    $response = $this->actingAs($this->user)
        ->getJson(route('api.organizations.risk-trends', $this->organization->id).'?days=7')
        ->assertOk();

    foreach ($response->json('organizations.0.series') as $point) {
        expect($point['risk_score'])->toBe(0)
            ->and($point['open_issues'])->toBe(0);
    }
});

it('allows a super user to access any organization', function (): void {
    $superUser = User::factory()->create(['agency_id' => null]);
    UserRoleModel::factory()->create([
        'user_id' => $superUser->id,
        'role' => UserRole::SuperUser,
    ]);

    $this->actingAs($superUser)
        ->getJson(route('api.organizations.risk-trends', $this->organization->id))
        ->assertOk();
});
