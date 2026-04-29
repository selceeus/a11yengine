<?php

namespace App\Jobs;

use App\Domain\Risk\AiRiskAdvisorService;
use App\Enums\RiskAdvisoryStatus;
use App\Models\RiskAdvisory;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateRiskAdvisoryJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 2;

    public int $uniqueFor = 300;

    /** @var array<int, int> */
    public array $backoff = [60, 120];

    public function __construct(public readonly RiskAdvisory $riskAdvisory) {}

    public function uniqueId(): string
    {
        return (string) $this->riskAdvisory->id;
    }

    public function handle(AiRiskAdvisorService $service): void
    {
        $this->riskAdvisory->update(['status' => RiskAdvisoryStatus::Processing]);

        $service->generate($this->riskAdvisory->fresh()->load('property'));
    }

    public function failed(Throwable $e): void
    {
        $this->riskAdvisory->update([
            'status' => RiskAdvisoryStatus::Failed,
            'error_message' => substr($e->getMessage(), 0, 250),
        ]);
    }
}
