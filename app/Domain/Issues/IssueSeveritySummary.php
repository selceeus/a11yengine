<?php

namespace App\Domain\Issues;

use App\Enums\IssueSeverity;

class IssueSeveritySummary
{
    /**
     * Build the standard severity summary response array from a plucked count-by-severity array.
     *
     * @param  array<string, int>  $counts  Keyed by severity value, values are counts
     * @return array{critical: int, high: int, medium: int, low: int, total: int, generated_at: string}
     */
    public static function fromCounts(array $counts): array
    {
        return [
            'critical' => (int) ($counts[IssueSeverity::Critical->value] ?? 0),
            'high' => (int) ($counts[IssueSeverity::High->value] ?? 0),
            'medium' => (int) ($counts[IssueSeverity::Medium->value] ?? 0),
            'low' => (int) ($counts[IssueSeverity::Low->value] ?? 0),
            'total' => array_sum($counts),
            'generated_at' => now()->toISOString(),
        ];
    }
}
