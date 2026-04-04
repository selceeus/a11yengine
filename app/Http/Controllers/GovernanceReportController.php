<?php

namespace App\Http\Controllers;

use App\Concerns\Exportable;
use App\Enums\GovernanceReportStatus;
use App\Http\Requests\StoreGovernanceReportRequest;
use App\Jobs\GenerateGovernanceReportJob;
use App\Models\GovernanceReport;
use App\Models\Property;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GovernanceReportController extends Controller
{
    use AuthorizesRequests;
    use Exportable;

    public function index(): Response
    {
        $this->authorize('viewAny', GovernanceReport::class);

        $reports = GovernanceReport::query()
            ->with(['property:id,name,base_url'])
            ->latest()
            ->get();

        $properties = Property::query()
            ->select(['id', 'name', 'base_url'])
            ->orderBy('name')
            ->get();

        return Inertia::render('governance/index', [
            'reports' => $reports->map(fn (GovernanceReport $report) => [
                'id' => $report->id,
                'report_scope' => $report->report_scope,
                'period_from' => $report->period_from->toDateString(),
                'period_to' => $report->period_to->toDateString(),
                'status' => $report->status->value,
                'is_scheduled' => $report->is_scheduled,
                'generated_at' => $report->generated_at?->toIso8601String(),
                'error_message' => $report->error_message,
                'property' => $report->property ? [
                    'id' => $report->property->id,
                    'name' => $report->property->name,
                    'base_url' => $report->property->base_url,
                ] : null,
            ]),
            'properties' => $properties->map(fn (Property $property) => [
                'id' => $property->id,
                'name' => $property->name,
                'base_url' => $property->base_url,
            ]),
        ]);
    }

    public function store(StoreGovernanceReportRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $propertyId = $validated['property_id'] ?? null;

        if ($propertyId !== null) {
            $property = Property::query()->findOrFail($propertyId);
            $this->authorize('create', [GovernanceReport::class, $property]);
            $organizationId = $property->organization_id;
            $agencyId = $property->agency_id;
        } else {
            $this->authorize('create', GovernanceReport::class);
            $agencyId = $user->agency_id;
            $organizationId = $user->organization_id;
        }

        $report = GovernanceReport::withoutGlobalScopes()->create([
            'agency_id' => $agencyId,
            'organization_id' => $organizationId,
            'property_id' => $propertyId,
            'report_scope' => $validated['report_scope'],
            'period_from' => $validated['period_from'],
            'period_to' => $validated['period_to'],
            'status' => GovernanceReportStatus::Pending,
            'is_scheduled' => false,
        ]);

        GenerateGovernanceReportJob::dispatch($report);

        return redirect()->route('governance.show', $report);
    }

    public function show(GovernanceReport $report): Response
    {
        $this->authorize('view', $report);

        $report->load(['property:id,name,base_url', 'agency:id,name']);

        return Inertia::render('governance/show', [
            'report' => [
                'id' => $report->id,
                'report_scope' => $report->report_scope,
                'period_from' => $report->period_from->toDateString(),
                'period_to' => $report->period_to->toDateString(),
                'status' => $report->status->value,
                'is_scheduled' => $report->is_scheduled,
                'generated_at' => $report->generated_at?->toIso8601String(),
                'error_message' => $report->error_message,
                'executive_narrative' => $report->executive_narrative,
                'summary_cards' => $report->summary_cards ?? [],
                'risk_trend' => $report->risk_trend ?? [],
                'severity_breakdown' => $report->severity_breakdown ?? [],
                'remediation_progress' => $report->remediation_progress ?? [],
                'compliance_status' => $report->compliance_status ?? [],
                'legal_risk_rating' => $report->legal_risk_rating,
                'legal_precedents' => $report->legal_precedents ?? [],
                'recommendations' => $report->recommendations ?? [],
                'property' => $report->property ? [
                    'id' => $report->property->id,
                    'name' => $report->property->name,
                    'base_url' => $report->property->base_url,
                ] : null,
                'agency' => $report->agency ? [
                    'id' => $report->agency->id,
                    'name' => $report->agency->name,
                ] : null,
            ],
        ]);
    }

    public function destroy(GovernanceReport $report): RedirectResponse
    {
        $this->authorize('delete', $report);

        $report->delete();

        return redirect()->route('governance.index');
    }

    public function export(Request $request, GovernanceReport $report, string $format): \Illuminate\Http\JsonResponse|\Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\Response
    {
        $this->authorize('view', $report);

        $report->load(['property:id,name,base_url', 'agency:id,name']);

        $filename = 'governance-report-'.$report->id.'-'.now()->format('Y-m-d');

        return match ($format) {
            'json' => $this->exportJson([
                'id' => $report->id,
                'report_scope' => $report->report_scope,
                'period_from' => $report->period_from->toDateString(),
                'period_to' => $report->period_to->toDateString(),
                'executive_narrative' => $report->executive_narrative,
                'risk_trend' => $report->risk_trend,
                'severity_breakdown' => $report->severity_breakdown,
                'remediation_progress' => $report->remediation_progress,
                'compliance_status' => $report->compliance_status,
                'legal_risk_rating' => $report->legal_risk_rating,
                'legal_precedents' => $report->legal_precedents,
                'recommendations' => $report->recommendations,
                'summary_cards' => $report->summary_cards,
                'generated_at' => $report->generated_at?->toIso8601String(),
            ], $filename.'.json'),
            'csv' => $this->exportCsv(
                collect($report->recommendations ?? [])->map(fn (array $rec) => [
                    $rec['priority'] ?? '',
                    $rec['title'] ?? '',
                    $rec['category'] ?? '',
                    $rec['rationale'] ?? '',
                    $rec['action'] ?? '',
                    $rec['due_by_quarter'] ?? '',
                ])->all(),
                ['Priority', 'Title', 'Category', 'Rationale', 'Action', 'Due By Quarter'],
                $filename.'.csv'
            ),
            default => $this->exportPdf('governance-reports.pdf', ['report' => $report], $filename.'.html'),
        };
    }
}
