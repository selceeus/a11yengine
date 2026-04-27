<?php

namespace App\Http\Middleware;

use App\Enums\ApiKeyScope;
use App\Models\Agency;
use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates MCP clients. Checks the api_keys table first (scope: mcp),
 * then falls back to the legacy agencies.mcp_token column for backwards
 * compatibility with existing integrations.
 *
 * Usage in routes/ai.php:
 *   Mcp::web('/mcp/property-accessibility', PropertyAccessibilityServer::class)
 *       ->middleware('mcp.auth');
 */
class VerifyMcpToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        abort_unless(filled($token), 401, 'MCP token required.');

        // Check api_keys table first.
        $hash = hash('sha256', $token);

        $apiKey = ApiKey::query()
            ->where('token_hash', $hash)
            ->with('agency')
            ->first();

        if ($apiKey !== null && $apiKey->isActive() && $apiKey->hasScope(ApiKeyScope::Mcp)) {
            $apiKey->forceFill(['last_used_at' => now()])->saveQuietly();

            app()->instance(Agency::class, $apiKey->agency);

            return $next($request);
        }

        // Legacy fallback: agencies.mcp_token column.
        $agency = Agency::query()->where('mcp_token', $token)->first();

        abort_unless($agency !== null, 401, 'Invalid MCP token.');

        app()->instance(Agency::class, $agency);

        return $next($request);
    }
}
