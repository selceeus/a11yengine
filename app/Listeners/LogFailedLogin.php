<?php

namespace App\Listeners;

use App\Models\User;
use App\Notifications\SuspiciousLoginNotification;
use App\Services\ActivityLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\Cache;

class LogFailedLogin
{
    public function handle(Failed $event): void
    {
        $email = $event->credentials['email'] ?? null;

        if (! $email || ! is_string($email)) {
            return;
        }

        $cacheKey = 'login_failures:'.md5($email);

        $count = Cache::get($cacheKey, 0) + 1;
        Cache::put($cacheKey, $count, now()->addHour());

        ActivityLogger::loginFailed($email, request(), $count);

        if ($count >= 5) {
            $agencyId = User::where('email', $email)->value('agency_id');

            if (! $agencyId) {
                return;
            }

            $admins = User::query()
                ->where('agency_id', $agencyId)
                ->whereHas('roles', fn ($q) => $q->where('role', 'agency_admin'))
                ->get();

            foreach ($admins as $admin) {
                $admin->notify(new SuspiciousLoginNotification($email, $count, request()->ip()));
            }
        }
    }
}
