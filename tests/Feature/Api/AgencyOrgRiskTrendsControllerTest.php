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
    $this->org = Organization::factory()->create(['agency_id' => $this->agency->id]);
});

it('requires authentication', function (): void {
    $this->getJson(route('api.agencies.organizations.risk-trends', $this->agency->id))
        ->assertUnauthorized();
});

it('returns 403 when the user belongs to a different agency', function (): void {
    $other = Agency::factory()->create();

    $this->actingAs($this->user)
        ->getJson(route('api.agencies.organizations.risk-trends', $other->id))
        ->assertForbidden();
});

it('returns 422 for an invalid days parameter', function (): void {
    $this->actingAs($this->user)
        ->getJson(route('api.agencies.organizations.risk-trends', $this->agency->id).'?days=14')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('days');
});

it('accepts valid days values', function (int $days): void {
    $this->actingAs($this->user)
        ->getJson(route('api.agencies.organizations.risk-trends', $this->agency->id)."?days={$days}")
        ->assertOk();
})->with([7, 30, 90]);

it('returns the correct response structure', function (): void {
    $this->actingAs($this->user)
        ->getJson(route('api.agencies.organizations.risk-trends', $this->agency->id))
        ->assertOk()
        ->assertJsonStructure(['organizations', 'days', 'generated_at']);
});

it('returns empty organizations when none exist', function (): void {
    $emptyAgency = Agency::factory()->create();
    $emptyUser = User::factory()->create(['agency_id' => $emptyAgency->id]);

    $this->actingAs($emptyUser)
        ->getJson(route('api.agencies.organizations.risk-trends', $emptyAgency->id))
        ->assertOk()
        ->assertJson(['organizations' => []]);
});

it('each organization series has the expected shape', function (): void {
    RiskSnapshot::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->org->id,
        'snapshot_date' => now()->subDays(1)->toDateString(),
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('api.agencies.organizations.risk-trends', $this->agency->id))
        ->assertOk();

    $org = collect($response->json('organizations'))->firstWhere('id', $this->org->id);

    expect($org)->not->toBeNull()
        ->and($org)->toHaveKeys(['id', 'name', 'series'])
        ->and($org['series'][0])->toHaveKeys(['date', 'risk_score', 'open_issues']);
});

it('always returns the full date spine for the requested window', function (int $days): void {
    $response = $this->actingAs($this->user)
        ->getJson(route('api.agencies.organizations.risk-trends', $this->agency->id)."?days={$days}")
        ->assertOk();

    expect($response->json('days'))->toHaveCount($days);

    $org = collect($response->json('organizations'))->firstWhere('id', $this->org->id);
    expect($org['series'])->toHaveCount($days);
})->with([7, 30, 90]);

it('zero-fills dates that have no snapshot', function (): void {
    $response = $this->actingAs($this->user)
        ->getJson(route('api.agencies.organizations.risk-trends', $this->agency->id).'?days=7')
        ->assertOk();

    $org = collect($response->json('organizations'))->firstWhere('id', $this->org->id);

    foreach ($org['series'] as $point) {
        expect($point['risk_score'])->toBe(0)
            ->and($point['open_issues'])->toBe(0);
    }
});

it('populates snapshot values on the correct date', function (): void {
    $date = now()->subDays(2)->toDateString();

    RiskSnapshot::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->org->id,
        'snapshot_date' => $date,
        'total_risk_score' => 42,
        'open_issue_count' => 7,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('api.agencies.organizations.risk-trends', $this->agency->id).'?days=7')
        ->assertOk();

    $org = collect($response->json('organizations'))->firstWhere('id', $this->org->id);
    $point = collect($org['series'])->firstWhere('date', $date);

    expect($point['risk_score'])->toBe(42)
        ->and($point['open_issues'])->toBe(7);
});

it('excludes snapshots older than the window', function (): void {
    RiskSnapshot::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->org->id,
        'snapshot_date' => now()->subDays(8)->toDateString(),
        'total_risk_score' => 999,
        'open_issue_count' => 99,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('api.agencies.organizations.risk-trends', $this->agency->id).'?days=7')
        ->assertOk();

    $org = collect($response->json('organizations'))->firstWhere('id', $this->org->id);
    expect(collect($org['series'])->sum('risk_score'))->toBe(0);
});

it('does not leak data from another agency', function (): void {
    $otherAgency = Agency::factory()->create();
    $otherOrg = Organization::factory()->create(['agency_id' => $otherAgency->id]);

    RiskSnapshot::factory()->create([
        'agency_id' => $otherAgency->id,
        'organization_id' => $otherOrg->id,
        'snapshot_date' => now()->subDays(1)->toDateString(),
        'total_risk_score' => 888,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('api.agencies.organizations.risk-trends', $this->agency->id))
        ->assertOk();

    $orgIds = collect($response->json('organizations'))->pluck('id');
    expect($orgIds)->not->toContain($otherOrg->id);
});

it('allows super_user to access any agency', function (): void {
    $superUser = User::factory()->create(['agency_id' => $this->agency->id]);
    UserRoleModel::factory()->create([
        'user_id' => $superUser->id,
        'role' => UserRole::SuperUser,
        'agency_id' => $this->agency->id,
    ]);

    $this->actingAs($superUser)
        ->getJson(route('api.agencies.organizations.risk-trends', $this->agency->id))
        ->assertOk();
});
