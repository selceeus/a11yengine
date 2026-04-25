<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityFeedController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $logs = ActivityLog::query()
            ->latest()
            ->cursorPaginate(25);

        return response()->json([
            'data' => $logs->map(fn (ActivityLog $log) => [
                'id' => $log->id,
                'event' => $log->event->value,
                'event_label' => $log->event->label(),
                'event_category' => $log->event->category(),
                'actor_type' => $log->actor_type,
                'actor_label' => $log->actor_label,
                'subject_type' => $log->subject_type,
                'subject_id' => $log->subject_id,
                'subject_label' => $log->subject_label,
                'metadata' => $log->metadata,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at->toIso8601String(),
            ]),
            'next_cursor' => $logs->nextCursor()?->encode(),
        ]);
    }
}
