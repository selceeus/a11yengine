<?php

namespace App\Domain\Scans;

use App\Enums\ScanPageStatus;
use App\Models\Scan as ScanModel;
use App\Models\ScanPage as ScanPageModel;

class ScanPage
{
    public function record(ScanModel|int $scan, string $url, int $violationsCount): ScanPageModel
    {
        $scan = $this->resolveScan($scan);

        return ScanPageModel::query()->create([
            'agency_id' => $scan->agency_id,
            'scan_id' => $scan->id,
            'url' => $url,
            'violations_count' => $violationsCount,
            'status' => ScanPageStatus::Scanned,
        ]);
    }

    public function fail(ScanModel|int $scan, string $url): ScanPageModel
    {
        $scan = $this->resolveScan($scan);

        return ScanPageModel::query()->create([
            'agency_id' => $scan->agency_id,
            'scan_id' => $scan->id,
            'url' => $url,
            'violations_count' => 0,
            'status' => ScanPageStatus::Failed,
        ]);
    }

    private function resolveScan(ScanModel|int $scan): ScanModel
    {
        return $scan instanceof ScanModel
            ? $scan
            : ScanModel::withoutGlobalScopes()->findOrFail($scan);
    }
}
