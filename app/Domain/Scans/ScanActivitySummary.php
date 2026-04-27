<?php

namespace App\Domain\Scans;

use Carbon\CarbonImmutable;

class ScanActivitySummary
{
    /**
     * Build a 30-day zero-filled date spine from a keyed-by-date scan aggregate.
     *
     * @param  array<string, array{scans: int, violations: int}>  $byDate
     * @return list<array{date: string, scans: int, violations: int}>
     */
    public static function buildDaySpine(array $byDate, CarbonImmutable $windowStart): array
    {
        $days = [];

        for ($i = 0; $i < 30; $i++) {
            $date = $windowStart->addDays($i)->toDateString();
            $days[] = [
                'date' => $date,
                'scans' => $byDate[$date]['scans'] ?? 0,
                'violations' => $byDate[$date]['violations'] ?? 0,
            ];
        }

        return $days;
    }
}
