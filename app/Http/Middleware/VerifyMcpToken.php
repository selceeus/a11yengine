<?php

namespace App\Http\Middleware;

use App\Models\Agency;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates MCP clients via a per-agency Bearer token stored in
 * agencies.mcp_token. On success the Agency is bound to the service
 * container — mirroring the SetTenant middleware pattern — so that all
 * MCP tools and resources can resolve it via dependency injection.
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

        $agency = Agency::query()->where('mcp_token', $token)->first();

        abort_unless($agency !== null, 401, 'Invalid MCP token.');

        app()->instance(Agency::class, $agency);
        app()->instance('currentAgency', $agency);

        return $next($request);
    }
}
