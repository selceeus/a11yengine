<?php

use App\Enums\ScanStatus;
use App\Mcp\Servers\PropertyAccessibilityServer;
use App\Mcp\Tools\GetScanFindingsTool;
use App\Models\Agency;
use App\Models\Finding;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Scan;

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    app()->instance(Agency::class, $this->agency);

    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);
    $this->property = Property::factory()
        ->for($this->agency)
        ->for($this->organization)
        ->create(['slug' => 'test-site']);
});

it('returns findings from the latest completed scan', function (): void {
    $scan = Scan::factory()->create([
        'agency_id' => $this->agency->id,
        'property_id' => $this->property->id,
        'status' => ScanStatus::Completed,
    ]);

    Finding::factory()->count(3)->create([
        'agency_id' => $this->agency->id,
        'scan_id' => $scan->id,
        'property_id' => $this->property->id,
    ]);

    PropertyAccessibilityServer::tool(GetScanFindingsTool::class, [
        'property_slug' => 'test-site',
    ])->assertOk()->assertSee('"total":3');
});

it('returns findings for a specific scan_id', function (): void {
    $scan1 = Scan::factory()->create([
        'agency_id' => $this->agency->id,
        'property_id' => $this->property->id,
        'status' => ScanStatus::Completed,
        'completed_at' => now()->subDays(2),
    ]);

    $scan2 = Scan::factory()->create([
        'agency_id' => $this->agency->id,
        'property_id' => $this->property->id,
        'status' => ScanStatus::Completed,
        'completed_at' => now(),
    ]);

    Finding::factory()->count(2)->create([
        'agency_id' => $this->agency->id,
        'scan_id' => $scan1->id,
        'property_id' => $this->property->id,
    ]);

    Finding::factory()->count(5)->create([
        'agency_id' => $this->agency->id,
        'scan_id' => $scan2->id,
        'property_id' => $this->property->id,
    ]);

    PropertyAccessibilityServer::tool(GetScanFindingsTool::class, [
        'property_slug' => 'test-site',
        'scan_id' => $scan1->id,
    ])->assertOk()->assertSee('"total":2');
});

it('returns an error when no completed scan exists', function (): void {
    PropertyAccessibilityServer::tool(GetScanFindingsTool::class, [
        'property_slug' => 'test-site',
    ])->assertSee('No completed scan found');
});
