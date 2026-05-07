<?php

use App\Enums\UserRole as UserRoleEnum;
use App\Models\Agency;
use App\Models\Organization;
use App\Models\Property;
use App\Models\ScanJourney;
use App\Models\ScanJourneyStep;
use App\Models\User;

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

// ─── index ────────────────────────────────────────────────────────────────────

it('returns the journeys index page', function (): void {
    $this->get(route('journeys.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('journeys/index'));
});

it('passes existing journeys to the index page', function (): void {
    ScanJourney::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
    ]);

    $this->get(route('journeys.index'))
        ->assertInertia(fn ($page) => $page->has('journeys', 1));
});

it('does not expose journeys from another agency on the index', function (): void {
    ScanJourney::factory()->create();

    $this->get(route('journeys.index'))
        ->assertInertia(fn ($page) => $page->has('journeys', 0));
});

it('redirects unauthenticated users from the journeys index', function (): void {
    $this->post('/logout');

    $this->get(route('journeys.index'))->assertRedirect(route('login'));
});

// ─── create ───────────────────────────────────────────────────────────────────

it('returns the journey create page', function (): void {
    $this->get(route('journeys.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('journeys/create'));
});

// ─── store ────────────────────────────────────────────────────────────────────

it('creates a journey with steps', function (): void {
    $this->post(route('journeys.store'), [
        'name' => 'Marketing funnel',
        'property_id' => $this->property->id,
        'description' => 'Top-of-funnel pages',
        'steps' => [
            ['label' => 'Home', 'url' => 'https://example.com'],
            ['label' => 'About', 'url' => 'https://example.com/about'],
        ],
    ])->assertRedirect(route('journeys.index'));

    $journey = ScanJourney::query()->first();
    expect($journey)->not->toBeNull()
        ->and($journey->name)->toBe('Marketing funnel')
        ->and($journey->agency_id)->toBe($this->agency->id);

    expect(ScanJourneyStep::query()->where('scan_journey_id', $journey->id)->count())->toBe(2);
});

it('assigns positions in order when creating steps', function (): void {
    $this->post(route('journeys.store'), [
        'name' => 'Funnel',
        'property_id' => $this->property->id,
        'steps' => [
            ['label' => 'Step A', 'url' => 'https://example.com/a'],
            ['label' => 'Step B', 'url' => 'https://example.com/b'],
            ['label' => 'Step C', 'url' => 'https://example.com/c'],
        ],
    ])->assertRedirect();

    $steps = ScanJourneyStep::query()->orderBy('position')->get();
    expect($steps->pluck('position')->all())->toBe([0, 1, 2]);
});

it('returns validation errors when steps are missing', function (): void {
    $this->post(route('journeys.store'), [
        'name' => 'No steps',
        'property_id' => $this->property->id,
        'steps' => [],
    ])->assertSessionHasErrors('steps');
});

it('returns validation errors when a step url is invalid', function (): void {
    $this->post(route('journeys.store'), [
        'name' => 'Bad URL',
        'property_id' => $this->property->id,
        'steps' => [['label' => 'Home', 'url' => 'not-a-url']],
    ])->assertSessionHasErrors('steps.0.url');
});

// ─── edit ─────────────────────────────────────────────────────────────────────

it('returns the journey edit page', function (): void {
    $journey = ScanJourney::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
    ]);

    $this->get(route('journeys.edit', $journey))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('journeys/edit'));
});

it('returns 404 when editing a journey from another agency', function (): void {
    $otherJourney = ScanJourney::factory()->create();

    $this->get(route('journeys.edit', $otherJourney))->assertNotFound();
});

// ─── update ───────────────────────────────────────────────────────────────────

it('updates journey name and re-syncs steps', function (): void {
    $journey = ScanJourney::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
    ]);
    ScanJourneyStep::factory()->create(['scan_journey_id' => $journey->id, 'position' => 0]);

    $this->patch(route('journeys.update', $journey), [
        'name' => 'Updated name',
        'steps' => [
            ['label' => 'New Step 1', 'url' => 'https://example.com/1'],
            ['label' => 'New Step 2', 'url' => 'https://example.com/2'],
        ],
    ])->assertRedirect(route('journeys.index'));

    $journey->refresh();
    expect($journey->name)->toBe('Updated name');
    expect(ScanJourneyStep::query()->where('scan_journey_id', $journey->id)->count())->toBe(2);
});

// ─── destroy ──────────────────────────────────────────────────────────────────

it('deletes a journey and its steps', function (): void {
    $journey = ScanJourney::factory()->create([
        'agency_id' => $this->agency->id,
        'organization_id' => $this->organization->id,
        'property_id' => $this->property->id,
    ]);
    ScanJourneyStep::factory()->create(['scan_journey_id' => $journey->id, 'position' => 0]);

    $this->delete(route('journeys.destroy', $journey))
        ->assertRedirect(route('journeys.index'));

    expect(ScanJourney::query()->find($journey->id))->toBeNull();
    expect(ScanJourneyStep::query()->where('scan_journey_id', $journey->id)->count())->toBe(0);
});

it('returns 403 when deleting a journey from another agency', function (): void {
    $otherJourney = ScanJourney::factory()->create();

    $this->delete(route('journeys.destroy', $otherJourney))->assertNotFound();
});
