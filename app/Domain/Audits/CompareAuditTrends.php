<?php

namespace App\Domain\Audits;

use App\Enums\AuditStatus;
use App\Models\Audit;
use Carbon\CarbonImmutable;

class CompareAuditTrends
{
    /**
     * Build a trend comparison for the given audit against historical audits for the same property.
     *
     * @param  int  $days  Date window: 7, 30, or 90.
     * @return array{
     *     history: list<array{id: int, generated_at: string, overall_score: int|null, title: string}>,
     *     audit_count: int,
     *     previous_score: int|null,
     *     score_delta: int|null,
     *     trend_direction: string,
     * }
     */
    public function handle(Audit $current, int $days = 30): array
    {
        $windowStart = CarbonImmutable::now()->subDays($days - 1)->startOfDay();

        $history = Audit::withoutGlobalScopes()
            ->where('property_id', $current->property_id)
            ->where('status', AuditStatus::Completed)
            ->where('generated_at', '>=', $windowStart)
            ->orderBy('generated_at')
            ->get(['id', 'title', 'overall_score', 'generated_at']);

        $auditCount = $history->count();

        $previousScore = null;
        $scoreDelta = null;
        $trendDirection = 'stable';

        if ($auditCount > 1) {
            $previousAudit = $history->first();
            $previousScore = $previousAudit->overall_score;

            if ($current->overall_score !== null && $previousScore !== null) {
                $scoreDelta = $current->overall_score - $previousScore;

                $trendDirection = match (true) {
                    $scoreDelta > 2 => 'improving',
                    $scoreDelta < -2 => 'declining',
                    default => 'stable',
                };
            }
        }

        return [
            'history' => $history->map(fn (Audit $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'overall_score' => $a->overall_score,
                'generated_at' => $a->generated_at?->toIso8601String(),
            ])->values()->all(),
            'audit_count' => $auditCount,
            'previous_score' => $previousScore,
            'score_delta' => $scoreDelta,
            'trend_direction' => $trendDirection,
        ];
    }
}
