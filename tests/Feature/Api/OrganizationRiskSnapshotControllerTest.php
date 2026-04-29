<?php

use App\Domain\Risk\RecordOrganizationRiskSnapshot;
use App\Domain\Risk\RecordRiskSnapshot;
use App\Models\Agency;
use App\Models\Organization;
use App\Models\User;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);
    $this->user = User::factory()->create(['agency_id' => $this->agency->id]);
});

it('returns 401 for unauthenticated requests', function (): void {
    $this->postJson(route('api.organizations.risk-snapshot', $this->organization->id))
        ->assertUnauthorized();
});

it('returns the risk snapshot for an authenticated user', function (): void {
    $snapshot = \App\Models\OrganizationRiskSnapshot::create([
        'organization_id' => $this->organization->id,
        'risk_score' => 75,
        'calculated_at' => now(),
    ]);

    $this->mock(RecordOrganizationRiskSnapshot::class)
        ->shouldReceive('handle')
        ->once()
        ->andReturn($snapshot);

    $this->mock(RecordRiskSnapshot::class)
        ->shouldReceive('handle')
        ->once();

    $this->actingAs($this->user)
        ->postJson(route('api.organizations.risk-snapshot', $this->organization->id))
        ->assertOk()
        ->assertJsonStructure(['organization_id', 'organization_name', 'risk_score', 'calculated_at']);
});
