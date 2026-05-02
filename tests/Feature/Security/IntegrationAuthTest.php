<?php

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\Integration;
use App\Models\Organization;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();

    $this->admin = User::factory()
        ->withRole(UserRole::AgencyAdmin, agencyId: $this->agency->id)
        ->create(['agency_id' => $this->agency->id]);

    $this->viewer = User::factory()->create(['agency_id' => $this->agency->id]);
});

describe('POST integrations.test', function (): void {
    it('forbids a viewer from testing an integration', function (): void {
        $integration = Integration::factory()->create([
            'agency_id' => $this->agency->id,
            'settings' => [],
        ]);

        $this->actingAs($this->viewer)
            ->post(route('integrations.test', $integration))
            ->assertForbidden();
    });
});

describe('POST integrations.store', function (): void {
    it('rejects a property_id belonging to another agency', function (): void {
        $otherAgency = Agency::factory()->create();
        $otherOrg = Organization::factory()->create(['agency_id' => $otherAgency->id]);
        $otherProperty = Property::factory()->create([
            'agency_id' => $otherAgency->id,
            'organization_id' => $otherOrg->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('integrations.store'), [
                'provider' => 'jira',
                'name' => 'Test Integration',
                'credentials' => ['api_token' => 'abc'],
                'property_id' => $otherProperty->id,
            ])
            ->assertSessionHasErrors('property_id');
    });
});
