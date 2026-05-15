<?php

namespace App\Domain\Risk;

use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Models\Organization;
use Illuminate\Support\Facades\Date;

class CalculateOrganizationRiskScore
{
    public function handle(Organization|int $organization): int
    {
        $organizationId = $organization instanceof Organization
            ? $organization->id
            : $organization;

        return (int) Issue::query()
            ->where('organization_id', $organizationId)
            ->whereIn('status', IssueStatus::activeStatusValues())
            ->selectRaw('SUM(risk_weight * occurrence_count) as score')
            ->value('score');
    }

    public function openIssueCount(int $organizationId): int
    {
        return Issue::query()
            ->where('organization_id', $organizationId)
            ->whereIn('status', IssueStatus::activeStatusValues())
            ->count();
    }

    /**
     * @return array{under_30_days: int, 30_to_60_days: int, over_60_days: int}
     */
    public function agingBuckets(int $organizationId): array
    {
        $now = Date::now();

        $base = Issue::query()
            ->where('organization_id', $organizationId)
            ->whereIn('status', IssueStatus::activeStatusValues());

        return [
            'under_30_days' => (clone $base)
                ->where('first_detected_at', '>=', $now->copy()->subDays(30))
                ->count(),
            '30_to_60_days' => (clone $base)
                ->whereBetween('first_detected_at', [$now->copy()->subDays(60), $now->copy()->subDays(30)])
                ->count(),
            'over_60_days' => (clone $base)
                ->where('first_detected_at', '<', $now->copy()->subDays(60))
                ->count(),
        ];
    }
}
