<?php

namespace App\Http\Controllers\Api;

use App\Enums\ScanPageStatus;
use App\Enums\ScanStatus;
use App\Http\Controllers\Controller;
use App\Models\Finding;
use App\Models\LighthouseResult;
use App\Models\Property;
use App\Models\Scan;
use App\Models\ScanPage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RiskMapController extends Controller
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

        if ($latestScan === null) {
            return response()->json([]);
        }

        $pages = ScanPage::withoutGlobalScopes()
            ->where('scan_id', $latestScan->id)
            ->where('status', ScanPageStatus::Scanned)
            ->get(['id', 'url']);

        $weights = Finding::withoutGlobalScopes()
            ->where('scan_id', $latestScan->id)
            ->selectRaw("page_url, SUM(CASE severity WHEN 'critical' THEN 5 WHEN 'serious' THEN 3 WHEN 'moderate' THEN 1 WHEN 'minor' THEN 0.5 ELSE 0 END) as total_weight")
            ->groupBy('page_url')
            ->pluck('total_weight', 'page_url');

        $issueCounts = Finding::withoutGlobalScopes()
            ->where('scan_id', $latestScan->id)
            ->whereNotNull('issue_id')
            ->selectRaw('page_url, COUNT(DISTINCT issue_id) as issue_count')
            ->groupBy('page_url')
            ->pluck('issue_count', 'page_url');

        $lighthouseScores = LighthouseResult::withoutGlobalScopes()
            ->where('scan_id', $latestScan->id)
            ->get(['url', 'accessibility_score'])
            ->pluck('accessibility_score', 'url');

        $baseUrl = rtrim($property->base_url, '/');

        $results = $pages->map(function (ScanPage $page) use ($weights, $issueCounts, $lighthouseScores, $baseUrl): array {
            $path = Str::after($page->url, $baseUrl) ?: '/';

            /** @var float|int $totalWeight */
            $totalWeight = $weights[$page->url] ?? 0;
            $riskScore = max(0, round(100 - $totalWeight));

            return [
                'url' => $path,
                'riskScore' => $riskScore,
                'issueCount' => (int) ($issueCounts[$page->url] ?? 0),
                'lighthouseAccessibility' => (int) ($lighthouseScores[$page->url] ?? 0),
            ];
        });

        $topPages = $results
            ->sortByDesc('riskScore')
            ->take(200)
            ->values();

        return response()->json($topPages);
    }
}
