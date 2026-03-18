<?php

namespace App\Jobs;

use App\Domain\Issues\AiRemediationService;
use App\Models\Issue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateIssueRemediationJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public int $tries = 2;

    /** @var array<int, int> */
    public array $backoff = [30, 60];

    public function __construct(public readonly Issue $issue) {}

    public function handle(AiRemediationService $service): void
    {
        $this->issue->update(['ai_remediation_status' => 'processing']);

        $suggestions = $service->generate($this->issue->fresh()->load('findings'));

        $this->issue->update([
            'ai_remediation_status' => 'completed',
            'ai_suggestions' => $suggestions,
        ]);
    }

    public function failed(Throwable $e): void
    {
        $this->issue->update(['ai_remediation_status' => 'failed']);
    }
}
