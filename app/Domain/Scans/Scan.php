<?php

namespace App\Domain\Scans;

use App\Enums\ScanStatus;
use App\Models\Scan as ScanModel;
use Illuminate\Support\Facades\Date;

class Scan
{
    public function start(ScanModel|int $scan): ScanModel
    {
        $scan = $this->resolve($scan);

        $scan->update([
            'status' => ScanStatus::Running,
            'started_at' => Date::now(),
        ]);

        return $scan->fresh();
    }

    public function complete(ScanModel|int $scan, int $pagesScanned, int $totalViolations, ?string $rawOutputPath = null): ScanModel
    {
        $scan = $this->resolve($scan);

        $scan->update([
            'status' => ScanStatus::Completed,
            'pages_scanned' => $pagesScanned,
            'total_violations' => $totalViolations,
            'raw_output_path' => $rawOutputPath,
            'completed_at' => Date::now(),
        ]);

        return $scan->fresh();
    }

    public function fail(ScanModel|int $scan): ScanModel
    {
        $scan = $this->resolve($scan);

        $scan->update([
            'status' => ScanStatus::Failed,
            'completed_at' => Date::now(),
        ]);

        return $scan->fresh();
    }

    private function resolve(ScanModel|int $scan): ScanModel
    {
        return $scan instanceof ScanModel
            ? $scan
            : ScanModel::withoutGlobalScopes()->findOrFail($scan);
    }
}
