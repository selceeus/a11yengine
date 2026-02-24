<?php

use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates an organization with an agency', function (): void {
    $organization = Organization::factory()->create();

    expect($organization->agency_id)->not->toBeNull()
        ->and($organization->status)->toBe('active');

    $this->assertDatabaseHas('organizations', [
        'id' => $organization->id,
    ]);
});

it('creates an inactive organization', function (): void {
    $organization = Organization::factory()->inactive()->create();

    expect($organization->status)->toBe('inactive');
});

it('creates an organization without a domain', function (): void {
    $organization = Organization::factory()->withoutDomain()->create();

    expect($organization->domain)->toBeNull();
});

it('loads the agency relationship', function (): void {
    $organization = Organization::factory()->create();

    expect($organization->agency)->not->toBeNull()
        ->and($organization->agency->id)->toBe($organization->agency_id);
});
