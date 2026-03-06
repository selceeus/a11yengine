<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();

        // Super Users have visibility across all tenants — do not apply filter.
        if ($user?->isSuperUser()) {
            return;
        }

        // Prefer the Agency bound to the container by SetCurrentAgency or
        // SetTenant middleware; fall back to the authenticated user's own
        // agency_id for backwards compatibility with session-based requests.
        $agencyId = currentAgency()?->id ?? $user?->agency_id;

        if ($agencyId === null) {
            return;
        }

        $builder->where($model->getTable().'.agency_id', $agencyId);
    }
}
