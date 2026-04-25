<?php

namespace App\Console\Commands;

use App\Enums\ActivityLogEvent;
use App\Models\ApiKey;
use App\Services\ActivityLogger;
use Illuminate\Console\Command;

class RevokeExpiredApiKeysCommand extends Command
{
    protected $signature = 'api-keys:revoke-expired';

    protected $description = 'Revoke API keys that have passed their expiry date';

    public function handle(): int
    {
        $expired = ApiKey::withoutGlobalScopes()
            ->with('agency')
            ->whereNull('revoked_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        if ($expired->isEmpty()) {
            $this->info('No expired API keys to revoke.');

            return self::SUCCESS;
        }

        foreach ($expired as $apiKey) {
            $apiKey->update(['revoked_at' => now()]);

            if ($apiKey->agency) {
                ActivityLogger::system(
                    agencyId: $apiKey->agency_id,
                    event: ActivityLogEvent::ApiKeyRevoked,
                    subject: $apiKey,
                    subjectLabel: $apiKey->name,
                    metadata: ['reason' => 'auto_revoked_expired'],
                );
            }

            $this->line("  revoked key #{$apiKey->id} \"{$apiKey->name}\" (expired {$apiKey->expires_at->toDateString()})");
        }

        $this->info("Done. Revoked {$expired->count()} expired key(s).");

        return self::SUCCESS;
    }
}
