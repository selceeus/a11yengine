<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Integrations\IntegrationProviderRegistry;
use App\Enums\IntegrationProvider;
use App\Enums\IntegrationStatus;
use App\Http\Controllers\Controller;
use App\Models\Integration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationController extends Controller
{
    public function index(): Response
    {
        $integrations = Integration::query()
            ->with('property:id,name')
            ->latest()
            ->get()
            ->map(fn (Integration $integration) => [
                'id' => $integration->id,
                'provider' => $integration->provider->value,
                'provider_label' => $integration->provider->label(),
                'name' => $integration->name,
                'status' => $integration->status->value,
                'status_label' => $integration->status->label(),
                'error_message' => $integration->error_message,
                'last_synced_at' => $integration->last_synced_at?->toIso8601String(),
                'property' => $integration->property ? [
                    'id' => $integration->property->id,
                    'name' => $integration->property->name,
                ] : null,
            ]);

        $allProviders = collect(IntegrationProvider::cases())->map(fn (IntegrationProvider $provider) => [
            'value' => $provider->value,
            'label' => $provider->label(),
            'is_implemented' => $provider->isImplemented(),
            'credential_fields' => $provider->credentialFields(),
            'supports_webhooks' => $provider->supportsWebhooks(),
        ]);

        return Inertia::render('settings/integrations', [
            'integrations' => $integrations,
            'providers' => $allProviders,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'provider' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'credentials' => ['required', 'array'],
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
        ]);

        $provider = IntegrationProvider::from($request->input('provider'));

        $agency = app('currentAgency');

        Integration::create([
            'agency_id' => $agency->id,
            'property_id' => $request->input('property_id'),
            'provider' => $provider,
            'name' => $request->input('name'),
            'credentials' => $request->input('credentials'),
            'status' => IntegrationStatus::Active,
        ]);

        return redirect()->route('integrations.index');
    }

    public function destroy(Integration $integration): RedirectResponse
    {
        $integration->delete();

        return redirect()->route('integrations.index');
    }

    public function show(Integration $integration): Response
    {
        $integration->load('property:id,name');

        return Inertia::render('settings/integration-show', [
            'integration' => [
                'id' => $integration->id,
                'provider' => $integration->provider->value,
                'provider_label' => $integration->provider->label(),
                'name' => $integration->name,
                'status' => $integration->status->value,
                'status_label' => $integration->status->label(),
                'error_message' => $integration->error_message,
                'last_synced_at' => $integration->last_synced_at?->toIso8601String(),
                'property' => $integration->property ? [
                    'id' => $integration->property->id,
                    'name' => $integration->property->name,
                ] : null,
            ],
        ]);
    }

    public function test(Integration $integration): JsonResponse
    {
        $provider = IntegrationProviderRegistry::make($integration->provider);

        $result = $provider->testConnection($integration);

        return response()->json($result);
    }
}
