<?php

namespace App\Domain\Audits;

use App\Enums\IssueStatus;
use App\Enums\ScanStatus;
use App\Models\Finding;
use App\Models\LighthouseResult;
use App\Models\Property;
use App\Models\Scan;
use App\Models\ScanPage;

class GatherAuditContext
{
    /**
     * Gather all relevant data for a property to build an AI audit prompt.
     *
     * @param  array<int, int>  $scanIds  Specific scan IDs to use; if empty, loads the last 3 completed scans.
     * @return array<string, mixed>
     */
    public function handle(int $propertyId, array $scanIds = []): array
    {
        $property = Property::withoutGlobalScopes()->findOrFail($propertyId);

        $scanQuery = Scan::withoutGlobalScopes()
            ->where('property_id', $propertyId)
            ->where('status', ScanStatus::Completed);

        if (! empty($scanIds)) {
            $scanQuery->whereIn('id', $scanIds);
        } else {
            $scanQuery->latest('completed_at')->limit(3);
        }

        $scans = $scanQuery->get();

        $maxIssues = config('ai.audit.max_issues_in_prompt', 30);

        $issues = \App\Models\Issue::withoutGlobalScopes()
            ->where('property_id', $propertyId)
            ->whereIn('status', array_map(
                fn (IssueStatus $s) => $s->value,
                IssueStatus::activeStatuses()
            ))
            ->orderByDesc('risk_weight')
            ->limit($maxIssues)
            ->get(['rule_key', 'severity', 'wcag_category', 'wcag_criteria', 'description', 'occurrence_count', 'risk_weight', 'help_url']);

        return [
            'property' => $property->only(['id', 'name', 'base_url']),
            'scans' => $scans->map(fn (Scan $s) => $s->only(['id', 'pages_scanned', 'total_violations', 'completed_at'])),
            'issues' => $issues->toArray(),
            'severity_breakdown' => $this->buildSeverityBreakdown($propertyId),
            'top_pages' => $this->buildTopPages($scans->pluck('id')->all()),
            'lighthouse' => $this->buildLighthouseAverages($scans->pluck('id')->all()),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function buildSeverityBreakdown(int $propertyId): array
    {
        $rows = Finding::withoutGlobalScopes()
            ->where('property_id', $propertyId)
            ->selectRaw('severity, COUNT(*) as total')
            ->groupBy('severity')
            ->pluck('total', 'severity')
            ->all();

        arsort($rows);

        return $rows;
    }

    /**
     * @param  array<int, int>  $scanIds
     * @return array<int, array<string, mixed>>
     */
    private function buildTopPages(array $scanIds): array
    {
        if (empty($scanIds)) {
            return [];
        }

        return ScanPage::withoutGlobalScopes()
            ->whereIn('scan_id', $scanIds)
            ->orderByDesc('violations_count')
            ->limit(10)
            ->get(['url', 'violations_count'])
            ->toArray();
    }

    /**
     * @param  array<int, int>  $scanIds
     * @return array<string, float|null>
     */
    private function buildLighthouseAverages(array $scanIds): array
    {
        if (empty($scanIds)) {
            return [
                'performance' => null,
                'accessibility' => null,
                'best_practices' => null,
                'seo' => null,
            ];
        }

        $row = LighthouseResult::withoutGlobalScopes()
            ->whereIn('scan_id', $scanIds)
            ->selectRaw('AVG(performance_score) as performance, AVG(accessibility_score) as accessibility, AVG(best_practices_score) as best_practices, AVG(seo_score) as seo')
            ->first();

        return [
            'performance' => $row?->performance !== null ? round((float) $row->performance, 1) : null,
            'accessibility' => $row?->accessibility !== null ? round((float) $row->accessibility, 1) : null,
            'best_practices' => $row?->best_practices !== null ? round((float) $row->best_practices, 1) : null,
            'seo' => $row?->seo !== null ? round((float) $row->seo, 1) : null,
        ];
    }
}
