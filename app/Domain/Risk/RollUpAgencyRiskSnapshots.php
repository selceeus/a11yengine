<?php

namespace App\Domain\Risk;

use App\Models\Agency;
use App\Models\Property;
use App\Models\PropertyRiskSnapshot;
use App\Models\Scopes\TenantScope;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class RollUpAgencyRiskSnapshots
{
    /**
     * Aggregate the most-recent property snapshot for each property in the
     * agency as of the given date, returning a summary keyed by property.
     *
     * @return array{
     *     agency_id: int,
     *     snapshot_date: string,
     *     total_risk_score: int,
     *     total_open_issue_count: int,
     *     property_count: int,
     *     properties: Collection<int, array{property_id: int, risk_score: int, open_issue_count: int}>
     * }
     */
    public function handle(Agency|int $agency, ?CarbonInterface $asOf = null): array
    {
        $agencyId = $agency instanceof Agency ? $agency->id : $agency;
        $asOf ??= now();

        $propertyIds = Property::withoutGlobalScope(TenantScope::class)
            ->where('agency_id', $agencyId)
            ->pluck('id');

        $latestSnapshotIdPerProperty = PropertyRiskSnapshot::query()
            ->whereIn('property_id', $propertyIds)
            ->where('snapshot_date', '<=', $asOf->toDateString())
            ->selectRaw('MAX(id) as id')
            ->groupBy('property_id')
            ->pluck('id');

        $snapshots = PropertyRiskSnapshot::query()
            ->whereIn('id', $latestSnapshotIdPerProperty)
            ->get();

        $properties = $snapshots->map(fn (PropertyRiskSnapshot $s): array => [
            'property_id' => $s->property_id,
            'risk_score' => $s->risk_score,
            'open_issue_count' => $s->open_issue_count,
        ])->values();

        return [
            'agency_id' => $agencyId,
            'snapshot_date' => $asOf->toDateString(),
            'total_risk_score' => $snapshots->sum('risk_score'),
            'total_open_issue_count' => $snapshots->sum('open_issue_count'),
            'property_count' => $snapshots->count(),
            'properties' => $properties,
        ];
    }
}
