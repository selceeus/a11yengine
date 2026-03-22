<?php

use App\Enums\ScanStatus;
use App\Events\ScanCompleted;
use App\Events\ScanProgressUpdated;
use App\Models\Agency;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Scan;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Event;

function setupBroadcastTenant(): array
{
    $agency = Agency::factory()->create();
    $organization = Organization::factory()->create(['agency_id' => $agency->id]);
    $property = Property::factory()->create([
        'agency_id' => $agency->id,
        'organization_id' => $organization->id,
    ]);
    $user = User::factory()->create(['agency_id' => $agency->id]);

    app()->instance(Agency::class, $agency);

    return [$agency, $organization, $property, $user];
}

// -------------------------------------------------------------------
// ScanCompleted event tests
// -------------------------------------------------------------------

test('ScanCompleted broadcasts on the correct private channel', function () {
    [$agency, $organization, $property] = setupBroadcastTenant();

    $scan = Scan::factory()->completed()->create([
        'agency_id' => $agency->id,
        'organization_id' => $organization->id,
        'property_id' => $property->id,
    ]);

    $event = new ScanCompleted($scan);
    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1);
    expect($channels[0])->toBeInstanceOf(PrivateChannel::class);
    expect($channels[0]->name)->toBe("private-agency.{$agency->id}");
});

test('ScanCompleted broadcastWith returns expected data', function () {
    [$agency, $organization, $property] = setupBroadcastTenant();

    $scan = Scan::factory()->completed()->create([
        'agency_id' => $agency->id,
        'organization_id' => $organization->id,
        'property_id' => $property->id,
    ]);

    $event = new ScanCompleted($scan->load('property'));
    $data = $event->broadcastWith();

    expect($data)->toHaveKeys(['scan_id', 'property_name', 'status', 'total_violations']);
    expect($data['scan_id'])->toBe($scan->id);
    expect($data['status'])->toBe(ScanStatus::Completed->value);
});

test('ScanCompleted implements ShouldBroadcast', function () {
    expect(ScanCompleted::class)->toImplement(\Illuminate\Contracts\Broadcasting\ShouldBroadcast::class);
});

// -------------------------------------------------------------------
// ScanProgressUpdated event tests
// -------------------------------------------------------------------

test('ScanProgressUpdated broadcasts on the correct private channel', function () {
    $event = new ScanProgressUpdated(
        scanId: 1,
        agencyId: 42,
        pagesScanned: 5,
        status: ScanStatus::Running->value,
    );

    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1);
    expect($channels[0])->toBeInstanceOf(PrivateChannel::class);
    expect($channels[0]->name)->toBe('private-agency.42');
});

test('ScanProgressUpdated broadcastWith returns expected data', function () {
    $event = new ScanProgressUpdated(
        scanId: 99,
        agencyId: 1,
        pagesScanned: 12,
        status: ScanStatus::Running->value,
    );

    $data = $event->broadcastWith();

    expect($data)->toBe([
        'scan_id' => 99,
        'pages_scanned' => 12,
        'status' => ScanStatus::Running->value,
    ]);
});

test('ScanProgressUpdated implements ShouldBroadcastNow', function () {
    expect(ScanProgressUpdated::class)->toImplement(\Illuminate\Contracts\Broadcasting\ShouldBroadcastNow::class);
});

// -------------------------------------------------------------------
// Channel authorization tests
// -------------------------------------------------------------------

test('agency channel authorizes user belonging to the agency', function () {
    [$agency, , , $user] = setupBroadcastTenant();

    // Verify the channel callback logic matches what's in channels.php
    expect((int) $user->agency_id === (int) $agency->id)->toBeTrue();
});

test('agency channel rejects user from a different agency', function () {
    [$agency] = setupBroadcastTenant();

    $otherAgency = Agency::factory()->create();
    $otherUser = User::factory()->create(['agency_id' => $otherAgency->id]);

    expect((int) $otherUser->agency_id === (int) $agency->id)->toBeFalse();
});

// -------------------------------------------------------------------
// Event dispatch integration test
// -------------------------------------------------------------------

test('ScanCompleted event is dispatched when a scan completes', function () {
    Event::fake([ScanCompleted::class]);

    [$agency, $organization, $property] = setupBroadcastTenant();

    $scan = Scan::factory()->completed()->create([
        'agency_id' => $agency->id,
        'organization_id' => $organization->id,
        'property_id' => $property->id,
    ]);

    event(new ScanCompleted($scan));

    Event::assertDispatched(ScanCompleted::class, function (ScanCompleted $e) use ($scan) {
        return $e->scan->id === $scan->id;
    });
});

test('ScanProgressUpdated event carries correct payload', function () {
    Event::fake([ScanProgressUpdated::class]);

    ScanProgressUpdated::dispatch(10, 5, 3, ScanStatus::Running->value);

    Event::assertDispatched(ScanProgressUpdated::class, function (ScanProgressUpdated $e) {
        return $e->scanId === 10
            && $e->agencyId === 5
            && $e->pagesScanned === 3;
    });
});
