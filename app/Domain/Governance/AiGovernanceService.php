<?php

namespace App\Domain\Governance;

use App\Ai\Agents\GovernanceAgent;
use App\Enums\GovernanceReportStatus;
use App\Enums\IssueStatus;
use App\Models\AgencyRiskSnapshot;
use App\Models\ContentAudit;
use App\Models\GovernanceReport;
use App\Models\Issue;
use App\Models\Property;
use App\Models\PropertyRiskSnapshot;
use App\Models\RiskAdvisory;
use App\Models\Scan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

class AiGovernanceService
{
    public function __construct() {}

    /**
     * Generate the AI governance report, populating all data and narrative fields.
     */
    public function generate(GovernanceReport $report): void
    {
        $periodFrom = Carbon::parse($report->period_from);
        $periodTo = Carbon::parse($report->period_to);
        $periodLabel = $periodFrom->toDateString().' to '.$periodTo->toDateString();

        if ($report->report_scope === 'agency') {
            [$scopeName, $context, $dataFields] = $this->gatherAgencyContext($report, $periodFrom, $periodTo);
        } else {
            [$scopeName, $context, $dataFields] = $this->gatherPropertyContext($report, $periodFrom, $periodTo);
        }

        $prompt = $this->buildPrompt($context, $scopeName, $periodLabel);

        $response = GovernanceAgent::make()->prompt($prompt);
        $result = json_decode($response->text, true) ?? [];

        $report->update(array_merge($dataFields, [
            'executive_narrative' => $result['executive_narrative'] ?? '',
            'summary_cards' => $result['summary_cards'] ?? [],
            'recommendations' => $result['recommendations'] ?? [],
            'prompt_context' => $prompt,
            'raw_ai_response' => $response->text,
            'status' => GovernanceReportStatus::Completed,
            'generated_at' => Date::now(),
        ]));
    }

    /**
     * Gather context for a property-scoped report.
     *
     * @return array{0: string, 1: array<string, mixed>, 2: array<string, mixed>}
     */
    private function gatherPropertyContext(GovernanceReport $report, Carbon $periodFrom, Carbon $periodTo): array
    {
        $property = Property::withoutGlobalScopes()->findOrFail($report->property_id);
        $scopeName = $property->name.' ('.$property->base_url.')';

        // Risk trend from property snapshots in the period
        $snapshots = PropertyRiskSnapshot::where('property_id', $report->property_id)
            ->whereBetween('snapshot_date', [$periodFrom->toDateString(), $periodTo->toDateString()])
            ->orderBy('snapshot_date')
            ->get(['snapshot_date', 'risk_score', 'open_issue_count'])
            ->map(fn ($s) => [
                'date' => $s->snapshot_date->toDateString(),
                'risk_score' => $s->risk_score,
                'open_issues' => $s->open_issue_count,
            ])
            ->values()
            ->all();

        // Issue severity breakdown (all time, grouped by severity × status)
        $severityBreakdown = $this->buildSeverityBreakdown($report->property_id, null);

        // Remediation progress within the period
        $remediationProgress = $this->buildRemediationProgress($report->property_id, null, $periodFrom, $periodTo);

        // WCAG compliance from active issues
        $complianceStatus = $this->buildComplianceStatus($report->property_id, null);

        // Scan history in period
        $scans = Scan::withoutGlobalScopes()
            ->where('property_id', $report->property_id)
            ->whereBetween('completed_at', [$periodFrom, $periodTo])
            ->orderBy('completed_at')
            ->get(['id', 'pages_scanned', 'total_violations', 'completed_at'])
            ->map(fn ($s) => [
                'id' => $s->id,
                'pages_scanned' => $s->pages_scanned,
                'total_violations' => $s->total_violations,
                'completed_at' => $s->completed_at?->toDateString(),
            ])
            ->values()
            ->all();

        // Latest risk advisory priorities (top 5)
        $latestAdvisory = RiskAdvisory::withoutGlobalScopes()
            ->where('property_id', $report->property_id)
            ->where('status', 'completed')
            ->latest()
            ->first(['id', 'priorities', 'total_recommendations', 'generated_at']);

        $topPriorities = collect($latestAdvisory?->priorities ?? [])->take(5)->values()->all();

        // Latest content audit issues (top 5)
        $latestContentAudit = ContentAudit::withoutGlobalScopes()
            ->where('property_id', $report->property_id)
            ->where('status', 'completed')
            ->latest()
            ->first(['id', 'content_issues', 'total_issues', 'generated_at']);

        $topContentIssues = collect($latestContentAudit?->content_issues ?? [])->take(5)->values()->all();

        // Open issue counts for AI context
        $openIssueCounts = Issue::withoutGlobalScopes()
            ->where('property_id', $report->property_id)
            ->whereIn('status', array_map(fn ($s) => $s->value, IssueStatus::activeStatuses()))
            ->selectRaw('severity, COUNT(*) as total')
            ->groupBy('severity')
            ->pluck('total', 'severity')
            ->all();

        $dataFields = [
            'risk_trend' => $snapshots,
            'severity_breakdown' => $severityBreakdown,
            'remediation_progress' => $remediationProgress,
            'compliance_status' => $complianceStatus,
        ];

        $context = [
            'scope' => 'property',
            'property' => $property->only(['id', 'name', 'base_url']),
            'risk_trend' => $snapshots,
            'severity_breakdown' => $severityBreakdown,
            'remediation_progress' => $remediationProgress,
            'compliance_status' => $complianceStatus,
            'scans_in_period' => $scans,
            'open_issue_counts' => $openIssueCounts,
            'top_risk_priorities' => $topPriorities,
            'top_content_issues' => $topContentIssues,
            'advisory_id' => $latestAdvisory?->id,
            'content_audit_id' => $latestContentAudit?->id,
        ];

        return [$scopeName, $context, $dataFields];
    }

    /**
     * Gather context for an agency-scoped report.
     *
     * @return array{0: string, 1: array<string, mixed>, 2: array<string, mixed>}
     */
    private function gatherAgencyContext(GovernanceReport $report, Carbon $periodFrom, Carbon $periodTo): array
    {
        $agencyId = $report->agency_id;
        $scopeName = 'Agency-wide Report';

        // Agency risk trend
        $snapshots = AgencyRiskSnapshot::where('agency_id', $agencyId)
            ->whereBetween('snapshot_date', [$periodFrom->toDateString(), $periodTo->toDateString()])
            ->orderBy('snapshot_date')
            ->get(['snapshot_date', 'risk_score', 'open_issue_count'])
            ->map(fn ($s) => [
                'date' => $s->snapshot_date->toDateString(),
                'risk_score' => $s->risk_score,
                'open_issues' => $s->open_issue_count,
            ])
            ->values()
            ->all();

        // Severity breakdown across all agency properties
        $severityBreakdown = $this->buildSeverityBreakdown(null, $agencyId);
        $remediationProgress = $this->buildRemediationProgress(null, $agencyId, $periodFrom, $periodTo);
        $complianceStatus = $this->buildComplianceStatus(null, $agencyId);

        // Property count + scan summaries
        $properties = Property::withoutGlobalScopes()
            ->where('agency_id', $agencyId)
            ->orderBy('name')
            ->get(['id', 'name', 'base_url']);

        $openIssueCounts = Issue::withoutGlobalScopes()
            ->where('agency_id', $agencyId)
            ->whereIn('status', array_map(fn ($s) => $s->value, IssueStatus::activeStatuses()))
            ->selectRaw('severity, COUNT(*) as total')
            ->groupBy('severity')
            ->pluck('total', 'severity')
            ->all();

        $dataFields = [
            'risk_trend' => $snapshots,
            'severity_breakdown' => $severityBreakdown,
            'remediation_progress' => $remediationProgress,
            'compliance_status' => $complianceStatus,
        ];

        $context = [
            'scope' => 'agency',
            'properties' => $properties->map->only(['id', 'name', 'base_url'])->values()->all(),
            'property_count' => $properties->count(),
            'risk_trend' => $snapshots,
            'severity_breakdown' => $severityBreakdown,
            'remediation_progress' => $remediationProgress,
            'compliance_status' => $complianceStatus,
            'open_issue_counts' => $openIssueCounts,
        ];

        return [$scopeName, $context, $dataFields];
    }

    /**
     * Build severity × status breakdown for a property or agency.
     *
     * @return array<string, array<string, int>>
     */
    private function buildSeverityBreakdown(?int $propertyId, ?int $agencyId): array
    {
        $query = Issue::withoutGlobalScopes();

        if ($propertyId !== null) {
            $query->where('property_id', $propertyId);
        } else {
            $query->where('agency_id', $agencyId);
        }

        $rows = $query
            ->selectRaw('severity, status, COUNT(*) as total')
            ->groupBy('severity', 'status')
            ->get();

        $breakdown = [];

        foreach ($rows as $row) {
            $sev = $row->severity instanceof \App\Enums\IssueSeverity ? $row->severity->value : (string) $row->severity;
            $status = $row->status instanceof IssueStatus ? $row->status->value : (string) $row->status;
            $bucket = match (true) {
                in_array($status, ['open', 'in_progress']) => 'open',
                $status === 'resolved' => 'resolved',
                default => 'ignored',
            };

            $breakdown[$sev][$bucket] = ($breakdown[$sev][$bucket] ?? 0) + (int) $row->total;
        }

        return $breakdown;
    }

    /**
     * Build remediation progress (resolved vs total per severity) within a date period.
     *
     * @return array<string, array<string, int>>
     */
    private function buildRemediationProgress(?int $propertyId, ?int $agencyId, Carbon $periodFrom, Carbon $periodTo): array
    {
        $query = Issue::withoutGlobalScopes();

        if ($propertyId !== null) {
            $query->where('property_id', $propertyId);
        } else {
            $query->where('agency_id', $agencyId);
        }

        $rows = $query
            ->selectRaw('severity, status, COUNT(*) as total')
            ->groupBy('severity', 'status')
            ->get();

        $progress = [];

        foreach ($rows as $row) {
            $sev = $row->severity instanceof \App\Enums\IssueSeverity ? $row->severity->value : (string) $row->severity;
            $status = $row->status instanceof IssueStatus ? $row->status->value : (string) $row->status;
            $count = (int) $row->total;

            $progress[$sev]['total'] = ($progress[$sev]['total'] ?? 0) + $count;

            if ($status === 'resolved') {
                $progress[$sev]['resolved'] = ($progress[$sev]['resolved'] ?? 0) + $count;
            }
        }

        // Compute rate
        foreach ($progress as $sev => $data) {
            $total = $data['total'] ?? 0;
            $resolved = $data['resolved'] ?? 0;
            $progress[$sev]['resolved'] = $resolved;
            $progress[$sev]['rate'] = $total > 0 ? (int) round(($resolved / $total) * 100) : 0;
        }

        return $progress;
    }

    /**
     * Build WCAG compliance grid by counting distinct wcag_criteria being violated per level.
     *
     * Uses the wcag_criteria column (e.g. "1.4.3 AA", "1.1.1 A") which correctly encodes the
     * conformance level, rather than wcag_category which stores WCAG principles (perceivable,
     * operable, etc.) and is unsuitable for level-based compliance calculations.
     *
     * @return array<string, array<string, int>>
     */
    private function buildComplianceStatus(?int $propertyId, ?int $agencyId): array
    {
        $query = Issue::withoutGlobalScopes();

        if ($propertyId !== null) {
            $query->where('property_id', $propertyId);
        } else {
            $query->where('agency_id', $agencyId);
        }

        // Group by distinct criterion so each SC counts once regardless of how many violations exist.
        $rows = $query
            ->whereNotNull('wcag_criteria')
            ->whereIn('status', array_map(fn ($s) => $s->value, IssueStatus::activeStatuses()))
            ->selectRaw('DISTINCT wcag_criteria')
            ->get();

        $failMap = ['wcag_a' => 0, 'wcag_aa' => 0, 'wcag_aaa' => 0];

        foreach ($rows as $row) {
            $level = $this->parseLevelFromCriteria((string) $row->wcag_criteria);

            if ($level === null) {
                continue;
            }

            $failMap[$level]++;
        }

        // Total success criteria counts per level (WCAG 2.1): A=30, AA=20, AAA=28.
        return [
            'wcag_a' => ['pass' => max(0, 30 - $failMap['wcag_a']), 'fail' => $failMap['wcag_a'], 'partial' => 0],
            'wcag_aa' => ['pass' => max(0, 20 - $failMap['wcag_aa']), 'fail' => $failMap['wcag_aa'], 'partial' => 0],
            'wcag_aaa' => ['pass' => max(0, 28 - $failMap['wcag_aaa']), 'fail' => $failMap['wcag_aaa'], 'partial' => 0],
        ];
    }

    /**
     * Derive the wcag level key from a wcag_criteria string like "1.4.3 AA" or "2.4.3 A".
     */
    private function parseLevelFromCriteria(string $criteria): ?string
    {
        if (str_ends_with($criteria, ' AAA')) {
            return 'wcag_aaa';
        }

        if (str_ends_with($criteria, ' AA')) {
            return 'wcag_aa';
        }

        if (str_ends_with($criteria, ' A')) {
            return 'wcag_a';
        }

        return null;
    }

    /**
     * Build the governance prompt.
     *
     * @param  array<string, mixed>  $context
     */
    public function buildPrompt(array $context, string $scopeName, string $periodLabel): string
    {
        $contextJson = json_encode($context, JSON_PRETTY_PRINT);

        return <<<PROMPT
You are producing an AI Governance Report for "{$scopeName}" covering the period {$periodLabel}.

## Your Task
Using the data provided below, produce:
1. An `executive_narrative` — 3-5 paragraph plain-English summary of the accessibility posture, risk trends, and key findings suitable for a non-technical executive audience.
2. `summary_cards` — 4 KPI cards derived from the provided data. Each card has a `title`, `value` (number), `delta` (positive = improved, negative = worsened), `trend` ("up"|"down"|"stable"), and optional `unit` (e.g. "/100", "%", or null).
3. `recommendations` — up to 5 prioritised, actionable recommendations for the next quarter. Each recommendation must include `source_refs` — an array of traceable evidence links referencing the data you were given. Each source_ref has `type` (one of: "issue", "scan", "audit", "advisory", "content_audit"), `id` (integer), `label` (descriptive text), and `url` (e.g. "/issues/42").

## Data
{$contextJson}

---

Return a single JSON object only (no markdown fences, no prose outside the JSON):

{
  "executive_narrative": "<3-5 paragraphs of plain-English executive summary>",
  "summary_cards": [
    { "title": "...", "value": <number>, "delta": <number>, "trend": "up|down|stable", "unit": "..." }
  ],
  "recommendations": [
    {
      "priority": "high|medium|low",
      "title": "<short action title>",
      "rationale": "<why this matters — reference specific data points>",
      "category": "<category label>",
      "action": "<specific actionable steps>",
      "due_by_quarter": "<e.g. Q2 2026>",
      "source_refs": [
        { "type": "issue|scan|audit|advisory|content_audit", "id": <int>, "label": "<label>", "url": "<url>" }
      ]
    }
  ]
}

Rules:
- Base all numbers, trends, and rationale on the provided data — do not fabricate statistics.
- Only include source_refs that have a real `id` from the data you were given.
- Keep `executive_narrative` free of jargon; assume the reader is a senior manager, not a developer.
- Prioritise recommendations by user impact and compliance risk.
PROMPT;
    }
}
