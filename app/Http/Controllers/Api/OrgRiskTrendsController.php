<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\RiskSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OrgRiskTrendsController extends Controller
{
    private const ALLOWED_DAYS = [7, 30, 90];

    public function __invoke(Request $request, Organization $organization): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->isSuperUser() || $user->agency_id === $organization->agency_id, 403);

        $days = (int) $request->query('days', 30);

        if (! in_array($days, self::ALLOWED_DAYS, strict: true)) {
            throw ValidationException::withMessages(['days' => 'days must be 7, 30, or 90.']);
        }

        $windowStart = CarbonImmutable::now()->subDays($days - 1)->startOfDay();

        $snapshots = RiskSnapshot::query()
            ->where('organization_id', $organization->id)
            ->where('snapshot_date', '>=', $windowStart->toDateString())
            ->orderBy('snapshot_date')
            ->get(['snapshot_date', 'total_risk_score', 'open_issue_count']);

        $dateSpine = [];
        for ($i = 0; $i < $days; $i++) {
            $dateSpine[] = $windowStart->addDays($i)->toDateString();
        }

        $indexed = [];
        foreach ($snapshots as $snap) {
            $indexed[$snap->snapshot_date->toDateString()] = [
                'risk_score' => $snap->total_risk_score,
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
            'organizations' => [
                [
                    'id' => $organization->id,
                    'name' => $organization->name,
                    'series' => $series,
                ],
            ],
            'days' => $dateSpine,
            'generated_at' => now()->toISOString(),
        ]);
    }
}
