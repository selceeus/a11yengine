<?php

namespace App\Console\Commands;

use App\Enums\ActivityLogEvent;
use App\Models\Agency;
use App\Models\ApiKey;
use App\Models\User;
use App\Notifications\ApiKeyExpiringSoonNotification;
use App\Services\ActivityLogger;
use Illuminate\Console\Command;

class NotifyExpiringApiKeysCommand extends Command
{
    protected $signature = 'api-keys:notify-expiring
                            {--days=30 : Notify keys expiring within this many days}';

    protected $description = 'Send expiry warning notifications for API keys expiring soon';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $window = [now(), now()->addDays($days)];

        $keys = ApiKey::withoutGlobalScopes()
            ->with(['createdBy', 'agency'])
            ->whereNull('revoked_at')
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', $window)
            ->get();

        if ($keys->isEmpty()) {
            $this->info('No API keys expiring within the notification window.');

            return self::SUCCESS;
        }

        $notified = 0;

        foreach ($keys as $apiKey) {
            $agency = $apiKey->agency;

            if (! $agency) {
                continue;
            }

            // Notify the key creator
            if ($apiKey->createdBy) {
                $apiKey->createdBy->notify(new ApiKeyExpiringSoonNotification($apiKey));
            }

            // Notify all agency admins (deduplicated by ID)
            $adminIds = User::query()
                ->where('agency_id', $agency->id)
                ->whereHas('roles', fn ($q) => $q->where('role', 'agency_admin'))
                ->pluck('id');

            $notifiedAdminIds = $adminIds->filter(
                fn ($id) => $apiKey->createdBy === null || $id !== $apiKey->createdBy->id,
            );

            User::whereIn('id', $notifiedAdminIds)->get()
                ->each(fn (User $admin) => $admin->notify(new ApiKeyExpiringSoonNotification($apiKey)));

            ActivityLogger::system(
                agencyId: $agency->id,
                event: ActivityLogEvent::ApiKeyExpiringSoon,
                subject: $apiKey,
                subjectLabel: $apiKey->name,
                metadata: [
                    'expires_at' => $apiKey->expires_at->toIso8601String(),
                    'days_remaining' => (int) now()->diffInDays($apiKey->expires_at),
                ],
            );

            $this->line("  notified for key #{$apiKey->id} \"{$apiKey->name}\" (agency #{$agency->id})");
            $notified++;
        }

        $this->info("Done. Sent notifications for {$notified} expiring key(s).");

        return self::SUCCESS;
    }
}
