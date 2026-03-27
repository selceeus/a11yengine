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
        $priorScanId = $this->resolvePriorScanId($scan);

        $axeMetrics = $this->computeAxeMetrics($scan);
        $lighthouseMetrics = $this->computeLighthouseMetrics($scan);

        if ($priorScanId !== null) {
            $axeMetrics['risk_trend'] = $this->computeRiskTrend($axeMetrics['accessibility_risk_score'], $priorScanId);
            $axeMetrics = array_merge($axeMetrics, $this->computeIssueDeltaMetrics($scan, $priorScanId));

            $lighthouseDeltaMetrics = $this->computeLighthouseDeltaMetrics($priorScanId, $lighthouseMetrics);

            if (! empty($lighthouseDeltaMetrics)) {
                $lighthouseMetrics = array_merge($lighthouseMetrics, $lighthouseDeltaMetrics);
            }
        }

        $this->recorder->record($scan, null, $axeMetrics, 'axe');

        if (! empty($lighthouseMetrics)) {
            $this->recorder->record($scan, null, $lighthouseMetrics, 'lighthouse');
        }

        $experienceMetrics = $this->computeExperienceMetrics($axeMetrics, $lighthouseMetrics, $priorScanId);

        if (! empty($experienceMetrics)) {
            $this->recorder->record($scan, null, $experienceMetrics, 'experience');
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

        $pages = max(1, $scan->pages_scanned ?? 1);

        $score = max(0.0, 100.0 - (($critical * 5.0 + $serious * 3.0 + $moderate * 1.0 + $minor * 0.5) / $pages));

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

        return [
            'accessibility_risk_score' => $score,
            'total_issue_count' => $totalIssueCount,
            'critical_issue_count' => $criticalIssueCount,
        ];
    }

    /**
     * @return array<string, int|float>
     */
    private function computeLighthouseMetrics(Scan $scan): array
    {
        $avgAccessibility = LighthouseResult::withoutGlobalScopes()
            ->where('scan_id', $scan->id)
            ->where('form_factor', 'mobile')
            ->avg('accessibility_score');

        if ($avgAccessibility === null) {
            return [];
        }

        $base = LighthouseResult::withoutGlobalScopes()
            ->where('scan_id', $scan->id)
            ->where('form_factor', 'mobile');

        $avgPerformance = $base->avg('performance_score');
        $avgBestPractices = $base->avg('best_practices_score');
        $avgSeo = $base->avg('seo_score');

        return [
            'lighthouse_accessibility_avg' => round((float) $avgAccessibility, 4),
            'lighthouse_performance_avg' => round((float) $avgPerformance, 4),
            'lighthouse_best_practices_avg' => round((float) $avgBestPractices, 4),
            'lighthouse_seo_avg' => round((float) $avgSeo, 4),
        ];
    }

    /**
     * @param  array<string, int|float>  $axeMetrics
     * @param  array<string, int|float>  $lighthouseMetrics
     */
    private function computeExperienceScore(array $axeMetrics, array $lighthouseMetrics): ?float
    {
        if (
            ! isset($lighthouseMetrics['lighthouse_performance_avg']) ||
            ! isset($lighthouseMetrics['lighthouse_best_practices_avg']) ||
            ! isset($lighthouseMetrics['lighthouse_seo_avg'])
        ) {
            return null;
        }

        $score = 0.40 * $axeMetrics['accessibility_risk_score']
            + 0.25 * $lighthouseMetrics['lighthouse_performance_avg']
            + 0.20 * $lighthouseMetrics['lighthouse_best_practices_avg']
            + 0.15 * $lighthouseMetrics['lighthouse_seo_avg'];

        return round($score, 2);
    }

    private function resolvePriorScanId(Scan $scan): ?int
    {
        $id = ScanMetric::withoutGlobalScopes()
            ->join('scans', 'scans.id', '=', 'scan_metrics.scan_id')
            ->where('scans.property_id', $scan->property_id)
            ->where('scan_metrics.metric_name', 'accessibility_risk_score')
            ->where('scan_metrics.scan_id', '<', $scan->id)
            ->orderByDesc('scan_metrics.scan_id')
            ->value('scan_metrics.scan_id');

        return $id !== null ? (int) $id : null;
    }

    private function computeRiskTrend(float $currentScore, int $priorScanId): float
    {
        $priorScore = (float) ScanMetric::withoutGlobalScopes()
            ->where('scan_id', $priorScanId)
            ->where('metric_name', 'accessibility_risk_score')
            ->value('metric_value');

        return round($currentScore - $priorScore, 4);
    }

    /**
     * @return array<string, int>
     */
    private function computeIssueDeltaMetrics(Scan $scan, int $priorScanId): array
    {
        $current = Finding::withoutGlobalScopes()
            ->where('scan_id', $scan->id)
            ->whereNotNull('issue_id')
            ->distinct()
            ->pluck('issue_id');

        $prior = Finding::withoutGlobalScopes()
            ->where('scan_id', $priorScanId)
            ->whereNotNull('issue_id')
            ->distinct()
            ->pluck('issue_id');

        return [
            'resolved_issue_count' => $prior->diff($current)->count(),
            'new_issue_count' => $current->diff($prior)->count(),
        ];
    }

    /**
     * @param  array<string, int|float>  $currentLighthouseMetrics
     * @return array<string, float>
     */
    private function computeLighthouseDeltaMetrics(int $priorScanId, array $currentLighthouseMetrics): array
    {
        if (empty($currentLighthouseMetrics)) {
            return [];
        }

        $priorMetrics = ScanMetric::withoutGlobalScopes()
            ->where('scan_id', $priorScanId)
            ->whereIn('metric_name', [
                'lighthouse_accessibility_avg',
                'lighthouse_performance_avg',
                'lighthouse_best_practices_avg',
                'lighthouse_seo_avg',
            ])
            ->pluck('metric_value', 'metric_name');

        $deltas = [];

        $deltaMap = [
            'lighthouse_accessibility_avg' => 'lighthouse_accessibility_delta',
            'lighthouse_performance_avg' => 'lighthouse_performance_delta',
            'lighthouse_best_practices_avg' => 'lighthouse_best_practices_delta',
            'lighthouse_seo_avg' => 'lighthouse_seo_delta',
        ];

        foreach ($deltaMap as $avgKey => $deltaKey) {
            if (isset($currentLighthouseMetrics[$avgKey]) && $priorMetrics->has($avgKey)) {
                $deltas[$deltaKey] = round(
                    $currentLighthouseMetrics[$avgKey] - (float) $priorMetrics[$avgKey],
                    4
                );
            }
        }

        return $deltas;
    }

    /**
     * @param  array<string, int|float>  $axeMetrics
     * @param  array<string, int|float>  $lighthouseMetrics
     * @return array<string, float>
     */
    private function computeExperienceMetrics(array $axeMetrics, array $lighthouseMetrics, ?int $priorScanId): array
    {
        $score = $this->computeExperienceScore($axeMetrics, $lighthouseMetrics);

        if ($score === null) {
            return [];
        }

        $metrics = ['experience_score' => $score];

        if ($priorScanId !== null) {
            $priorScore = ScanMetric::withoutGlobalScopes()
                ->where('scan_id', $priorScanId)
                ->where('metric_name', 'experience_score')
                ->value('metric_value');

            if ($priorScore !== null) {
                $metrics['experience_score_delta'] = round($score - (float) $priorScore, 4);
            }
        }

        return $metrics;
    }
}
