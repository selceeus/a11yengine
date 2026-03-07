<?php

namespace App\Services;

use App\Domain\Scans\RecordScanMetrics;
use App\Enums\FindingSeverity;
use App\Enums\IssueSeverity;
use App\Models\Finding;
use App\Models\LighthouseResult;
use App\Models\Scan;
use App\Models\ScanMetric;

class CalculateScanMetrics
{
    public function __construct(private readonly RecordScanMetrics $recorder) {}

    public function handle(Scan $scan): void
    {
        $axeMetrics = $this->computeAxeMetrics($scan);
        $lighthouseMetrics = $this->computeLighthouseMetrics($scan);

        $this->recorder->record($scan, null, $axeMetrics, 'axe');

        if (! empty($lighthouseMetrics)) {
            $this->recorder->record($scan, null, $lighthouseMetrics, 'lighthouse');
        }
    }

    /**
     * @return array<string, int|float>
     */
    private function computeAxeMetrics(Scan $scan): array
    {
        $counts = Finding::withoutGlobalScopes()
            ->where('scan_id', $scan->id)
            ->selectRaw('severity, COUNT(*) as total')
            ->groupBy('severity')
            ->pluck('total', 'severity')
            ->all();

        $critical = (int) ($counts[FindingSeverity::CRITICAL->value] ?? 0);
        $serious = (int) ($counts[FindingSeverity::SERIOUS->value] ?? 0);
        $moderate = (int) ($counts[FindingSeverity::MODERATE->value] ?? 0);
        $minor = (int) ($counts[FindingSeverity::MINOR->value] ?? 0);

        $score = max(0.0, 100.0 - ($critical * 5.0 + $serious * 3.0 + $moderate * 1.0 + $minor * 0.5));

        $totalIssueCount = Finding::withoutGlobalScopes()
            ->where('scan_id', $scan->id)
            ->whereNotNull('issue_id')
            ->distinct('issue_id')
            ->count('issue_id');

        $criticalIssueCount = Finding::withoutGlobalScopes()
            ->where('scan_id', $scan->id)
            ->whereNotNull('issue_id')
            ->join('issues', 'issues.id', '=', 'findings.issue_id')
            ->where('issues.severity', IssueSeverity::Critical->value)
            ->distinct('findings.issue_id')
            ->count('findings.issue_id');

        $metrics = [
            'accessibility_risk_score' => $score,
            'total_issue_count' => $totalIssueCount,
            'critical_issue_count' => $criticalIssueCount,
        ];

        $riskTrend = $this->computeRiskTrend($scan, $score);

        if ($riskTrend !== null) {
            $metrics['risk_trend'] = $riskTrend;
        }

        return $metrics;
    }

    /**
     * @return array<string, int|float>
     */
    private function computeLighthouseMetrics(Scan $scan): array
    {
        $avgAccessibility = LighthouseResult::withoutGlobalScopes()
            ->where('scan_id', $scan->id)
            ->avg('accessibility_score');

        if ($avgAccessibility === null) {
            return [];
        }

        $avgPerformance = LighthouseResult::withoutGlobalScopes()
            ->where('scan_id', $scan->id)
            ->avg('performance_score');

        return [
            'lighthouse_accessibility_avg' => round((float) $avgAccessibility, 4),
            'lighthouse_performance_avg' => round((float) $avgPerformance, 4),
        ];
    }

    private function computeRiskTrend(Scan $scan, float $currentScore): ?float
    {
        $priorScore = ScanMetric::withoutGlobalScopes()
            ->join('scans', 'scans.id', '=', 'scan_metrics.scan_id')
            ->where('scans.property_id', $scan->property_id)
            ->where('scan_metrics.metric_name', 'accessibility_risk_score')
            ->where('scan_metrics.scan_id', '<', $scan->id)
            ->orderByDesc('scan_metrics.scan_id')
            ->value('scan_metrics.metric_value');

        if ($priorScore === null) {
            return null;
        }

        return round($currentScore - (float) $priorScore, 4);
    }
}
