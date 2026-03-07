<?php

namespace App\Domain\Scans;

use App\Models\Scan;
use App\Models\ScanMetric;
use App\Models\ScanPage;
use Illuminate\Support\Facades\Date;

class RecordScanMetrics
{
    /**
     * Bulk-insert a set of named metrics for a single scan page.
     *
     * Each entry in `$metrics` becomes one row in `scan_metrics`.
     * Uses a single INSERT statement for efficiency. Does nothing
     * when `$metrics` is empty.
     *
     * @param  array<string, int|float>  $metrics  Keyed by metric name, e.g.:
     *                                             ['accessibility_issue_count' => 14, 'lighthouse_performance' => 82]
     */
    public function record(Scan $scan, ScanPage $page, array $metrics, string $source): void
    {
        if (empty($metrics)) {
            return;
        }

        $now = Date::now();

        $rows = collect($metrics)->map(fn (int|float $value, string $name) => [
            'agency_id' => $scan->agency_id,
            'scan_id' => $scan->id,
            'page_id' => $page->id,
            'metric_name' => $name,
            'metric_value' => $value,
            'metric_source' => $source,
            'created_at' => $now,
        ])->values()->all();

        ScanMetric::insert($rows);
    }
}
