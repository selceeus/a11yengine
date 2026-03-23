<?php

namespace App\Domain\Integrations\Contracts;

use App\Models\Integration;
use App\Models\Issue;
use Illuminate\Http\Request;

interface ProjectManagementProvider
{
    /**
     * Push an issue to the external PM tool and return the external task ID and URL.
     *
     * @return array{id: string, url: string|null}
     */
    public function createTask(Integration $integration, Issue $issue): array;

    /**
     * Close or resolve the external task.
     */
    public function closeTask(Integration $integration, string $externalId): void;

    /**
     * Verify the webhook signature and return true if valid.
     */
    public function verifyWebhook(Integration $integration, Request $request): bool;

    /**
     * Parse the external status string from the incoming webhook payload.
     */
    public function parseWebhookStatus(Request $request): string;

    /**
     * Parse the external task ID from the incoming webhook payload.
     */
    public function parseWebhookExternalId(Request $request): string;

    /**
     * Test the credentials and return true if the connection succeeds.
     *
     * @return array{ok: bool, message: string}
     */
    public function testConnection(Integration $integration): array;
}
