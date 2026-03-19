<?php

namespace App\Jobs;

use App\Domain\Issues\AiIssueClusterService;
use App\Enums\ClusterStatus;
use App\Models\IssueCluster;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateIssueClusteringJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 2;

    /** @var array<int, int> */
    public array $backoff = [60, 120];

    public function __construct(public readonly IssueCluster $issueCluster) {}

    public function handle(AiIssueClusterService $service): void
    {
        $this->issueCluster->update(['status' => ClusterStatus::Processing]);

        $service->generate($this->issueCluster->fresh()->load('property'));
    }

    public function failed(Throwable $e): void
    {
        $this->issueCluster->update([
            'status' => ClusterStatus::Failed,
            'error_message' => substr($e->getMessage(), 0, 250),
        ]);
    }
}
