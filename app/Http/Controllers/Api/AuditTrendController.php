<?php

namespace App\Http\Controllers\Api;

use App\Domain\Audits\CompareAuditTrends;
use App\Http\Controllers\Controller;
use App\Models\Property;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuditTrendController extends Controller
{
    use AuthorizesRequests;

    private const ALLOWED_DAYS = [7, 30, 90];

    public function __invoke(Request $request, Property $property, CompareAuditTrends $trends): JsonResponse
    {
        $this->authorize('view', $property);

        $days = (int) $request->query('days', 30);

        if (! in_array($days, self::ALLOWED_DAYS, strict: true)) {
            throw ValidationException::withMessages(['days' => 'days must be 7, 30, or 90.']);
        }

        $latestAudit = \App\Models\Audit::withoutGlobalScopes()
            ->where('property_id', $property->id)
            ->where('status', \App\Enums\AuditStatus::Completed)
            ->latest('generated_at')
            ->first();

        $trend = $latestAudit
            ? $trends->handle($latestAudit, $days)
            : [
                'history' => [],
                'audit_count' => 0,
                'previous_score' => null,
                'score_delta' => null,
                'trend_direction' => 'stable',
            ];

        return response()->json([
            ...$trend,
            'property_id' => $property->id,
            'days' => $days,
            'generated_at' => now()->toISOString(),
        ]);
    }
}
