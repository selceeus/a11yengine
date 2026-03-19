<?php

namespace App\Jobs;

use App\Domain\Governance\AiGovernanceService;
use App\Enums\GovernanceReportStatus;
use App\Models\GovernanceReport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateGovernanceReportJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 2;

    /** @var array<int, int> */
    public array $backoff = [60, 120];

    public function __construct(public readonly GovernanceReport $report) {}

    public function handle(AiGovernanceService $service): void
    {
        $this->report->update(['status' => GovernanceReportStatus::Processing]);

        $service->generate($this->report->fresh());
    }

    public function failed(Throwable $e): void
    {
        $this->report->update([
            'status' => GovernanceReportStatus::Failed,
            'error_message' => substr($e->getMessage(), 0, 250),
        ]);
    }
}
