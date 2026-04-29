<?php

namespace App\Jobs;

use App\Domain\Content\AiContentAuditService;
use App\Enums\ContentAuditStatus;
use App\Models\ContentAudit;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateContentAuditJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 2;

    public int $uniqueFor = 300;

    /** @var array<int, int> */
    public array $backoff = [60, 120];

    public function __construct(public readonly ContentAudit $contentAudit) {}

    public function uniqueId(): string
    {
        return (string) $this->contentAudit->id;
    }

    public function handle(AiContentAuditService $service): void
    {
        $this->contentAudit->update(['status' => ContentAuditStatus::Processing]);

        $service->generate($this->contentAudit->fresh()->load('property'));
    }

    public function failed(Throwable $e): void
    {
        $this->contentAudit->update([
            'status' => ContentAuditStatus::Failed,
            'error_message' => substr($e->getMessage(), 0, 250),
        ]);
    }
}
