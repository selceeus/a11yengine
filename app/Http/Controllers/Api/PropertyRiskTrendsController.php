<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\PropertyRiskSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PropertyRiskTrendsController extends Controller
{
    use AuthorizesRequests;

    private const ALLOWED_DAYS = [7, 30, 90];

    public function __invoke(Request $request, Property $property): JsonResponse
    {
        $this->authorize('view', $property);

        $days = (int) $request->query('days', 30);

        if (! in_array($days, self::ALLOWED_DAYS, strict: true)) {
            throw ValidationException::withMessages(['days' => 'days must be 7, 30, or 90.']);
        }

        $windowStart = CarbonImmutable::now()->subDays($days - 1)->startOfDay();

        $snapshots = PropertyRiskSnapshot::query()
            ->where('property_id', $property->id)
            ->where('snapshot_date', '>=', $windowStart->toDateString())
            ->orderBy('snapshot_date')
            ->get(['snapshot_date', 'risk_score', 'open_issue_count']);

        $dateSpine = [];
        for ($i = 0; $i < $days; $i++) {
            $dateSpine[] = $windowStart->addDays($i)->toDateString();
        }

        $indexed = [];
        foreach ($snapshots as $snap) {
            $indexed[$snap->snapshot_date->toDateString()] = [
                'risk_score' => $snap->risk_score,
                'open_issues' => $snap->open_issue_count,
            ];
        }

        $series = [];
        foreach ($dateSpine as $date) {
            $series[] = [
                'date' => $date,
                'risk_score' => $indexed[$date]['risk_score'] ?? 0,
                'open_issues' => $indexed[$date]['open_issues'] ?? 0,
            ];
        }

        return response()->json([
            'series' => $series,
            'days' => $dateSpine,
            'generated_at' => now()->toISOString(),
        ]);
    }
}
