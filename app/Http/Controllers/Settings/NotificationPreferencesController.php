<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\NotificationPreferencesUpdateRequest;
use App\Models\NotificationPreference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationPreferencesController extends Controller
{
    private const NOTIFICATION_TYPES = [
        'scan_completed' => 'Scan Completed',
        'issue_assigned' => 'Issue Assigned',
        'weekly_digest' => 'Weekly Digest',
    ];

    private const CHANNELS = [
        'mail' => 'Email',
        'database' => 'In-App',
    ];

    public function edit(Request $request): Response
    {
        $user = $request->user();

        $preferences = NotificationPreference::where('user_id', $user->id)->get();

        $preferencesMap = [];
        foreach ($preferences as $pref) {
            $preferencesMap["{$pref->notification_type}.{$pref->channel}"] = $pref->enabled;
        }

        return Inertia::render('settings/notifications', [
            'preferences' => $preferencesMap,
            'notificationTypes' => self::NOTIFICATION_TYPES,
            'channels' => self::CHANNELS,
        ]);
    }

    public function update(NotificationPreferencesUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        foreach ($validated['preferences'] as $key => $enabled) {
            [$type, $channel] = explode('.', $key);

            NotificationPreference::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'notification_type' => $type,
                    'channel' => $channel,
                ],
                [
                    'agency_id' => $user->agency_id,
                    'enabled' => $enabled,
                ],
            );
        }

        return to_route('notification-preferences.edit');
    }
}
