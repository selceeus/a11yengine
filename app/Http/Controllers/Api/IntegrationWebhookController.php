<?php

namespace App\Http\Controllers\Api;

use App\Domain\Integrations\IntegrationProviderRegistry;
use App\Http\Controllers\Controller;
use App\Models\Integration;
use App\Models\IssueLink;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class IntegrationWebhookController extends Controller
{
    private const DONE_STATUSES = ['done', 'closed', 'completed', 'resolved', 'won\'t fix'];

    public function __invoke(Request $request, Integration $integration): Response
    {
        $provider = IntegrationProviderRegistry::make($integration->provider);

        if (! $provider->verifyWebhook($integration, $request)) {
            abort(401, 'Invalid webhook signature.');
        }

        $externalId = $provider->parseWebhookExternalId($request);
        $externalStatus = $provider->parseWebhookStatus($request);

        if (empty($externalId)) {
            return response()->noContent();
        }

        $issueLink = IssueLink::query()
            ->where('integration_id', $integration->id)
            ->where('external_id', $externalId)
            ->with('issue')
            ->first();

        if ($issueLink === null) {
            return response()->noContent();
        }

        $issueLink->update([
            'external_status' => $externalStatus,
            'synced_at' => now(),
        ]);

        if ($this->isDone($externalStatus) && $issueLink->issue !== null) {
            $issue = $issueLink->issue;

            if (! $issue->status->isTerminal()) {
                $issue->markResolved('Resolved via integration webhook.');
            }
        }

        return response()->noContent();
    }

    private function isDone(string $status): bool
    {
        $normalised = strtolower(trim($status));

        foreach (self::DONE_STATUSES as $done) {
            if (str_contains($normalised, $done)) {
                return true;
            }
        }

        return false;
    }
}
