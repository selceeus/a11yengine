<?php

namespace App\Http\Middleware;

use App\Models\Agency;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentAgency
{
    public function handle(Request $request, Closure $next): Response
    {

        $agencyId = $request->user()?->agency_id;

        if (! $agencyId) {
            return $next($request);
        }

        $agency = Agency::query()
            ->where('id', $agencyId)
            ->firstOrFail();

        app()->instance(Agency::class, $agency);
        app()->instance('currentAgency', $agency);

        return $next($request);
    }
}
