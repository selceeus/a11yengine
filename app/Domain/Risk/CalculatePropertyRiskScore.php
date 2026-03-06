<?php

namespace App\Domain\Risk;

use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Models\Property;
use App\Models\Scopes\TenantScope;

class CalculatePropertyRiskScore
{
    /**
     * @return array{risk_score: int, open_issue_count: int}
     */
    public function handle(Property|int $property): array
    {
        $propertyId = $property instanceof Property ? $property->id : $property;

        $activeStatuses = array_map(
            fn (IssueStatus $s) => $s->value,
            IssueStatus::activeStatuses()
        );

        $row = Issue::withoutGlobalScope(TenantScope::class)
            ->where('property_id', $propertyId)
            ->whereIn('status', $activeStatuses)
            ->selectRaw('COUNT(*) as open_issue_count, COALESCE(SUM(risk_weight * occurrence_count), 0) as risk_score')
            ->first();

        return [
            'risk_score' => (int) $row->risk_score,
            'open_issue_count' => (int) $row->open_issue_count,
        ];
    }
}
