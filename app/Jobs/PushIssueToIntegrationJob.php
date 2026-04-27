<?php

namespace App\Jobs;

use App\Domain\Integrations\IntegrationProviderRegistry;
use App\Enums\IntegrationStatus;
use App\Models\Integration;
use App\Models\Issue;
use App\Models\IssueLink;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class PushIssueToIntegrationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120];

    public function __construct(
        public readonly Issue $issue,
        public readonly Integration $integration,
    ) {}

    public function handle(): void
    {
        $existing = IssueLink::query()
            ->where('issue_id', $this->issue->id)
            ->where('integration_id', $this->integration->id)
            ->exists();

        if ($existing) {
            return;
        }

        $provider = IntegrationProviderRegistry::make($this->integration->provider);

        $result = $provider->createTask($this->integration, $this->issue);

        IssueLink::create([
            'issue_id' => $this->issue->id,
            'integration_id' => $this->integration->id,
            'external_id' => $result['id'],
            'external_url' => $result['url'],
            'synced_at' => now(),
        ]);

        $this->integration->update([
            'status' => IntegrationStatus::Active,
            'error_message' => null,
            'last_synced_at' => now(),
        ]);
    }

    public function failed(Throwable $exception): void
    {
        $this->integration->update([
            'status' => IntegrationStatus::Error,
            'error_message' => $exception->getMessage(),
        ]);
    }
}
