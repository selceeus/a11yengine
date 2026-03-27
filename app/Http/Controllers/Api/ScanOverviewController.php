<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Finding;
use App\Models\LighthouseResult;
use App\Models\Scan;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScanOverviewController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(Request $request, Scan $scan): JsonResponse
    {
        $this->authorize('view', $scan);

        $severityBreakdown = Finding::query()
            ->where('scan_id', $scan->id)
            ->selectRaw('severity, count(*) as count')
            ->groupBy('severity')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => [
                'severity' => $row->severity->value,
                'count' => (int) $row->count,
            ]);

        $lighthouseResults = LighthouseResult::query()
            ->where('scan_id', $scan->id)
            ->where('form_factor', 'mobile')
            ->select(['performance_score', 'accessibility_score', 'best_practices_score', 'seo_score'])
            ->get();

        $avg = function (string $field) use ($lighthouseResults): ?int {
            $vals = $lighthouseResults->pluck($field)->filter(fn ($v) => $v !== null);

            return $vals->count() > 0 ? (int) round($vals->average()) : null;
        };

        $lighthouseAverages = $lighthouseResults->count() > 0
            ? [
                'performance' => $avg('performance_score'),
                'accessibility' => $avg('accessibility_score'),
                'best_practices' => $avg('best_practices_score'),
                'seo' => $avg('seo_score'),
            ]
            : null;

        return response()->json([
            'severityBreakdown' => $severityBreakdown,
            'lighthouseAverages' => $lighthouseAverages,
        ]);
    }
}
