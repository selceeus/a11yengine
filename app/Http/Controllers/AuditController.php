<?php

namespace App\Http\Controllers;

use App\Domain\Audits\CompareAuditTrends;
use App\Enums\AuditStatus;
use App\Http\Requests\StoreAuditRequest;
use App\Jobs\GenerateAiAuditJob;
use App\Models\Audit;
use App\Models\Property;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AuditController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly CompareAuditTrends $trends) {}

    public function index(): InertiaResponse
    {
        $this->authorize('viewAny', Audit::class);

        $audits = Audit::query()
            ->with('property:id,name')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $properties = Property::query()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();

        return Inertia::render('audits/index', [
            'audits' => $audits,
            'properties' => $properties,
        ]);
    }

    public function store(StoreAuditRequest $request): RedirectResponse
    {
        $this->authorize('create', Audit::class);

        $property = Property::findOrFail($request->integer('property_id'));

        $title = $request->string('title')->toString() ?: 'AI Audit — '.$property->name.' — '.now()->format('M j, Y');

        $audit = Audit::create([
            'agency_id' => Auth::user()->agency_id,
            'organization_id' => $property->organization_id,
            'property_id' => $property->id,
            'title' => $title,
            'source_scan_ids' => $request->input('scan_ids') ?? [],
            'status' => AuditStatus::Pending,
        ]);

        dispatch(new GenerateAiAuditJob($audit));

        return redirect()->route('audits.show', $audit);
    }

    public function show(Audit $audit): InertiaResponse
    {
        $this->authorize('view', $audit);

        $audit->load('property:id,name,base_url');

        $trend = $audit->status === AuditStatus::Completed
            ? $this->trends->handle($audit)
            : null;

        return Inertia::render('audits/show', [
            'audit' => $this->formatAudit($audit),
            'trend' => $trend,
        ]);
    }

    public function dashboard(): InertiaResponse
    {
        $this->authorize('viewAny', Audit::class);

        $audits = Audit::query()
            ->with('property:id,name')
            ->where('status', AuditStatus::Completed)
            ->latest('generated_at')
            ->paginate(12)
            ->withQueryString();

        $properties = Property::query()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();

        $auditsWithTrend = $audits->through(function (Audit $audit): array {
            $formatted = $this->formatAudit($audit);
            $trend = $this->trends->handle($audit, 30);
            $formatted['score_delta'] = $trend['score_delta'];
            $formatted['trend_direction'] = $trend['trend_direction'];

            return $formatted;
        });

        return Inertia::render('audits/dashboard', [
            'audits' => $auditsWithTrend,
            'properties' => $properties,
        ]);
    }

    public function destroy(Audit $audit): RedirectResponse
    {
        $this->authorize('delete', $audit);

        $audit->delete();

        return redirect()->route('audits.index');
    }

    public function export(Request $request, Audit $audit, string $format): Response|\Illuminate\Http\JsonResponse|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorize('view', $audit);

        return match ($format) {
            'json' => $this->exportJson($audit),
            'csv' => $this->exportCsv($audit),
            default => $this->exportPdf($audit),
        };
    }

    private function exportJson(Audit $audit): \Illuminate\Http\JsonResponse
    {
        $filename = 'audit-'.$audit->id.'-'.now()->format('Y-m-d').'.json';

        return response()->json($this->formatAudit($audit))
            ->withHeaders(['Content-Disposition' => "attachment; filename=\"{$filename}\""]);
    }

    private function exportCsv(Audit $audit): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $filename = 'audit-'.$audit->id.'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($audit): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Type', 'Priority/Severity', 'Title', 'Description', 'Details']);

            foreach ($audit->top_risks ?? [] as $risk) {
                fputcsv($handle, ['Top Risk', $risk['severity'] ?? '', $risk['title'] ?? '', $risk['impact'] ?? '', 'Occurrences: '.($risk['occurrences'] ?? '')]);
            }

            foreach ($audit->issue_details ?? [] as $issue) {
                fputcsv($handle, ['Issue', $issue['severity'] ?? '', $issue['title'] ?? '', $issue['description'] ?? '', $issue['remediation_hint'] ?? '']);
            }

            foreach ($audit->remediations ?? [] as $rem) {
                fputcsv($handle, ['Remediation', $rem['priority'] ?? '', $rem['title'] ?? '', $rem['description'] ?? '', implode(' | ', $rem['steps'] ?? [])]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function exportPdf(Audit $audit): Response
    {
        $html = view('audits.pdf', ['audit' => $audit])->render();

        return response($html, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatAudit(Audit $audit): array
    {
        return [
            'id' => $audit->id,
            'title' => $audit->title,
            'status' => $audit->status->value,
            'overall_score' => $audit->overall_score,
            'executive_summary' => $audit->executive_summary,
            'compliance_status' => $audit->compliance_status,
            'top_risks' => $audit->top_risks,
            'issue_details' => $audit->issue_details,
            'remediations' => $audit->remediations,
            'summary_statistics' => $audit->summary_statistics,
            'error_message' => $audit->error_message,
            'generated_at' => $audit->generated_at?->toIso8601String(),
            'created_at' => $audit->created_at?->toIso8601String(),
            'property' => $audit->property ? [
                'id' => $audit->property->id,
                'name' => $audit->property->name,
                'base_url' => $audit->property->base_url ?? null,
            ] : null,
        ];
    }
}
