<?php

use App\Models\Agency;

/**
 * Resolve the current tenant Agency from the service container.
 *
 * Both the SetCurrentAgency and SetTenant middleware bind the resolved Agency
 * under Agency::class. This helper provides a single, intention-revealing
 * call site for retrieving that binding throughout the application.
 *
 * Returns null when no tenant has been bound (e.g. unauthenticated routes
 * that skip both middlewares, or during console commands).
 */
function currentAgency(): ?Agency
{
    if (app()->bound(Agency::class)) {
        return app(Agency::class);
    }

    return null;
}
