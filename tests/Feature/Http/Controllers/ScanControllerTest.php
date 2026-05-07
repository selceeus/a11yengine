<?php

use App\Enums\ScanStatus;
use App\Enums\UserRole as UserRoleEnum;
use App\Jobs\RunScanJob;
use App\Models\Agency;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Scan;
use App\Models\ScanJourney;
use App\Models\ScanPage;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);
    $this->property = Property::factory()
        ->for($this->agency)
        ->for($this->organization)
        ->create();

    $this->user = User::factory()->withRole(UserRoleEnum::AgencyAdmin, agencyId: $this->agency->id)->create(['agency_id' => $this->agency->id]);
    $this->actingAs($this->user);
});

// ─── index ───────────────────────────────────────────────────────────────────

it('returns the scans index page', function (): void {
    $this->get(route('scans.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('scans/index'));
});

it('passes existing scans to the index page', function (): void {
    Scan::factory()->for($this->agency)->for($this->organization)->for($this->property)->create();

    $this->get(route('scans.index'))
        ->assertInertia(fn ($page) => $page->has('scans', 1));
});

it('passes the agency properties to the index page for the trigger form', function (): void {
    $this->get(route('scans.index'))
        ->assertInertia(fn ($page) => $page->has('properties', 1));
});

it('redirects unauthenticated users from the index', function (): void {
    $this->post('/logout');

    $this->get(route('scans.index'))->assertRedirect(route('login'));
});

it('does not expose scans from another agency on the index', function (): void {
    // ScanFactory creates its own independent agency, so this scan is invisible to $this->user
    Scan::factory()->create();

    $this->get(route('scans.index'))
        ->assertInertia(fn ($page) => $page->has('scans', 0));
});

// ─── store ───────────────────────────────────────────────────────────────────

it('creates a scan in pending status', function (): void {
    Queue::fake();

    $this->post(route('scans.store'), ['property_id' => $this->property->id])
        ->assertRedirect();

    $scan = Scan::query()->first();

    expect($scan)->not->toBeNull()
        ->and($scan->status)->toBe(ScanStatus::Pending)
        ->and($scan->property_id)->toBe($this->property->id)
        ->and($scan->agency_id)->toBe($this->agency->id);
});

it('dispatches RunScanJob after creating a scan', function (): void {
    Queue::fake();

    $this->post(route('scans.store'), ['property_id' => $this->property->id]);

    Queue::assertPushed(RunScanJob::class, function (RunScanJob $job): bool {
        return $job->scan->id === Scan::query()->first()->id;
    });
});

it('redirects to the new scan show page after store', function (): void {
    Queue::fake();

    $this->post(route('scans.store'), ['property_id' => $this->property->id])
        ->assertRedirect(route('scans.show', Scan::query()->first()));
});

it('returns a validation error when property_id is missing', function (): void {
    Queue::fake();

    $this->post(route('scans.store'), [])
        ->assertSessionHasErrors('property_id');

    Queue::assertNothingPushed();
});

it('returns 404 when storing a scan for a property from another agency', function (): void {
    Queue::fake();

    $otherProperty = Property::factory()->create();

    $this->post(route('scans.store'), ['property_id' => $otherProperty->id])
        ->assertNotFound();

    Queue::assertNothingPushed();
});

it('redirects unauthenticated users from store', function (): void {
    $this->post('/logout');

    $this->post(route('scans.store'), ['property_id' => $this->property->id])
        ->assertRedirect(route('login'));
});

it('stores target_url when a single-page scan is requested', function (): void {
    Queue::fake();

    $this->post(route('scans.store'), [
        'property_id' => $this->property->id,
        'target_url' => 'https://example.com/about',
    ])->assertRedirect();

    $scan = Scan::query()->first();

    expect($scan->target_url)->toBe('https://example.com/about');
});

it('creates a full-site scan when target_url is omitted', function (): void {
    Queue::fake();

    $this->post(route('scans.store'), ['property_id' => $this->property->id])
        ->assertRedirect();

    expect(Scan::query()->first()->target_url)->toBeNull();
});

it('stores scan_journey_id when a journey scan is requested', function (): void {
    Queue::fake();

    $journey = ScanJourney::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
    ]);

    $this->post(route('scans.store'), [
        'property_id' => $this->property->id,
        'scan_journey_id' => $journey->id,
    ])->assertRedirect();

    expect(Scan::query()->first()->scan_journey_id)->toBe($journey->id);
});

it('rejects an invalid target_url', function (): void {
    Queue::fake();

    $this->post(route('scans.store'), [
        'property_id' => $this->property->id,
        'target_url' => 'not-a-url',
    ])->assertSessionHasErrors('target_url');

    Queue::assertNothingPushed();
});

// ─── show ────────────────────────────────────────────────────────────────────

it('returns the scan show page', function (): void {
    $scan = Scan::factory()->for($this->agency)->for($this->organization)->for($this->property)->create();

    $this->get(route('scans.show', $scan))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('scans/show'));
});

it('passes the scan to the show page', function (): void {
    $scan = Scan::factory()
        ->for($this->agency)
        ->for($this->organization)
        ->for($this->property)
        ->create();

    $this->get(route('scans.show', $scan))
        ->assertInertia(fn ($page) => $page->has('scan')
            ->where('scan.id', $scan->id)
            ->where('scan.status', ScanStatus::Pending->value)
        );
});

it('includes scan pages in the show response', function (): void {
    $scan = Scan::factory()
        ->for($this->agency)
        ->for($this->organization)
        ->for($this->property)
        ->completed()
        ->create();

    ScanPage::factory()->for($scan)->for($this->agency)->count(3)->create();

    $this->get(route('scans.show', $scan))
        ->assertInertia(fn ($page) => $page->has('scan.scan_pages', 3));
});

it('includes the property on the show response', function (): void {
    $scan = Scan::factory()->for($this->agency)->for($this->organization)->for($this->property)->create();

    $this->get(route('scans.show', $scan))
        ->assertInertia(fn ($page) => $page->has('scan.property'));
});

it('returns 404 when viewing a scan from another agency', function (): void {
    $otherScan = Scan::factory()->create();

    $this->get(route('scans.show', $otherScan))->assertNotFound();
});

it('redirects unauthenticated users from show', function (): void {
    $scan = Scan::factory()->for($this->agency)->for($this->organization)->for($this->property)->create();

    $this->post('/logout');

    $this->get(route('scans.show', $scan))->assertRedirect(route('login'));
});

// ─── destroy ─────────────────────────────────────────────────────────────────

it('a propAdmin can delete a completed scan', function (): void {
    $actor = User::factory()->withRole(UserRoleEnum::PropAdmin, propertyId: $this->property->id)->create(['agency_id' => $this->agency->id]);
    $scan = Scan::factory()->for($this->agency)->for($this->organization)->for($this->property)->completed()->create();

    $this->actingAs($actor)
        ->delete(route('scans.destroy', $scan))
        ->assertRedirect(route('scans.index'));

    $this->assertDatabaseMissing('scans', ['id' => $scan->id]);
});

it('a propAdmin can delete a failed scan', function (): void {
    $actor = User::factory()->withRole(UserRoleEnum::PropAdmin, propertyId: $this->property->id)->create(['agency_id' => $this->agency->id]);
    $scan = Scan::factory()->for($this->agency)->for($this->organization)->for($this->property)->failed()->create();

    $this->actingAs($actor)
        ->delete(route('scans.destroy', $scan))
        ->assertRedirect(route('scans.index'));

    $this->assertDatabaseMissing('scans', ['id' => $scan->id]);
});

it('an editor cannot delete a scan', function (): void {
    $editor = User::factory()->withRole(UserRoleEnum::Editor, propertyId: $this->property->id)->create(['agency_id' => $this->agency->id]);
    $scan = Scan::factory()->for($this->agency)->for($this->organization)->for($this->property)->completed()->create();

    $this->actingAs($editor)
        ->delete(route('scans.destroy', $scan))
        ->assertForbidden();

    $this->assertDatabaseHas('scans', ['id' => $scan->id]);
});

it('cannot delete a pending scan', function (): void {
    $actor = User::factory()->withRole(UserRoleEnum::PropAdmin, propertyId: $this->property->id)->create(['agency_id' => $this->agency->id]);
    $scan = Scan::factory()->for($this->agency)->for($this->organization)->for($this->property)->create();

    $this->actingAs($actor)
        ->delete(route('scans.destroy', $scan))
        ->assertSessionHasErrors('scan');

    $this->assertDatabaseHas('scans', ['id' => $scan->id]);
});

it('cannot delete a running scan', function (): void {
    $actor = User::factory()->withRole(UserRoleEnum::PropAdmin, propertyId: $this->property->id)->create(['agency_id' => $this->agency->id]);
    $scan = Scan::factory()->for($this->agency)->for($this->organization)->for($this->property)->running()->create();

    $this->actingAs($actor)
        ->delete(route('scans.destroy', $scan))
        ->assertSessionHasErrors('scan');

    $this->assertDatabaseHas('scans', ['id' => $scan->id]);
});

it('returns 404 when deleting a scan from another agency', function (): void {
    $scan = Scan::factory()->completed()->create();

    $this->delete(route('scans.destroy', $scan))->assertNotFound();
});

it('passes canDelete true to the index page for a propAdmin', function (): void {
    $actor = User::factory()->withRole(UserRoleEnum::PropAdmin, propertyId: $this->property->id)->create(['agency_id' => $this->agency->id]);
    Scan::factory()->for($this->agency)->for($this->organization)->for($this->property)->completed()->create();

    $this->actingAs($actor)
        ->get(route('scans.index'))
        ->assertInertia(fn ($page) => $page->where('scans.0.canDelete', true));
});

it('passes canDelete false to the index page for an editor', function (): void {
    $editor = User::factory()->withRole(UserRoleEnum::Editor, propertyId: $this->property->id)->create(['agency_id' => $this->agency->id]);
    Scan::factory()->for($this->agency)->for($this->organization)->for($this->property)->completed()->create();

    $this->actingAs($editor)
        ->get(route('scans.index'))
        ->assertInertia(fn ($page) => $page->where('scans.0.canDelete', false));
});
