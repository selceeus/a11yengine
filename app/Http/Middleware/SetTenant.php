<?php

namespace App\Http\Middleware;

use App\Models\Agency;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the current tenant (Agency) from the request and binds it into
 * the service container, making it available everywhere within the request.
 *
 * Resolution order:
 *   1. {tenant} route parameter  (slug)  — e.g. /api/{tenant}/...
 *   2. X-Tenant request header   (slug)  — e.g. X-Tenant: acme
 *
 * Throws HTTP 404 if the slug is missing or does not match a known Agency.
 *
 * -----------------------------------------------------------------------
 * USAGE — routes
 * -----------------------------------------------------------------------
 *
 *   // routes/api.php
 *   Route::prefix('{tenant}')
 *       ->middleware('tenant')
 *       ->group(function () {
 *           Route::get('organizations', [OrganizationController::class, 'index']);
 *       });
 *
 *   // Or via header (no route prefix needed):
 *   Route::middleware('tenant')->group(function () {
 *       Route::get('organizations', [OrganizationController::class, 'index']);
 *   });
 *
 * -----------------------------------------------------------------------
 * USAGE — controllers
 * -----------------------------------------------------------------------
 *
 *   // Inject the bound Agency model directly:
 *   public function index(Agency $agency): JsonResponse
 *   {
 *       // $agency is automatically the current tenant
 *       $organizations = $agency->organizations()->get();
 *   }
 *
 *   // Or resolve from the container:
 *   $agency = app('currentAgency'); // App\Models\Agency instance
 *
 * -----------------------------------------------------------------------
 * USAGE — Eloquent scoping
 * -----------------------------------------------------------------------
 *   Once the tenant is bound, TenantScope (which gates on auth()->user()->agency_id)
 *   continues to work as before.  For un-authenticated API routes you can scope
 *   queries directly via the bound agency:
 *
 *   Organization::query()->where('agency_id', app(Agency::class)->id)->get();
 */
class SetTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('tenant') ?? $request->header('X-Tenant');

        abort_unless(filled($slug), 404);

        $agency = Agency::query()->where('slug', $slug)->first();

        abort_unless($agency !== null, 404);

        // Bind under both the model class (for constructor injection) and a
        // string key (for explicit container lookups: app('currentAgency')).
        app()->instance(Agency::class, $agency);
        app()->instance('currentAgency', $agency);

        return $next($request);
    }
}
