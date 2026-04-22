<?php

use App\Enums\IntegrationProvider;
use App\Enums\IntegrationStatus;
use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\Integration;
use App\Models\User;
use Illuminate\Support\Facades\Http;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->agency = Agency::factory()->create();
    $this->actor = User::factory()->withRole(UserRole::AgencyAdmin, agencyId: $this->agency->id)->create([
        'agency_id' => $this->agency->id,
    ]);
});

// ── Wrike webhook registration on store ───────────────────────────────────────

it('registers a Wrike webhook when a Wrike integration is created', function (): void {
    Http::fake([
        'www.wrike.com/api/v4/webhooks' => Http::response([
            'data' => [[
                'id' => 'WEBHOOK_XYZ',
                'secretKey' => 'generated-secret',
            ]],
        ], 200),
    ]);

    $this->actingAs($this->actor)
        ->post(route('integrations.store'), [
            'provider' => 'wrike',
            'name' => 'My Wrike Integration',
            'credentials' => [
                'access_token' => 'wrike-token',
                'folder_id' => 'FOLDER123',
            ],
        ])
        ->assertRedirect(route('integrations.index'));

    $integration = Integration::withoutGlobalScopes()
        ->where('agency_id', $this->agency->id)
        ->where('provider', IntegrationProvider::Wrike)
        ->first();

    expect($integration)->not->toBeNull()
        ->and($integration->settings['wrike_webhook_id'])->toBe('WEBHOOK_XYZ')
        ->and($integration->settings['webhook_secret'])->toBe('generated-secret');

    Http::assertSentCount(1);
});

it('does not call the Wrike webhook API when creating a non-Wrike integration', function (): void {
    Http::fake();

    $this->actingAs($this->actor)
        ->post(route('integrations.store'), [
            'provider' => 'monday',
            'name' => 'My Monday Integration',
            'credentials' => [
                'api_token' => 'monday-token',
                'board_id' => '123',
            ],
        ])
        ->assertRedirect(route('integrations.index'));

    Http::assertNothingSent();
});

// ── Wrike webhook cleanup on destroy ──────────────────────────────────────────

it('deletes the Wrike webhook when the integration is removed', function (): void {
    Http::fake([
        'www.wrike.com/api/v4/webhooks/WEBHOOK_XYZ' => Http::response([], 200),
    ]);

    $integration = Integration::withoutGlobalScopes()->create([
        'agency_id' => $this->agency->id,
        'provider' => IntegrationProvider::Wrike,
        'name' => 'Wrike to Delete',
        'credentials' => ['access_token' => 'wrike-token', 'folder_id' => 'FOLDER123'],
        'settings' => ['wrike_webhook_id' => 'WEBHOOK_XYZ', 'webhook_secret' => 'some-secret'],
        'status' => IntegrationStatus::Active,
    ]);

    $this->actingAs($this->actor)
        ->delete(route('integrations.destroy', $integration))
        ->assertRedirect(route('integrations.index'));

    expect(Integration::withoutGlobalScopes()->find($integration->id))->toBeNull();
    Http::assertSentCount(1);
});
