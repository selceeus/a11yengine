<?php

use App\Jobs\RunScanJob;
use App\Mcp\Servers\PropertyAccessibilityServer;
use App\Mcp\Tools\TriggerScanTool;
use App\Models\Agency;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Scan;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    app()->instance(Agency::class, $this->agency);

    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);
    $this->property = Property::factory()
        ->for($this->agency)
        ->for($this->organization)
        ->create(['slug' => 'scan-target']);
});

it('creates a pending scan for the property', function (): void {
    Queue::fake();

    PropertyAccessibilityServer::tool(TriggerScanTool::class, [
        'property_slug' => 'scan-target',
    ])->assertOk()->assertSee('Scan queued successfully');

    expect(Scan::where('property_id', $this->property->id)->where('status', 'pending')->exists())->toBeTrue();
});

it('dispatches a RunScanJob', function (): void {
    Queue::fake();

    PropertyAccessibilityServer::tool(TriggerScanTool::class, [
        'property_slug' => 'scan-target',
    ])->assertOk();

    Queue::assertPushed(RunScanJob::class);
});

it('returns an error for an unknown property slug', function (): void {
    Queue::fake();

    PropertyAccessibilityServer::tool(TriggerScanTool::class, [
        'property_slug' => 'does-not-exist',
    ])->assertSee('Property not found');

    Queue::assertNothingPushed();
});

it('does not scan properties from another agency', function (): void {
    Queue::fake();

    $otherAgency = Agency::factory()->create();
    $otherOrg = Organization::factory()->create(['agency_id' => $otherAgency->id]);
    Property::factory()->for($otherAgency)->for($otherOrg)->create(['slug' => 'other-agency-property']);

    // Current agency has no property with this slug — should not be found
    PropertyAccessibilityServer::tool(TriggerScanTool::class, [
        'property_slug' => 'other-agency-property',
    ])->assertSee('Property not found');

    Queue::assertNothingPushed();
});
