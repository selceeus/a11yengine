<?php

namespace App\Mcp\Resources;

use App\Enums\IssueSeverity;
use App\Enums\ScanStatus;
use App\Models\Agency;
use App\Models\Issue;
use App\Models\Property;
use App\Models\Scan;
use App\Models\Scopes\TenantScope;
use Illuminate\Support\Carbon;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

#[Description('Agency-wide pending alerts: failed scans, new critical issues, overdue properties, and running scans. Use this to proactively triage and action accessibility problems.')]
#[MimeType('application/json')]
class PendingAlertsResource extends Resource implements HasUriTemplate
{
    public function __construct(private readonly Agency $agency) {}

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('agency://pending-alerts');
    }

    public function handle(Request $request): Response
    {
        $alerts = [
            ...$this->failedScanAlerts(),
            ...$this->criticalIssueAlerts(),
            ...$this->overduePropertyAlerts(),
            ...$this->runningScansAlerts(),
        ];

        return Response::json([
            'generated_at' => now()->toIso8601String(),
            'alerts' => $alerts,
            'total_alerts' => count($alerts),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function failedScanAlerts(): array
    {
        return Scan::withoutGlobalScope(TenantScope::class)
            ->where('agency_id', $this->agency->id)
            ->where('status', ScanStatus::Failed)
            ->where('updated_at', '>=', now()->subDays(7))
            ->with('property:id,name,slug')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Scan $scan) => [
                'type' => 'scan_failed',
                'severity' => 'high',
                'property' => [
                    'id' => $scan->property?->id,
                    'name' => $scan->property?->name,
                    'slug' => $scan->property?->slug,
                ],
                'scan_id' => $scan->id,
                'failed_at' => $scan->updated_at?->toIso8601String(),
                'error_message' => $scan->error_message,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function criticalIssueAlerts(): array
    {
        return Issue::withoutGlobalScope(TenantScope::class)
            ->where('agency_id', $this->agency->id)
            ->where('severity', IssueSeverity::Critical)
            ->whereIn('status', ['open', 'in_progress'])
            ->where('first_detected_at', '>=', now()->subDays(7))
            ->with('property:id,name,slug')
            ->selectRaw('property_id, COUNT(*) as issue_count, MIN(first_detected_at) as since')
            ->groupBy('property_id')
            ->get()
            ->map(fn ($row) => [
                'type' => 'critical_issues_detected',
                'severity' => 'critical',
                'property' => [
                    'id' => $row->property?->id,
                    'name' => $row->property?->name,
                    'slug' => $row->property?->slug,
                ],
                'new_issue_count' => (int) $row->issue_count,
                'since' => Carbon::parse($row->since)->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function overduePropertyAlerts(): array
    {
        $cutoff = now()->subDays(30);

        $properties = Property::withoutGlobalScope(TenantScope::class)
            ->where('agency_id', $this->agency->id)
            ->whereDoesntHave('scans', fn ($q) => $q
                ->withoutGlobalScope(TenantScope::class)
                ->where('status', ScanStatus::Completed)
                ->where('completed_at', '>=', $cutoff)
            )
            ->with(['scans' => fn ($q) => $q
                ->withoutGlobalScope(TenantScope::class)
                ->where('status', ScanStatus::Completed)
                ->latest('completed_at')
                ->limit(1)
                ->select('id', 'property_id', 'completed_at'),
            ])
            ->get(['id', 'name', 'slug'])
            ->map(fn (Property $property) => [
                'type' => 'scan_overdue',
                'severity' => 'medium',
                'property' => [
                    'id' => $property->id,
                    'name' => $property->name,
                    'slug' => $property->slug,
                ],
                'last_scanned_at' => $property->scans->first()?->completed_at?->toIso8601String(),
                'days_overdue' => $property->scans->first()
                    ? (int) now()->diffInDays($property->scans->first()->completed_at)
                    : null,
            ])
            ->all();

        return $properties;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function runningScansAlerts(): array
    {
        return Scan::withoutGlobalScope(TenantScope::class)
            ->where('agency_id', $this->agency->id)
            ->where('status', ScanStatus::Running)
            ->with('property:id,name,slug')
            ->orderBy('started_at')
            ->get()
            ->map(fn (Scan $scan) => [
                'type' => 'scan_running',
                'severity' => 'info',
                'property' => [
                    'id' => $scan->property?->id,
                    'name' => $scan->property?->name,
                    'slug' => $scan->property?->slug,
                ],
                'scan_id' => $scan->id,
                'started_at' => $scan->started_at?->toIso8601String(),
            ])
            ->all();
    }
}
