<?php

use App\Models\Agency;
use App\Models\Organization;
use App\Models\RiskSnapshot;

it('creates one snapshot per organization', function (): void {
    $agency = Agency::factory()->create();
    $org1 = Organization::factory()->create(['agency_id' => $agency->id]);
    $org2 = Organization::factory()->create(['agency_id' => $agency->id]);

    $this->artisan('snapshots:organization-risk')->assertSuccessful();

    expect(RiskSnapshot::query()->count())->toBe(2)
        ->and(RiskSnapshot::query()->where('organization_id', $org1->id)->count())->toBe(1)
        ->and(RiskSnapshot::query()->where('organization_id', $org2->id)->count())->toBe(1);
});

it('records a zero risk score when the organization has no property snapshots', function (): void {
    $agency = Agency::factory()->create();
    Organization::factory()->create(['agency_id' => $agency->id]);

    $this->artisan('snapshots:organization-risk')->assertSuccessful();

    $snapshot = RiskSnapshot::query()->sole();

    expect($snapshot->total_risk_score)->toBe(0);
});

it('succeeds with an info message when there are no organizations', function (): void {
    $this->artisan('snapshots:organization-risk')
        ->expectsOutput('No organizations found — nothing to snapshot.')
        ->assertSuccessful();

    expect(RiskSnapshot::query()->count())->toBe(0);
});

it('snapshots organizations across multiple agencies', function (): void {
    $agencyA = Agency::factory()->create();
    $agencyB = Agency::factory()->create();
    Organization::factory()->create(['agency_id' => $agencyA->id]);
    Organization::factory()->create(['agency_id' => $agencyB->id]);

    $this->artisan('snapshots:organization-risk')->assertSuccessful();

    expect(RiskSnapshot::query()->count())->toBe(2);
});
