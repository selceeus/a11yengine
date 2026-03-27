<?php

namespace App\Http\Controllers\Api;

use App\Enums\FindingSeverity;
use App\Enums\ScanStatus;
use App\Http\Controllers\Controller;
use App\Models\Finding;
use App\Models\LighthouseResult;
use App\Models\Property;
use App\Models\Scan;
use App\Models\ScanMetric;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RiskDashboardController extends Controller
{
    public function __invoke(Request $request, int $site): JsonResponse
    {
        /** @var Property $property */
        $property = Property::withoutGlobalScopes()->findOrFail($site);

        /** @var User $user */
        $user = $request->user();

        abort_unless($user->isSuperUser() || $user->agency_id === $property->agency_id, 403);

        $latestScan = Scan::withoutGlobalScopes()
            ->where('property_id', $property->id)
            ->where('status', ScanStatus::Completed)
            ->orderByDesc('completed_at')
            ->first();

        return response()->json([
            'riskScore' => $this->riskScore($latestScan),
            'severityBreakdown' => $this->severityBreakdown($latestScan),
            'lighthouse' => $this->lighthouse($latestScan),
            'riskTrend' => $this->riskTrend($property),
        ]);
    }

    private function riskScore(?Scan $scan): ?float
    {
        if ($scan === null) {
            return null;
        }

        $value = ScanMetric::withoutGlobalScopes()
            ->where('scan_id', $scan->id)
            ->where('metric_name', 'accessibility_risk_score')
            ->whereNull('page_id')
            ->value('metric_value');

        return $value !== null ? round((float) $value, 1) : null;
    }

    /**
     * @return array{critical: int, serious: int, moderate: int}
     */
    private function severityBreakdown(?Scan $scan): array
    {
        if ($scan === null) {
            return ['critical' => 0, 'serious' => 0, 'moderate' => 0];
        }

        $counts = Finding::withoutGlobalScopes()
            ->where('scan_id', $scan->id)
            ->selectRaw('severity, COUNT(*) as total')
            ->groupBy('severity')
            ->pluck('total', 'severity');

        return [
            'critical' => (int) ($counts[FindingSeverity::CRITICAL->value] ?? 0),
            'serious' => (int) ($counts[FindingSeverity::SERIOUS->value] ?? 0),
            'moderate' => (int) ($counts[FindingSeverity::MODERATE->value] ?? 0),
        ];
    }

    /**
     * @return array{accessibility: int|null, performance: int|null, bestPractices: int|null}
     */
    private function lighthouse(?Scan $scan): array
    {
        if ($scan === null) {
            return ['accessibility' => null, 'performance' => null, 'bestPractices' => null];
        }

        $averages = LighthouseResult::withoutGlobalScopes()
            ->where('scan_id', $scan->id)
            ->where('form_factor', 'mobile')
            ->selectRaw('AVG(accessibility_score) as avg_accessibility, AVG(performance_score) as avg_performance, AVG(best_practices_score) as avg_best_practices')
            ->first();

        if ($averages === null || $averages->avg_accessibility === null) {
            return ['accessibility' => null, 'performance' => null, 'bestPractices' => null];
        }

        return [
            'accessibility' => (int) round((float) $averages->avg_accessibility),
            'performance' => (int) round((float) $averages->avg_performance),
            'bestPractices' => (int) round((float) $averages->avg_best_practices),
        ];
    }

    /**
     * @return list<array{date: string, score: int}>
     */
    private function riskTrend(Property $property): array
    {
        return ScanMetric::withoutGlobalScopes()
            ->join('scans', 'scans.id', '=', 'scan_metrics.scan_id')
            ->where('scans.property_id', $property->id)
            ->where('scan_metrics.metric_name', 'accessibility_risk_score')
            ->whereNull('scan_metrics.page_id')
            ->whereNotNull('scans.completed_at')
            ->orderBy('scans.completed_at')
            ->limit(30)
            ->get(['scans.completed_at', 'scan_metrics.metric_value'])
            ->map(fn ($row) => [
                'date' => Carbon::parse($row->completed_at)->toDateString(),
                'score' => (int) round((float) $row->metric_value),
            ])
            ->values()
            ->all();
    }
}
