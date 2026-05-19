<?php

namespace App\Http\Controllers;

use App\Domain\Audits\CompareAuditTrends;
use App\Enums\AuditStatus;
use App\Enums\ScanStatus;
use App\Models\ActivityLog;
use App\Models\Audit;
use App\Models\Scan;
use App\Models\WcagEmbedding;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly CompareAuditTrends $trends) {}

    public function __invoke(): Response
    {
        return Inertia::render('dashboard', [
            'ragIndexed' => WcagEmbedding::query()->exists(),
            'defaultPropertyId' => Scan::query()
                ->where('status', ScanStatus::Completed)
                ->orderByDesc('completed_at')
                ->value('property_id'),
            'latestAudits' => Inertia::defer(function (): array {
                return Audit::query()
                    ->with('property:id,name')
                    ->where('status', AuditStatus::Completed)
                    ->latest('generated_at')
                    ->limit(3)
                    ->get()
                    ->map(function (Audit $audit): array {
                        $trend = $this->trends->handle($audit, 30);

                        return [
                            'id' => $audit->id,
                            'title' => $audit->title,
                            'overall_score' => $audit->overall_score,
                            'score_delta' => $trend['score_delta'],
                            'trend_direction' => $trend['trend_direction'],
                            'generated_at' => $audit->generated_at?->toIso8601String(),
                            'property' => $audit->property
                                ? ['id' => $audit->property->id, 'name' => $audit->property->name]
                                : null,
                        ];
                    })
                    ->all();
            }),
            'activityFeed' => Inertia::defer(function (): array {
                $paginated = ActivityLog::query()
                    ->latest()
                    ->cursorPaginate(25);

                return [
                    'data' => collect($paginated->items())->map(fn (ActivityLog $log): array => [
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
                    ])->all(),
                    'next_cursor' => $paginated->nextCursor()?->encode(),
                ];
            }),
        ]);
    }
}
