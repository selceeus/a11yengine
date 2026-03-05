<?php

use App\Models\Agency;
use App\Models\Organization;
use App\Models\Property;
use App\Models\User;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);
    $this->property = Property::factory()
        ->for($this->agency)
        ->for($this->organization)
        ->create();

    $this->user = User::factory()->create(['agency_id' => $this->agency->id]);
    $this->actingAs($this->user);
});

// ─── index ──────────────────────────────────────────────────────────────────

it('returns the properties index page', function (): void {
    $this->get(route('properties.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('properties/index'));
});

it('passes properties belonging to the agency', function (): void {
    $this->get(route('properties.index'))
        ->assertInertia(fn ($page) => $page->has('properties', 1));
});

it('does not expose properties from another agency on the index', function (): void {
    Property::factory()->create();

    $this->get(route('properties.index'))
        ->assertInertia(fn ($page) => $page->has('properties', 1));
});

it('redirects unauthenticated users from the index', function (): void {
    $this->post('/logout');

    $this->get(route('properties.index'))->assertRedirect(route('login'));
});

// ─── create ─────────────────────────────────────────────────────────────────

it('returns the create property page', function (): void {
    $this->get(route('properties.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('properties/create'));
});

it('passes the agency organizations to the create page', function (): void {
    $this->get(route('properties.create'))
        ->assertInertia(fn ($page) => $page->has('organizations', 1));
});

// ─── store ───────────────────────────────────────────────────────────────────

it('creates a property with the correct data', function (): void {
    $this->post(route('properties.store'), [
        'organization_id' => $this->organization->id,
        'name' => 'Test Site',
        'base_url' => 'https://test.example.com',
    ])->assertRedirect();

    $property = Property::query()->where('name', 'Test Site')->first();

    expect($property)->not->toBeNull()
        ->and($property->agency_id)->toBe($this->agency->id)
        ->and($property->organization_id)->toBe($this->organization->id)
        ->and($property->base_url)->toBe('https://test.example.com');
});

it('redirects to the new property after store', function (): void {
    $this->post(route('properties.store'), [
        'organization_id' => $this->organization->id,
        'name' => 'Test Site',
        'base_url' => 'https://test.example.com',
    ]);

    $property = Property::query()->where('name', 'Test Site')->first();

    $this->get(route('properties.index'));

    $this->post(route('properties.store'), [
        'organization_id' => $this->organization->id,
        'name' => 'Redirect Site',
        'base_url' => 'https://redirect.example.com',
    ])->assertRedirect(route('properties.show', Property::query()->where('name', 'Redirect Site')->first() ?? $property));
});

it('returns validation errors when required fields are missing on store', function (): void {
    $this->post(route('properties.store'), [])
        ->assertSessionHasErrors(['organization_id', 'name', 'base_url']);
});

it('returns a validation error when storing with an organization from another agency', function (): void {
    $otherOrg = Organization::factory()->create();

    $this->post(route('properties.store'), [
        'organization_id' => $otherOrg->id,
        'name' => 'Bad',
        'base_url' => 'https://bad.example.com',
    ])->assertSessionHasErrors('organization_id');
});

it('returns a validation error when base_url is not a valid URL', function (): void {
    $this->post(route('properties.store'), [
        'organization_id' => $this->organization->id,
        'name' => 'Test',
        'base_url' => 'not-a-url',
    ])->assertSessionHasErrors('base_url');
});

// ─── show ─────────────────────────────────────────────────────────────────────

it('returns the property show page', function (): void {
    $this->get(route('properties.show', $this->property))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('properties/show'));
});

it('passes the property and recent scans to the show page', function (): void {
    $this->get(route('properties.show', $this->property))
        ->assertInertia(fn ($page) => $page
            ->has('property')
            ->where('property.id', $this->property->id)
            ->has('recentScans')
        );
});

it('returns 404 when viewing a property from another agency', function (): void {
    $otherProperty = Property::factory()->create();

    $this->get(route('properties.show', $otherProperty))->assertNotFound();
});

it('redirects unauthenticated users from show', function (): void {
    $this->post('/logout');

    $this->get(route('properties.show', $this->property))->assertRedirect(route('login'));
});

// ─── edit ─────────────────────────────────────────────────────────────────────

it('returns the edit property page', function (): void {
    $this->get(route('properties.edit', $this->property))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('properties/edit'));
});

it('returns 404 when editing a property from another agency', function (): void {
    $otherProperty = Property::factory()->create();

    $this->get(route('properties.edit', $otherProperty))->assertNotFound();
});

// ─── update ───────────────────────────────────────────────────────────────────

it('updates the property', function (): void {
    $this->patch(route('properties.update', $this->property), [
        'name' => 'Updated Name',
        'base_url' => 'https://updated.example.com',
    ])->assertRedirect(route('properties.show', $this->property));

    expect($this->property->fresh())
        ->name->toBe('Updated Name')
        ->base_url->toBe('https://updated.example.com');
});

it('returns validation errors on update when fields are missing', function (): void {
    $this->patch(route('properties.update', $this->property), [])
        ->assertSessionHasErrors(['name', 'base_url']);
});

it('returns 404 when updating a property from another agency', function (): void {
    $otherProperty = Property::factory()->create();

    $this->patch(route('properties.update', $otherProperty), [
        'name' => 'Hacked',
        'base_url' => 'https://hacked.example.com',
    ])->assertNotFound();
});

// ─── destroy ──────────────────────────────────────────────────────────────────

it('deletes the property and redirects to index', function (): void {
    $this->delete(route('properties.destroy', $this->property))
        ->assertRedirect(route('properties.index'));

    expect(Property::withoutGlobalScopes()->find($this->property->id))->toBeNull();
});

it('returns 404 when deleting a property from another agency', function (): void {
    $otherProperty = Property::factory()->create();

    $this->delete(route('properties.destroy', $otherProperty))->assertNotFound();
});
