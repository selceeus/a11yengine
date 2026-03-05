<?php

use App\Models\Agency;
use App\Models\Organization;
use App\Models\User;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->organization = Organization::factory()->create(['agency_id' => $this->agency->id]);

    $this->user = User::factory()->create(['agency_id' => $this->agency->id]);
    $this->actingAs($this->user);
});

// ─── index ──────────────────────────────────────────────────────────────────

it('returns the organizations index page', function (): void {
    $this->get(route('organizations.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('organizations/index'));
});

it('passes organizations belonging to the agency', function (): void {
    $this->get(route('organizations.index'))
        ->assertInertia(fn ($page) => $page->has('organizations', 1));
});

it('does not expose organizations from another agency on the index', function (): void {
    Organization::factory()->create();

    $this->get(route('organizations.index'))
        ->assertInertia(fn ($page) => $page->has('organizations', 1));
});

it('redirects unauthenticated users from the index', function (): void {
    $this->post('/logout');

    $this->get(route('organizations.index'))->assertRedirect(route('login'));
});

// ─── create ─────────────────────────────────────────────────────────────────

it('returns the create organization page', function (): void {
    $this->get(route('organizations.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('organizations/create'));
});

// ─── store ───────────────────────────────────────────────────────────────────

it('creates an organization with the correct data', function (): void {
    $this->post(route('organizations.store'), [
        'name' => 'Acme Corp',
        'domain' => 'acme.com',
    ])->assertRedirect();

    $organization = Organization::withoutGlobalScopes()->where('name', 'Acme Corp')->first();

    expect($organization)->not->toBeNull()
        ->and($organization->agency_id)->toBe($this->agency->id)
        ->and($organization->domain)->toBe('acme.com')
        ->and($organization->status)->toBe('active');
});

it('allows storing an organization without a domain', function (): void {
    $this->post(route('organizations.store'), [
        'name' => 'No Domain Org',
    ])->assertRedirect();

    expect(Organization::withoutGlobalScopes()->where('name', 'No Domain Org')->first())
        ->not->toBeNull()
        ->domain->toBeNull();
});

it('redirects to the new organization show page after store', function (): void {
    $this->post(route('organizations.store'), [
        'name' => 'Redirect Org',
    ]);

    $organization = Organization::withoutGlobalScopes()->where('name', 'Redirect Org')->first();

    $this->post(route('organizations.store'), [
        'name' => 'Second Redirect Org',
    ])->assertRedirect(route('organizations.show', Organization::withoutGlobalScopes()->where('name', 'Second Redirect Org')->first() ?? $organization));
});

it('returns validation errors when name is missing on store', function (): void {
    $this->post(route('organizations.store'), [])
        ->assertSessionHasErrors('name');
});

// ─── show ─────────────────────────────────────────────────────────────────────

it('returns the organization show page', function (): void {
    $this->get(route('organizations.show', $this->organization))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('organizations/show'));
});

it('passes the organization and its properties to the show page', function (): void {
    $this->get(route('organizations.show', $this->organization))
        ->assertInertia(fn ($page) => $page
            ->has('organization')
            ->where('organization.id', $this->organization->id)
            ->has('organization.properties')
        );
});

it('returns 404 when viewing an organization from another agency', function (): void {
    $otherOrg = Organization::factory()->create();

    $this->get(route('organizations.show', $otherOrg))->assertNotFound();
});

it('redirects unauthenticated users from show', function (): void {
    $this->post('/logout');

    $this->get(route('organizations.show', $this->organization))->assertRedirect(route('login'));
});

// ─── edit ─────────────────────────────────────────────────────────────────────

it('returns the edit organization page', function (): void {
    $this->get(route('organizations.edit', $this->organization))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('organizations/edit'));
});

it('returns 404 when editing an organization from another agency', function (): void {
    $otherOrg = Organization::factory()->create();

    $this->get(route('organizations.edit', $otherOrg))->assertNotFound();
});

// ─── update ───────────────────────────────────────────────────────────────────

it('updates the organization', function (): void {
    $this->patch(route('organizations.update', $this->organization), [
        'name' => 'Updated Name',
        'domain' => 'updated.com',
        'status' => 'inactive',
    ])->assertRedirect(route('organizations.show', $this->organization));

    expect($this->organization->fresh())
        ->name->toBe('Updated Name')
        ->domain->toBe('updated.com')
        ->status->toBe('inactive');
});

it('allows clearing the domain on update', function (): void {
    $this->patch(route('organizations.update', $this->organization), [
        'name' => $this->organization->name,
        'domain' => null,
        'status' => 'active',
    ]);

    expect($this->organization->fresh()->domain)->toBeNull();
});

it('returns validation errors on update when name is missing', function (): void {
    $this->patch(route('organizations.update', $this->organization), ['status' => 'active'])
        ->assertSessionHasErrors('name');
});

it('returns 404 when updating an organization from another agency', function (): void {
    $otherOrg = Organization::factory()->create();

    $this->patch(route('organizations.update', $otherOrg), [
        'name' => 'Hacked',
        'status' => 'active',
    ])->assertNotFound();
});

// ─── destroy ──────────────────────────────────────────────────────────────────

it('deletes the organization and redirects to index', function (): void {
    $this->delete(route('organizations.destroy', $this->organization))
        ->assertRedirect(route('organizations.index'));

    expect(Organization::withoutGlobalScopes()->find($this->organization->id))->toBeNull();
});

it('returns 404 when deleting an organization from another agency', function (): void {
    $otherOrg = Organization::factory()->create();

    $this->delete(route('organizations.destroy', $otherOrg))->assertNotFound();
});
