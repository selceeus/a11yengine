<?php

namespace App\Http\Controllers\Settings;

use App\Enums\ApiKeyScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreApiKeyRequest;
use App\Models\ApiKey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApiKeyController extends Controller
{
    public function index(Request $request): Response
    {
        $apiKeys = ApiKey::query()
            ->with('createdBy:id,name')
            ->latest()
            ->get()
            ->map(fn (ApiKey $key) => [
                'id' => $key->id,
                'name' => $key->name,
                'key_prefix' => $key->key_prefix,
                'scopes' => $key->scopes,
                'is_active' => $key->isActive(),
                'last_used_at' => $key->last_used_at?->toIso8601String(),
                'expires_at' => $key->expires_at?->toIso8601String(),
                'revoked_at' => $key->revoked_at?->toIso8601String(),
                'created_at' => $key->created_at?->toIso8601String(),
                'created_by' => $key->createdBy ? [
                    'id' => $key->createdBy->id,
                    'name' => $key->createdBy->name,
                ] : null,
            ]);

        $availableScopes = collect(ApiKeyScope::cases())->map(fn (ApiKeyScope $scope) => [
            'value' => $scope->value,
            'label' => $scope->label(),
            'description' => $scope->description(),
        ]);

        return Inertia::render('settings/api-keys', [
            'apiKeys' => $apiKeys,
            'availableScopes' => $availableScopes,
            'newToken' => $request->session()->get('newToken'),
        ]);
    }

    public function store(StoreApiKeyRequest $request): RedirectResponse
    {
        $agency = app('currentAgency');

        $token = ApiKey::generateToken();

        ApiKey::create([
            'agency_id' => $agency->id,
            'created_by' => $request->user()->id,
            'name' => $request->validated('name'),
            'key_prefix' => $token['prefix'],
            'token_hash' => $token['hash'],
            'scopes' => $request->validated('scopes'),
            'expires_at' => $request->validated('expires_at'),
        ]);

        return redirect()->route('api-keys.index')
            ->with('newToken', $token['plaintext']);
    }

    public function destroy(ApiKey $apiKey): RedirectResponse
    {
        $this->authorize('update', $apiKey->agency);

        $apiKey->forceFill(['revoked_at' => now()])->save();

        return redirect()->route('api-keys.index');
    }
}
