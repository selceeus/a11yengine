<?php

namespace App\Http\Controllers\Api;

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Issue;
use App\Models\Property;
use App\Models\PropertyRiskSnapshot;
use Illuminate\Http\JsonResponse;

class WordPressRiskSummaryController extends Controller
{
    public function __invoke(string $propertySlug): JsonResponse
    {
        $agency = app(Agency::class);

        $property = Property::withoutGlobalScopes()
            ->where('agency_id', $agency->id)
            ->where('slug', $propertySlug)
            ->firstOrFail();

        $openIssues = Issue::withoutGlobalScopes()
            ->where('property_id', $property->id)
            ->whereIn('status', IssueStatus::activeStatusValues());

        $counts = (clone $openIssues)
            ->selectRaw('severity, COUNT(*) as count')
            ->groupBy('severity')
            ->pluck('count', 'severity')
            ->toArray();

        $latestSnapshot = PropertyRiskSnapshot::query()
            ->where('property_id', $property->id)
            ->orderByDesc('snapshot_date')
            ->first(['risk_score', 'snapshot_date']);

        return response()->json([
            'property' => [
                'id' => $property->id,
                'name' => $property->name,
                'slug' => $property->slug,
            ],
            'risk_score' => $latestSnapshot?->risk_score,
            'snapshot_date' => $latestSnapshot?->snapshot_date?->toDateString(),
            'issue_counts' => [
                'critical' => (int) ($counts[IssueSeverity::Critical->value] ?? 0),
                'high' => (int) ($counts[IssueSeverity::High->value] ?? 0),
                'medium' => (int) ($counts[IssueSeverity::Medium->value] ?? 0),
                'low' => (int) ($counts[IssueSeverity::Low->value] ?? 0),
                'total' => array_sum($counts),
            ],
            'generated_at' => now()->toISOString(),
        ]);
    }
}
