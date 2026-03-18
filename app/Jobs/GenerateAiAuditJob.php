<?php

namespace App\Jobs;

use App\Enums\AuditStatus;
use App\Models\Audit;
use App\Services\AiAuditService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateAiAuditJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 2;

    /** @var array<int, int> */
    public array $backoff = [60, 120];

    public function __construct(public readonly Audit $audit) {}

    public function handle(AiAuditService $service): void
    {
        $this->audit->update(['status' => AuditStatus::Processing]);

        $service->generate($this->audit->fresh());
    }

    public function failed(Throwable $e): void
    {
        $this->audit->update([
            'status' => AuditStatus::Failed,
            'error_message' => substr($e->getMessage(), 0, 250),
        ]);
    }
}
