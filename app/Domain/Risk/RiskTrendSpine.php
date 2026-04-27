<?php

namespace App\Domain\Risk;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class RiskTrendSpine
{
    /** @var list<int> */
    public const ALLOWED_DAYS = [7, 30, 90];

    /**
     * Validate the requested days parameter against the allowed window sizes.
     *
     * @throws ValidationException
     */
    public static function validateDays(int $days): void
    {
        if (! in_array($days, self::ALLOWED_DAYS, strict: true)) {
            throw ValidationException::withMessages(['days' => 'days must be 7, 30, or 90.']);
        }
    }

    /**
     * Build a sequential list of date strings for the given window.
     *
     * @return list<string>
     */
    public static function buildDateSpine(CarbonImmutable $windowStart, int $days): array
    {
        $dateSpine = [];

        for ($i = 0; $i < $days; $i++) {
            $dateSpine[] = $windowStart->addDays($i)->toDateString();
        }

        return $dateSpine;
    }

    /**
     * Build a zero-filled series from a date-indexed snapshot map.
     *
     * @param  array<string, array{risk_score: int, open_issues: int}>  $indexed
     * @param  list<string>  $dateSpine
     * @return list<array{date: string, risk_score: int, open_issues: int}>
     */
    public static function buildSeries(array $indexed, array $dateSpine): array
    {
        $series = [];

        foreach ($dateSpine as $date) {
            $series[] = [
                'date' => $date,
                'risk_score' => $indexed[$date]['risk_score'] ?? 0,
                'open_issues' => $indexed[$date]['open_issues'] ?? 0,
            ];
        }

        return $series;
    }

    /**
     * SQL CASE expression that converts severity string to a numeric rank (4=critical … 1=low).
     * Use inside selectRaw() to determine the "highest" severity for a group.
     */
    public static function severityCaseSql(string $column = 'severity'): string
    {
        return "MAX(CASE {$column}
                    WHEN 'critical' THEN 4
                    WHEN 'high'     THEN 3
                    WHEN 'medium'   THEN 2
                    WHEN 'low'      THEN 1
                    ELSE 0 END) as severity_order";
    }

    /**
     * Map a numeric severity rank back to its string label.
     */
    public static function rankToSeverity(int $rank): ?string
    {
        return match ($rank) {
            4 => 'critical',
            3 => 'high',
            2 => 'medium',
            1 => 'low',
            default => null,
        };
    }
}
