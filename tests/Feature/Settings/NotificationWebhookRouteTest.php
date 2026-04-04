<?php

use App\Enums\MessagingPlatform;
use App\Enums\NotificationEmailCategory;
use App\Models\Agency;
use App\Models\NotificationWebhookRoute;
use App\Models\User;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->user = User::factory()->create(['agency_id' => $this->agency->id]);
    $this->actingAs($this->user);
});

it('renders the notification webhook routes settings page', function (): void {
    $this->get(route('notification-webhook-routes.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('settings/notification-webhook-routes'));
});

it('passes routes and categories and platforms to the page', function (): void {
    NotificationWebhookRoute::factory()->count(2)->create(['agency_id' => $this->agency->id]);

    $this->get(route('notification-webhook-routes.index'))
        ->assertInertia(fn ($page) => $page
            ->has('routes', 2)
            ->has('categories', 4)
            ->has('platforms', 3)
        );
});

it('does not expose routes from another agency', function (): void {
    NotificationWebhookRoute::factory()->count(3)->create();

    $this->get(route('notification-webhook-routes.index'))
        ->assertInertia(fn ($page) => $page->has('routes', 0));
});

it('stores a new webhook route', function (): void {
    $this->post(route('notification-webhook-routes.store'), [
        'category' => NotificationEmailCategory::Scans->value,
        'platform' => MessagingPlatform::Slack->value,
        'webhook_url' => 'https://hooks.slack.com/services/T00000000/B00000000/XXXXXXXXXXXXXXXXXXXXXXXX',
        'label' => 'Test Webhook',
    ])
        ->assertRedirect(route('notification-webhook-routes.index'));

    $this->assertDatabaseHas('notification_webhook_routes', [
        'agency_id' => $this->agency->id,
        'category' => NotificationEmailCategory::Scans->value,
        'platform' => MessagingPlatform::Slack->value,
    ]);
});

it('rejects an invalid webhook url', function (): void {
    $this->post(route('notification-webhook-routes.store'), [
        'category' => NotificationEmailCategory::Scans->value,
        'platform' => MessagingPlatform::Slack->value,
        'webhook_url' => 'not-a-url',
        'label' => 'Test Webhook',
    ])
        ->assertRedirect()
        ->assertSessionHasErrors(['webhook_url']);
});

it('rejects an invalid platform', function (): void {
    $this->post(route('notification-webhook-routes.store'), [
        'category' => NotificationEmailCategory::Scans->value,
        'platform' => 'twitter',
        'webhook_url' => 'https://hooks.slack.com/services/T00000000/B00000000/XXXXXXXXXXXXXXXXXXXXXXXX',
        'label' => 'Test Webhook',
    ])
        ->assertRedirect()
        ->assertSessionHasErrors(['platform']);
});

it('rejects an invalid category', function (): void {
    $this->post(route('notification-webhook-routes.store'), [
        'category' => 'invalid',
        'platform' => MessagingPlatform::Slack->value,
        'webhook_url' => 'https://hooks.slack.com/services/T00000000/B00000000/XXXXXXXXXXXXXXXXXXXXXXXX',
        'label' => 'Test Webhook',
    ])
        ->assertRedirect()
        ->assertSessionHasErrors(['category']);
});

it('destroys a webhook route', function (): void {
    $route = NotificationWebhookRoute::factory()->create(['agency_id' => $this->agency->id]);

    $this->delete(route('notification-webhook-routes.destroy', $route))
        ->assertRedirect(route('notification-webhook-routes.index'));

    $this->assertDatabaseMissing('notification_webhook_routes', ['id' => $route->id]);
});

it('cannot destroy a webhook route belonging to another agency', function (): void {
    $otherAgency = Agency::factory()->create();
    $route = NotificationWebhookRoute::factory()->create(['agency_id' => $otherAgency->id]);

    $this->delete(route('notification-webhook-routes.destroy', $route))
        ->assertNotFound();
});
