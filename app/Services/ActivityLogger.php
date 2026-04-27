<?php

namespace App\Services;

use App\Enums\ActivityLogEvent;
use App\Models\ActivityLog;
use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ActivityLogger
{
    /**
     * Write a generic activity log entry for an authenticated web user.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function log(
        ActivityLogEvent $event,
        ?Model $subject = null,
        ?string $subjectLabel = null,
        array $metadata = [],
        ?string $ipAddress = null,
    ): void {
        $user = auth()->user();

        if (! $user || ! $user->agency_id) {
            return;
        }

        self::write([
            'agency_id' => $user->agency_id,
            'user_id' => $user->id,
            'actor_type' => 'user',
            'actor_label' => $user->name,
            'event' => $event,
            'subject_type' => $subject ? self::subjectType($subject) : null,
            'subject_id' => $subject?->getKey(),
            'subject_label' => $subjectLabel,
            'metadata' => empty($metadata) ? null : $metadata,
            'ip_address' => $ipAddress,
        ]);
    }

    /**
     * Write a failed login attempt. Looks up agency_id by email; silently skips unknown emails.
     */
    public static function loginFailed(string $email, Request $request, int $failureCount): void
    {
        $agencyId = User::where('email', $email)->value('agency_id');

        if (! $agencyId) {
            return;
        }

        self::write([
            'agency_id' => $agencyId,
            'actor_type' => 'unknown',
            'actor_label' => $email,
            'event' => ActivityLogEvent::UserLoginFailed,
            'metadata' => ['attempt_count' => $failureCount],
            'ip_address' => $request->ip(),
        ]);
    }

    /**
     * Write a login event (user may not yet be bound to auth() at this point).
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function loginSuccess(User $user, Request $request, array $metadata = []): void
    {
        if (! $user->agency_id) {
            return;
        }

        self::write([
            'agency_id' => $user->agency_id,
            'user_id' => $user->id,
            'actor_type' => 'user',
            'actor_label' => $user->name,
            'event' => ActivityLogEvent::UserLogin,
            'metadata' => empty($metadata) ? null : $metadata,
            'ip_address' => $request->ip(),
        ]);
    }

    /**
     * Write a logout event.
     */
    public static function logoutSuccess(User $user, Request $request): void
    {
        if (! $user->agency_id) {
            return;
        }

        self::write([
            'agency_id' => $user->agency_id,
            'user_id' => $user->id,
            'actor_type' => 'user',
            'actor_label' => $user->name,
            'event' => ActivityLogEvent::UserLogout,
            'ip_address' => $request->ip(),
        ]);
    }

    /**
     * Write an API key usage event. Called from middleware.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function apiKeyUsed(ApiKey $apiKey, Request $request, array $metadata = []): void
    {
        self::write([
            'agency_id' => $apiKey->agency_id,
            'actor_type' => 'api_key',
            'actor_label' => $apiKey->name,
            'event' => ActivityLogEvent::ApiKeyUsed,
            'metadata' => array_merge(['key_prefix' => $apiKey->key_prefix], $metadata),
            'ip_address' => $request->ip(),
        ]);
    }

    /**
     * Write a system-initiated event (no user context).
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function system(
        int $agencyId,
        ActivityLogEvent $event,
        ?Model $subject = null,
        ?string $subjectLabel = null,
        array $metadata = [],
    ): void {
        self::write([
            'agency_id' => $agencyId,
            'actor_type' => 'system',
            'actor_label' => 'System',
            'event' => $event,
            'subject_type' => $subject ? self::subjectType($subject) : null,
            'subject_id' => $subject?->getKey(),
            'subject_label' => $subjectLabel,
            'metadata' => empty($metadata) ? null : $metadata,
        ]);
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private static function write(array $fields): void
    {
        ActivityLog::create(array_merge([
            'user_id' => null,
            'subject_type' => null,
            'subject_id' => null,
            'subject_label' => null,
            'metadata' => null,
            'ip_address' => null,
            'created_at' => now(),
        ], $fields));
    }

    private static function subjectType(Model $model): string
    {
        return strtolower(class_basename($model));
    }
}
