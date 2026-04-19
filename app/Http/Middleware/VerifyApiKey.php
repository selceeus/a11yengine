<?php

namespace App\Http\Middleware;

use App\Enums\ApiKeyScope;
use App\Models\Agency;
use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyApiKey
{
    public function handle(Request $request, Closure $next, string ...$scopes): Response
    {
        $token = $request->bearerToken();

        abort_unless(filled($token), 401, 'API key required.');

        $hash = hash('sha256', $token);

        $apiKey = ApiKey::query()
            ->where('token_hash', $hash)
            ->with('agency')
            ->first();

        abort_unless($apiKey !== null, 401, 'Invalid API key.');
        abort_unless($apiKey->isActive(), 401, 'API key has been revoked or expired.');

        foreach ($scopes as $scope) {
            $enumScope = ApiKeyScope::from($scope);
            abort_unless($apiKey->hasScope($enumScope), 403, "API key missing required scope: {$scope}.");
        }

        $apiKey->forceFill(['last_used_at' => now()])->saveQuietly();

        app()->instance(ApiKey::class, $apiKey);
        app()->instance(Agency::class, $apiKey->agency);
        app()->instance('currentAgency', $apiKey->agency);

        return $next($request);
    }
}
