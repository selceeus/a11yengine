import { useEffect, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { ChartOptions } from 'chart.js';
import { Line } from 'react-chartjs-2';
import {
    ArrowLeft,
    ChevronDown,
    ChevronUp,
    ExternalLink,
    Printer,
    Scale,
    TrendingDown,
    TrendingUp,
    Minus,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

// ── Types ─────────────────────────────────────────────────────────────────────

type RiskTrendPoint = { date: string; risk_score: number; open_issues: number };

type SummaryCard = {
    title: string;
    value: number;
    delta: number;
    trend: 'up' | 'down' | 'stable';
    unit?: string | null;
};

type SourceRef = {
    type: 'issue' | 'scan' | 'audit' | 'advisory' | 'content_audit';
    id: number;
    label: string;
    url: string;
};

type Recommendation = {
    priority: 'high' | 'medium' | 'low';
    title: string;
    rationale: string;
    category: string;
    action: string;
    due_by_quarter: string;
    source_refs: SourceRef[];
};

type SeverityBreakdown = Record<string, { open?: number; resolved?: number; ignored?: number }>;
type RemediationProgress = Record<string, { total?: number; resolved?: number; rate?: number }>;
type ComplianceStatus = Record<string, { pass?: number; fail?: number; partial?: number }>;

type LegalPrecedent = {
    case_name: string;
    year: number | null;
    outcome: 'plaintiff_won' | 'defendant_won' | 'settled';
    relevance: string;
};

type GovernanceReport = {
    id: number;
    report_scope: 'property' | 'agency';
    period_from: string;
    period_to: string;
    status: string;
    is_scheduled: boolean;
    generated_at: string | null;
    error_message: string | null;
    executive_narrative: string | null;
    summary_cards: SummaryCard[];
    risk_trend: RiskTrendPoint[];
    severity_breakdown: SeverityBreakdown;
    remediation_progress: RemediationProgress;
    compliance_status: ComplianceStatus;
    legal_risk_rating: 'high' | 'medium' | 'low' | null;
    legal_precedents: LegalPrecedent[];
    recommendations: Recommendation[];
    property: { id: number; name: string; base_url: string } | null;
    agency: { id: number; name: string } | null;
};

type PageProps = { report: GovernanceReport };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Governance', href: '/governance' },
    { title: 'Report', href: '#' },
];

function priorityVariant(p: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (p) {
        case 'high': return 'destructive';
        case 'medium': return 'default';
        default: return 'secondary';
    }
}

function legalRiskColor(rating: string): string {
    switch (rating) {
        case 'high': return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';
        case 'medium': return 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400';
        case 'low': return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400';
        default: return 'bg-muted text-muted-foreground';
    }
}

function outcomeLabel(outcome: string): string {
    switch (outcome) {
        case 'plaintiff_won': return 'Plaintiff Won';
        case 'defendant_won': return 'Defendant Won';
        case 'settled': return 'Settled';
        default: return outcome;
    }
}

function outcomeVariant(outcome: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (outcome) {
        case 'plaintiff_won': return 'destructive';
        case 'settled': return 'default';
        default: return 'secondary';
    }
}

function severityColor(sev: string): string {
    switch (sev) {
        case 'critical': return 'bg-red-500';
        case 'high': return 'bg-orange-400';
        case 'medium': return 'bg-yellow-400';
        case 'low': return 'bg-blue-400';
        default: return 'bg-muted';
    }
}

// ── Inline risk-trend mini-chart (static data) ─────────────────────────────

function RiskTrendMiniChart({ data }: { data: RiskTrendPoint[] }) {
    if (data.length === 0) {
        return <p className="py-8 text-center text-sm text-muted-foreground">No risk trend data for this period.</p>;
    }

    const chartData = {
        labels: data.map((p) =>
            new Date(p.date + 'T00:00:00').toLocaleDateString(undefined, { month: 'short', day: 'numeric' }),
        ),
        datasets: [
            {
                label: 'Risk score',
                data: data.map((p) => p.risk_score),
                borderColor: '#7c3aed',
                backgroundColor: 'rgba(124,58,237,0.08)',
                fill: true,
                tension: 0.3,
                pointRadius: 3,
                pointBackgroundColor: '#7c3aed',
                pointBorderColor: 'white',
                pointBorderWidth: 2,
            },
        ],
    };

    const options: ChartOptions<'line'> = {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: (item) => {
                        const pt = data[item.dataIndex];
                        return [`Risk score: ${pt.risk_score}`, `Open issues: ${pt.open_issues}`];
                    },
                },
            },
        },
        scales: {
            x: { grid: { display: false } },
            y: {
                min: 0,
                ticks: { color: 'var(--muted-foreground)' as string },
                grid: { color: 'rgba(0,0,0,0.06)' },
            },
        },
    };

    return (
        <div className="w-full">
            <Line data={chartData} options={options} aria-label="Risk score trend line chart" role="img" />
        </div>
    );
}

// ── Main page ─────────────────────────────────────────────────────────────────

export default function Show({ report }: PageProps) {
    const [expandedRec, setExpandedRec] = useState<number | null>(null);
    const [deleting, setDeleting] = useState(false);
    const [tab, setTab] = useState<'summary' | 'details'>('summary');

    const isPending = report.status === 'pending' || report.status === 'processing';
    const isCompleted = report.status === 'completed';

    // Poll while pending
    useEffect(() => {
        if (!isPending) return;
        const timer = setInterval(() => {
            router.reload({ only: ['report'] });
        }, 5000);
        return () => clearInterval(timer);
    }, [isPending]);

    function handleDelete() {
        if (!confirm('Delete this report? This cannot be undone.')) return;
        setDeleting(true);
        router.delete(`/governance/${report.id}`, {
            onFinish: () => setDeleting(false),
        });
    }

    const scopeLabel = report.property
        ? report.property.name
        : (report.agency?.name ?? 'Agency-wide');

    const severities = Object.keys(report.severity_breakdown ?? {});
    const wcagLevels = ['wcag_a', 'wcag_aa', 'wcag_aaa'];
    const wcagLabels: Record<string, string> = { wcag_a: 'WCAG A', wcag_aa: 'WCAG AA', wcag_aaa: 'WCAG AAA' };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Governance Report — ${scopeLabel}`} />

            <div className="flex flex-col gap-6 p-6 print:gap-4 print:p-0">
                {/* Header */}
                <div className="flex items-start justify-between gap-4 print:hidden">
                    <div className="flex items-center gap-3">
                        <Link href="/governance" className="text-muted-foreground hover:text-foreground">
                            <ArrowLeft className="h-5 w-5" />
                        </Link>
                        <div>
                            <h1 className="text-2xl font-semibold">Governance Report — {scopeLabel}</h1>
                            <p className="text-sm text-muted-foreground">
                                Period: {report.period_from} &rarr; {report.period_to}
                                {report.is_scheduled && (
                                    <span className="ml-2 rounded-full bg-muted px-2 py-0.5 text-xs">scheduled</span>
                                )}
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        {isCompleted && (
                            <Button size="sm" variant="outline" onClick={() => window.print()}>
                                <Printer className="mr-1.5 h-4 w-4" />
                                Print / PDF
                            </Button>
                        )}
                        <Button size="sm" variant="ghost" className="text-destructive" onClick={handleDelete} disabled={deleting}>
                            {deleting ? <Spinner className="h-4 w-4" /> : 'Delete'}
                        </Button>
                    </div>
                </div>

                {/* Print header (only visible in print) */}
                <div className="hidden print:block">
                    <h1 className="text-2xl font-bold">Governance Report — {scopeLabel}</h1>
                    <p className="text-sm text-muted-foreground">Period: {report.period_from} to {report.period_to}</p>
                    {report.generated_at && (
                        <p className="text-sm text-muted-foreground">Generated: {new Date(report.generated_at).toLocaleDateString()}</p>
                    )}
                </div>

                {/* Tabs */}
                {isCompleted && (
                    <div className="flex gap-1 border-b print:hidden">
                        {(['summary', 'details'] as const).map((t) => (
                            <button
                                key={t}
                                onClick={() => setTab(t)}
                                className={`px-4 py-2 text-sm font-medium capitalize transition-colors border-b-2 -mb-px ${
                                    tab === t
                                        ? 'border-primary text-foreground'
                                        : 'border-transparent text-muted-foreground hover:text-foreground'
                                }`}
                            >
                                {t}
                            </button>
                        ))}
                    </div>
                )}

                {/* Status */}
                {isPending && (
                    <div className="flex items-center gap-2 rounded border bg-muted/30 p-4">
                        <Spinner className="h-5 w-5" />
                        <p className="text-sm">Generating report… this may take up to a minute.</p>
                    </div>
                )}

                {report.status === 'failed' && (
                    <div className="rounded border border-destructive/40 bg-destructive/5 p-4">
                        <p className="text-sm font-medium text-destructive">Report generation failed.</p>
                        {report.error_message && (
                            <p className="mt-1 text-xs text-muted-foreground">{report.error_message}</p>
                        )}
                    </div>
                )}

                {isCompleted && tab === 'summary' && (
                    <>
                        {/* KPI summary cards */}
                        {report.summary_cards.length > 0 && (
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 print:grid-cols-4">
                                {report.summary_cards.map((card, i) => (
                                    <div key={i} className="rounded border bg-card p-4">
                                        <p className="text-xs font-medium text-muted-foreground uppercase tracking-wide">{card.title}</p>
                                        <p className="mt-1 text-2xl font-bold tabular-nums">
                                            {card.value}
                                            {card.unit && <span className="ml-1 text-sm font-normal text-muted-foreground">{card.unit}</span>}
                                        </p>
                                        <div className={`mt-1 flex items-center gap-1 text-xs ${card.trend === 'up' ? 'text-green-600 dark:text-green-400' : card.trend === 'down' ? 'text-red-600 dark:text-red-400' : 'text-muted-foreground'}`}>
                                            {card.trend === 'up' && <TrendingUp className="h-3.5 w-3.5" />}
                                            {card.trend === 'down' && <TrendingDown className="h-3.5 w-3.5" />}
                                            {card.trend === 'stable' && <Minus className="h-3.5 w-3.5" />}
                                            <span>{card.delta > 0 ? '+' : ''}{card.delta} vs previous</span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}

                        {/* Executive narrative */}
                        {report.executive_narrative && (
                            <div className="rounded border bg-card p-6 print:border-0 print:p-0">
                                <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-muted-foreground">Executive Summary</h2>
                                <div className="space-y-3 text-sm leading-relaxed whitespace-pre-line">
                                    {report.executive_narrative}
                                </div>
                            </div>
                        )}

                        {/* Risk trend chart */}
                        {report.risk_trend.length > 0 && (
                            <div className="rounded border bg-card p-6 print:border-0 print:p-0">
                                <h2 className="mb-4 text-sm font-semibold uppercase tracking-wide text-muted-foreground">Risk Score Trend</h2>
                                <RiskTrendMiniChart data={report.risk_trend} />
                            </div>
                        )}

                        {/* WCAG compliance grid */}
                        {Object.keys(report.compliance_status ?? {}).length > 0 && (
                            <div className="rounded border bg-card p-6">
                                <h2 className="mb-4 text-sm font-semibold uppercase tracking-wide text-muted-foreground">WCAG Compliance</h2>
                                <div className="grid gap-4 sm:grid-cols-3">
                                    {wcagLevels.map((level) => {
                                        const data = report.compliance_status[level] ?? {};
                                        const pass = data.pass ?? 0;
                                        const fail = data.fail ?? 0;
                                        const partial = data.partial ?? 0;
                                        const total = pass + fail + partial;
                                        const pct = total > 0 ? Math.round((pass / total) * 100) : 0;
                                        return (
                                            <div key={level} className="rounded border p-4 text-center">
                                                <p className="text-sm font-semibold">{wcagLabels[level] ?? level}</p>
                                                <p className="mt-1 text-3xl font-bold tabular-nums">{pct}<span className="text-base font-normal text-muted-foreground">%</span></p>
                                                <p className="mt-1 text-xs text-muted-foreground">pass rate</p>
                                                <div className="mt-2 flex justify-center gap-3 text-[11px]">
                                                    <span className="text-green-600">{pass} pass</span>
                                                    <span className="text-red-500">{fail} fail</span>
                                                    {partial > 0 && <span className="text-yellow-600">{partial} partial</span>}
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        )}

                        {/* Legal risk rating */}
                        {report.legal_risk_rating && (
                            <div className="rounded border bg-card p-6">
                                <h2 className="mb-4 text-sm font-semibold uppercase tracking-wide text-muted-foreground">Legal Risk Assessment</h2>
                                <div className="flex items-center gap-4">
                                    <div className={`inline-flex items-center gap-2 rounded px-4 py-2 text-sm font-semibold capitalize ${legalRiskColor(report.legal_risk_rating)}`}>
                                        <Scale className="h-4 w-4" />
                                        {report.legal_risk_rating} Risk
                                    </div>
                                    {report.legal_precedents.length > 0 && (
                                        <p className="text-sm text-muted-foreground">
                                            Based on {report.legal_precedents.length} relevant legal precedent{report.legal_precedents.length !== 1 ? 's' : ''}
                                        </p>
                                    )}
                                </div>
                            </div>
                        )}
                    </>
                )}

                {/* ── Details Tab ───────────────────────────────────────────── */}
                {isCompleted && tab === 'details' && (
                    <>
                        {/* KPI summary cards */}
                        {report.summary_cards.length > 0 && (
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 print:grid-cols-4">
                                {report.summary_cards.map((card, i) => (
                                    <div key={i} className="rounded border bg-card p-4">
                                        <p className="text-xs font-medium text-muted-foreground uppercase tracking-wide">{card.title}</p>
                                        <p className="mt-1 text-2xl font-bold tabular-nums">
                                            {card.value}
                                            {card.unit && <span className="ml-1 text-sm font-normal text-muted-foreground">{card.unit}</span>}
                                        </p>
                                        <div className={`mt-1 flex items-center gap-1 text-xs ${card.trend === 'up' ? 'text-green-600 dark:text-green-400' : card.trend === 'down' ? 'text-red-600 dark:text-red-400' : 'text-muted-foreground'}`}>
                                            {card.trend === 'up' && <TrendingUp className="h-3.5 w-3.5" />}
                                            {card.trend === 'down' && <TrendingDown className="h-3.5 w-3.5" />}
                                            {card.trend === 'stable' && <Minus className="h-3.5 w-3.5" />}
                                            <span>{card.delta > 0 ? '+' : ''}{card.delta} vs previous</span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}

                        {/* Executive narrative */}
                        {report.executive_narrative && (
                            <div className="rounded border bg-card p-6 print:border-0 print:p-0">
                                <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-muted-foreground">Executive Summary</h2>
                                <div className="space-y-3 text-sm leading-relaxed whitespace-pre-line">
                                    {report.executive_narrative}
                                </div>
                            </div>
                        )}

                        {/* Two-column: severity breakdown + remediation progress */}
                        <div className="grid gap-4 lg:grid-cols-2 print:grid-cols-2">
                            {/* Severity breakdown */}
                            {severities.length > 0 && (
                                <div className="rounded border bg-card p-6">
                                    <h2 className="mb-4 text-sm font-semibold uppercase tracking-wide text-muted-foreground">Severity Breakdown</h2>
                                    <div className="space-y-3">
                                        {severities.map((sev) => {
                                            const data = report.severity_breakdown[sev] ?? {};
                                            const open = data.open ?? 0;
                                            const resolved = data.resolved ?? 0;
                                            const ignored = data.ignored ?? 0;
                                            const total = open + resolved + ignored;
                                            return (
                                                <div key={sev} className="space-y-1">
                                                    <div className="flex items-center justify-between text-xs">
                                                        <span className="flex items-center gap-1.5 capitalize font-medium">
                                                            <span className={`inline-block h-2 w-2 rounded-full ${severityColor(sev)}`} />
                                                            {sev}
                                                        </span>
                                                        <span className="text-muted-foreground">{open} open / {total} total</span>
                                                    </div>
                                                    <div className="flex h-2 overflow-hidden rounded-full bg-muted">
                                                        {total > 0 && (
                                                            <>
                                                                <div className="bg-green-500" style={{ width: `${(resolved / total) * 100}%` }} />
                                                                <div className="bg-red-400" style={{ width: `${(open / total) * 100}%` }} />
                                                                <div className="bg-muted-foreground/30" style={{ width: `${(ignored / total) * 100}%` }} />
                                                            </>
                                                        )}
                                                    </div>
                                                    <div className="flex gap-3 text-[11px] text-muted-foreground">
                                                        <span className="flex items-center gap-1"><span className="inline-block h-1.5 w-1.5 rounded-full bg-green-500" />Resolved {resolved}</span>
                                                        <span className="flex items-center gap-1"><span className="inline-block h-1.5 w-1.5 rounded-full bg-red-400" />Open {open}</span>
                                                        <span className="flex items-center gap-1"><span className="inline-block h-1.5 w-1.5 rounded-full bg-muted-foreground/30" />Ignored {ignored}</span>
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>
                            )}

                            {/* Remediation progress */}
                            {Object.keys(report.remediation_progress ?? {}).length > 0 && (
                                <div className="rounded border bg-card p-6">
                                    <h2 className="mb-4 text-sm font-semibold uppercase tracking-wide text-muted-foreground">Remediation Progress</h2>
                                    <div className="space-y-3">
                                        {Object.entries(report.remediation_progress).map(([sev, data]) => {
                                            const rate = data.rate ?? 0;
                                            const resolved = data.resolved ?? 0;
                                            const total = data.total ?? 0;
                                            return (
                                                <div key={sev} className="space-y-1">
                                                    <div className="flex items-center justify-between text-xs">
                                                        <span className="flex items-center gap-1.5 capitalize font-medium">
                                                            <span className={`inline-block h-2 w-2 rounded-full ${severityColor(sev)}`} />
                                                            {sev}
                                                        </span>
                                                        <span className="font-semibold tabular-nums">{rate}%</span>
                                                    </div>
                                                    <div className="h-2 overflow-hidden rounded-full bg-muted">
                                                        <div className="h-full rounded-full bg-violet-500 transition-all" style={{ width: `${rate}%` }} />
                                                    </div>
                                                    <p className="text-[11px] text-muted-foreground">{resolved} of {total} resolved</p>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>
                            )}
                        </div>

                        {/* WCAG compliance grid */}
                        {Object.keys(report.compliance_status ?? {}).length > 0 && (
                            <div className="rounded border bg-card p-6">
                                <h2 className="mb-4 text-sm font-semibold uppercase tracking-wide text-muted-foreground">WCAG Compliance</h2>
                                <div className="grid gap-4 sm:grid-cols-3">
                                    {wcagLevels.map((level) => {
                                        const data = report.compliance_status[level] ?? {};
                                        const pass = data.pass ?? 0;
                                        const fail = data.fail ?? 0;
                                        const partial = data.partial ?? 0;
                                        const total = pass + fail + partial;
                                        const pct = total > 0 ? Math.round((pass / total) * 100) : 0;
                                        return (
                                            <div key={level} className="rounded border p-4 text-center">
                                                <p className="text-sm font-semibold">{wcagLabels[level] ?? level}</p>
                                                <p className="mt-1 text-3xl font-bold tabular-nums">{pct}<span className="text-base font-normal text-muted-foreground">%</span></p>
                                                <p className="mt-1 text-xs text-muted-foreground">pass rate</p>
                                                <div className="mt-2 flex justify-center gap-3 text-[11px]">
                                                    <span className="text-green-600">{pass} pass</span>
                                                    <span className="text-red-500">{fail} fail</span>
                                                    {partial > 0 && <span className="text-yellow-600">{partial} partial</span>}
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        )}

                        {/* Legal risk + precedents */}
                        {report.legal_risk_rating && (
                            <div className="rounded border bg-card p-6">
                                <div className="mb-4 flex items-center justify-between">
                                    <h2 className="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Legal Risk Assessment</h2>
                                    <div className={`inline-flex items-center gap-2 rounded px-3 py-1.5 text-sm font-semibold capitalize ${legalRiskColor(report.legal_risk_rating)}`}>
                                        <Scale className="h-4 w-4" />
                                        {report.legal_risk_rating} Risk
                                    </div>
                                </div>

                                {report.legal_precedents.length > 0 && (
                                    <div className="space-y-3">
                                        <p className="text-xs font-medium text-muted-foreground">Relevant Legal Precedents ({report.legal_precedents.length})</p>
                                        {report.legal_precedents.map((prec, i) => (
                                            <div key={i} className="rounded border p-4">
                                                <div className="flex items-start justify-between gap-3">
                                                    <div className="min-w-0">
                                                        <p className="text-sm font-medium">{prec.case_name}</p>
                                                        {prec.year && (
                                                            <p className="mt-0.5 text-xs text-muted-foreground">{prec.year}</p>
                                                        )}
                                                    </div>
                                                    <Badge variant={outcomeVariant(prec.outcome)} className="shrink-0">
                                                        {outcomeLabel(prec.outcome)}
                                                    </Badge>
                                                </div>
                                                <p className="mt-2 text-sm text-muted-foreground">{prec.relevance}</p>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        )}

                        {/* Recommendations */}
                        {report.recommendations.length > 0 && (
                            <div className="rounded border bg-card p-6 print:border-0 print:p-0">
                                <h2 className="mb-4 text-sm font-semibold uppercase tracking-wide text-muted-foreground">Recommendations</h2>
                                <div className="space-y-3">
                                    {report.recommendations.map((rec, i) => {
                                        const isExpanded = expandedRec === i;
                                        return (
                                            <div key={i} className="rounded border">
                                                <button
                                                    className="flex w-full items-start justify-between gap-3 px-4 py-3 text-left"
                                                    onClick={() => setExpandedRec(isExpanded ? null : i)}
                                                    aria-expanded={isExpanded}
                                                >
                                                    <div className="flex min-w-0 items-start gap-2">
                                                        <Badge variant={priorityVariant(rec.priority)} className="mt-0.5 shrink-0 capitalize">
                                                            {rec.priority}
                                                        </Badge>
                                                        <div className="min-w-0">
                                                            <p className="text-sm font-medium leading-snug">{rec.title}</p>
                                                            <p className="mt-0.5 text-xs text-muted-foreground">{rec.category} &middot; {rec.due_by_quarter}</p>
                                                        </div>
                                                    </div>
                                                    {isExpanded ? <ChevronUp className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" /> : <ChevronDown className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />}
                                                </button>

                                                {isExpanded && (
                                                    <div className="space-y-3 border-t px-4 py-3">
                                                        <div>
                                                            <p className="mb-1 text-xs font-medium text-muted-foreground">Rationale</p>
                                                            <p className="text-sm">{rec.rationale}</p>
                                                        </div>
                                                        <div>
                                                            <p className="mb-1 text-xs font-medium text-muted-foreground">Action</p>
                                                            <p className="text-sm">{rec.action}</p>
                                                        </div>
                                                        {rec.source_refs.length > 0 && (
                                                            <div>
                                                                <p className="mb-1.5 text-xs font-medium text-muted-foreground">Evidence</p>
                                                                <div className="flex flex-wrap gap-1.5">
                                                                    {rec.source_refs.map((ref, ri) => (
                                                                        <a
                                                                            key={ri}
                                                                            href={ref.url}
                                                                            className="inline-flex items-center gap-1 rounded-full border bg-muted px-2.5 py-0.5 text-xs hover:bg-muted/80"
                                                                        >
                                                                            <span className="capitalize text-muted-foreground">{ref.type}</span>
                                                                            <span className="font-medium">#{ref.id}</span>
                                                                            <span className="truncate max-w-[160px]">{ref.label}</span>
                                                                            <ExternalLink className="h-3 w-3 shrink-0 text-muted-foreground" />
                                                                        </a>
                                                                    ))}
                                                                </div>
                                                            </div>
                                                        )}
                                                    </div>
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        )}
                    </>
                )}
            </div>
        </AppLayout>
    );
}
