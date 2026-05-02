<?php

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\Organization;
use App\Models\Property;
use App\Models\ScheduledScan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();

    $this->admin = User::factory()
        ->withRole(UserRole::AgencyAdmin, agencyId: $this->agency->id)
        ->create(['agency_id' => $this->agency->id]);

    $this->org = Organization::factory()->create(['agency_id' => $this->agency->id]);

    $this->property = Property::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->org->id,
    ]);

    $this->otherProperty = Property::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->org->id,
    ]);
});

describe('PATCH api.properties.scheduled-scan.toggle', function (): void {
    it('returns 403 when scheduledScan does not belong to the property', function (): void {
        $scan = ScheduledScan::factory()->create([
            'agency_id' => $this->agency->id,
            'organization_id' => $this->org->id,
            'property_id' => $this->otherProperty->id,
        ]);

        $this->actingAs($this->admin)
            ->patch(route('api.properties.scheduled-scan.toggle', [$this->property, $scan]))
            ->assertForbidden();
    });
});

describe('PUT api.properties.scheduled-scan.update', function (): void {
    it('returns 403 when scheduledScan does not belong to the property', function (): void {
        $scan = ScheduledScan::factory()->create([
            'agency_id' => $this->agency->id,
            'organization_id' => $this->org->id,
            'property_id' => $this->otherProperty->id,
        ]);

        $this->actingAs($this->admin)
            ->put(route('api.properties.scheduled-scan.update', [$this->property, $scan]), [
                'type' => 'recurring',
                'frequency' => 'weekly',
                'run_time' => '09:00',
                'timezone' => 'UTC',
            ])
            ->assertForbidden();
    });
});

describe('DELETE api.properties.scheduled-scan.destroy', function (): void {
    it('returns 403 when scheduledScan does not belong to the property', function (): void {
        $scan = ScheduledScan::factory()->create([
            'agency_id' => $this->agency->id,
            'organization_id' => $this->org->id,
            'property_id' => $this->otherProperty->id,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('api.properties.scheduled-scan.destroy', [$this->property, $scan]))
            ->assertForbidden();
    });
});
