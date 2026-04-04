<?php

use App\Enums\NotificationEmailCategory;
use App\Models\Agency;
use App\Models\NotificationEmailRoute;
use App\Models\User;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->user = User::factory()->create(['agency_id' => $this->agency->id]);
    $this->actingAs($this->user);
});

it('renders the notification email routes settings page', function (): void {
    $this->get(route('notification-email-routes.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('settings/notification-email-routes'));
});

it('passes routes and categories to the page', function (): void {
    NotificationEmailRoute::factory()->count(2)->create(['agency_id' => $this->agency->id]);

    $this->get(route('notification-email-routes.index'))
        ->assertInertia(fn ($page) => $page
            ->has('routes', 2)
            ->has('categories', 4)
        );
});

it('does not expose routes from another agency', function (): void {
    NotificationEmailRoute::factory()->count(3)->create();

    $this->get(route('notification-email-routes.index'))
        ->assertInertia(fn ($page) => $page->has('routes', 0));
});

it('stores a new email route', function (): void {
    $this->post(route('notification-email-routes.store'), [
        'category' => NotificationEmailCategory::Scans->value,
        'email' => 'notify@example.com',
        'label' => 'Test Route',
    ])
        ->assertRedirect(route('notification-email-routes.index'));

    $this->assertDatabaseHas('notification_email_routes', [
        'agency_id' => $this->agency->id,
        'category' => NotificationEmailCategory::Scans->value,
        'email' => 'notify@example.com',
        'label' => 'Test Route',
    ]);
});

it('rejects a duplicate email and category for the same agency', function (): void {
    $payload = [
        'category' => NotificationEmailCategory::Scans->value,
        'email' => 'notify@example.com',
        'label' => 'Test Route',
    ];

    $this->post(route('notification-email-routes.store'), $payload)
        ->assertRedirect(route('notification-email-routes.index'));

    $this->post(route('notification-email-routes.store'), $payload)
        ->assertRedirect(route('notification-email-routes.index'));

    $this->assertDatabaseCount('notification_email_routes', 1);
});

it('rejects an invalid email address', function (): void {
    $this->post(route('notification-email-routes.store'), [
        'category' => NotificationEmailCategory::Scans->value,
        'email' => 'not-valid',
        'label' => 'Test Route',
    ])
        ->assertRedirect()
        ->assertSessionHasErrors(['email']);
});

it('rejects an invalid category', function (): void {
    $this->post(route('notification-email-routes.store'), [
        'category' => 'invalid_category',
        'email' => 'notify@example.com',
        'label' => 'Test Route',
    ])
        ->assertRedirect()
        ->assertSessionHasErrors(['category']);
});

it('destroys a route', function (): void {
    $route = NotificationEmailRoute::factory()->create(['agency_id' => $this->agency->id]);

    $this->delete(route('notification-email-routes.destroy', $route))
        ->assertRedirect(route('notification-email-routes.index'));

    $this->assertDatabaseMissing('notification_email_routes', ['id' => $route->id]);
});

it('cannot destroy a route belonging to another agency', function (): void {
    $otherAgency = Agency::factory()->create();
    $route = NotificationEmailRoute::factory()->create(['agency_id' => $otherAgency->id]);

    $this->delete(route('notification-email-routes.destroy', $route))
        ->assertNotFound();
});
