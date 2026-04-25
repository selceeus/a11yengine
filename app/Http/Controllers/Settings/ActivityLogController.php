<?php

namespace App\Http\Controllers\Settings;

use App\Enums\ActivityLogEvent;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $query = ActivityLog::query()->orderByDesc('created_at');

        if ($request->filled('category')) {
            $events = collect(ActivityLogEvent::cases())
                ->filter(fn (ActivityLogEvent $e) => $e->category() === $request->input('category'))
                ->map(fn (ActivityLogEvent $e) => $e->value)
                ->values()
                ->all();

            $query->whereIn('event', $events);
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->input('date_to').' 23:59:59');
        }

        $logs = $query->paginate(50)->through(fn (ActivityLog $log) => [
            'id' => $log->id,
            'created_at' => $log->created_at->toIso8601String(),
            'event' => $log->event->value,
            'event_label' => $log->event->label(),
            'event_category' => $log->event->category(),
            'actor_type' => $log->actor_type,
            'actor_label' => $log->actor_label,
            'subject_type' => $log->subject_type,
            'subject_label' => $log->subject_label,
            'ip_address' => $log->ip_address,
            'metadata' => $log->metadata,
        ]);

        $categories = collect(ActivityLogEvent::cases())
            ->map(fn (ActivityLogEvent $e) => $e->category())
            ->unique()
            ->sort()
            ->values()
            ->all();

        return Inertia::render('settings/activity-log', [
            'logs' => $logs,
            'categories' => $categories,
            'filters' => [
                'category' => $request->input('category', ''),
                'date_from' => $request->input('date_from', ''),
                'date_to' => $request->input('date_to', ''),
            ],
        ]);
    }
}
