import { useEffect, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Download, Scale, TrendingDown, TrendingUp, Minus } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { AuditScoreTrendChart } from '@/components/charts/AuditScoreTrendChart';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type ComplianceItem = { status: 'pass' | 'partial' | 'fail'; notes: string };
type ComplianceStatus = { wcag_a?: ComplianceItem; wcag_aa?: ComplianceItem; wcag_aaa?: ComplianceItem };
type SummaryStatistics = { total_issues?: number; critical?: number; serious?: number; moderate?: number; minor?: number };
type TopRisk = { rank: number; title: string; severity: string; wcag_criteria: string; impact: string; occurrences: number };
type IssueDetail = { rule_key: string; title: string; severity: string; wcag_criteria: string; description: string; affected_pages: number; remediation_hint: string };
type Remediation = { priority: string; title: string; description: string; steps: string[]; code_example?: string };

type LegalPrecedent = {
    case_name: string;
    year: number | null;
    outcome: 'plaintiff_won' | 'defendant_won' | 'settled';
    relevance: string;
};

type Audit = {
    id: number;
    title: string;
    status: 'pending' | 'processing' | 'completed' | 'failed';
    overall_score: number | null;
    executive_summary: string | null;
    compliance_status: ComplianceStatus | null;
    top_risks: TopRisk[] | null;
    issue_details: IssueDetail[] | null;
    remediations: Remediation[] | null;
    legal_precedents: LegalPrecedent[];
    summary_statistics: SummaryStatistics | null;
    error_message: string | null;
    generated_at: string | null;
    created_at: string;
    property: { id: number; name: string; base_url?: string } | null;
};

type Trend = {
    score_delta: number | null;
    trend_direction: 'improving' | 'declining' | 'stable';
    previous_score: number | null;
    audit_count: number;
    history: { id: number; overall_score: number | null; generated_at: string; title: string }[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Audits', href: '/audits' },
    { title: 'Audit', href: '#' },
];

function severityVariant(s: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (s) {
        case 'critical': return 'destructive';
        case 'serious': return 'default';
        case 'moderate': return 'secondary';
        default: return 'outline';
    }
}

function priorityVariant(p: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (p) {
        case 'high': return 'destructive';
        case 'medium': return 'default';
        default: return 'secondary';
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

function complianceColor(status?: string) {
    switch (status) {
        case 'pass': return 'text-green-600';
        case 'partial': return 'text-amber-600';
        default: return 'text-red-600';
    }
}

function scoreColor(score: number | null) {
    if (score === null) return 'bg-muted text-muted-foreground';
    if (score >= 80) return 'bg-green-100 text-green-800';
    if (score >= 50) return 'bg-amber-100 text-amber-800';
    return 'bg-red-100 text-red-800';
}

export default function Show({ audit, trend }: { audit: Audit; trend: Trend | null }) {
    const isPending = audit.status === 'pending' || audit.status === 'processing';
    const [tab, setTab] = useState<'details' | 'summary'>('details');

    useEffect(() => {
        if (!isPending) return;
        const timer = setInterval(() => {
            router.reload({ only: ['audit'] });
        }, 5000);
        return () => clearInterval(timer);
    }, [isPending]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={audit.title} />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex items-start justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <Link href="/audits" className="text-muted-foreground hover:text-foreground">
                            <ArrowLeft className="h-5 w-5" />
                        </Link>
                        <div>
                            <h1 className="text-xl font-semibold">{audit.title}</h1>
                            {audit.property && (
                                <p className="text-sm text-muted-foreground">{audit.property.name}</p>
                            )}
                        </div>
                    </div>

                    {audit.status === 'completed' && (
                        <div className="flex items-center gap-2">
                            <a href={`/audits/${audit.id}/export/json`} download>
                                <Button variant="outline" size="sm">
                                    <Download className="mr-1.5 h-3.5 w-3.5" />
                                    JSON
                                </Button>
                            </a>
                            <a href={`/audits/${audit.id}/export/csv`} download>
                                <Button variant="outline" size="sm">
                                    <Download className="mr-1.5 h-3.5 w-3.5" />
                                    CSV
                                </Button>
                            </a>
                            <a href={`/audits/${audit.id}/export/pdf`} target="_blank" rel="noopener noreferrer">
                                <Button variant="outline" size="sm">
                                    <Download className="mr-1.5 h-3.5 w-3.5" />
                                    PDF
                                </Button>
                            </a>
                        </div>
                    )}
                </div>

                {/* Tabs — only shown for completed audits */}
                {audit.status === 'completed' && (
                    <div className="flex gap-1 border-b">
                        {(['details', 'summary'] as const).map((t) => (
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

                {/* Pending / Processing */}
                {isPending && (
                    <div className="flex flex-col items-center gap-4 rounded-xl border bg-card py-16">
                        <Spinner className="h-8 w-8 text-primary" />
                        <p className="font-medium">
                            {audit.status === 'processing' ? 'Analysing your site…' : 'Waiting to start…'}
                        </p>
                        <p className="text-sm text-muted-foreground">This page will refresh automatically.</p>
                    </div>
                )}

                {/* Failed */}
                {audit.status === 'failed' && (
                    <div className="rounded-xl border border-destructive/30 bg-destructive/5 p-6">
                        <h2 className="mb-2 font-semibold text-destructive">Audit failed</h2>
                        <p className="text-sm text-muted-foreground">{audit.error_message ?? 'An unknown error occurred.'}</p>
                    </div>
                )}

                {/* ── Summary Tab ─────────────────────────────────────────── */}
                {audit.status === 'completed' && tab === 'summary' && (
                    <>
                        {/* Score + delta */}
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-[auto_1fr]">
                            <div className="flex flex-col items-center justify-center gap-3 rounded-xl border bg-card p-6">
                                <span className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Overall Score</span>
                                <span className={`rounded-full px-5 py-2 text-3xl font-bold ${scoreColor(audit.overall_score)}`}>
                                    {audit.overall_score ?? '—'}
                                </span>
                                <span className="text-xs text-muted-foreground">out of 100</span>
                                {trend && trend.score_delta !== null && (
                                    <div className={`flex items-center gap-1 text-sm font-medium ${
                                        trend.trend_direction === 'improving' ? 'text-green-600'
                                            : trend.trend_direction === 'declining' ? 'text-red-600'
                                            : 'text-muted-foreground'
                                    }`}>
                                        {trend.trend_direction === 'improving' && <TrendingUp className="h-4 w-4" />}
                                        {trend.trend_direction === 'declining' && <TrendingDown className="h-4 w-4" />}
                                        {trend.trend_direction === 'stable' && <Minus className="h-4 w-4" />}
                                        <span>
                                            {trend.score_delta > 0 ? '+' : ''}{trend.score_delta} vs previous
                                        </span>
                                    </div>
                                )}
                            </div>
                            <div className="rounded-xl border bg-card p-6">
                                <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-muted-foreground">Executive Summary</h2>
                                <div className="space-y-2 text-sm leading-relaxed">
                                    {(audit.executive_summary ?? '').split(/\n{2,}/).map((para, i) => (
                                        <p key={i}>{para}</p>
                                    ))}
                                </div>
                            </div>
                        </div>

                        {/* Statistics */}
                        {audit.summary_statistics && (
                            <div className="rounded-xl border bg-card p-6">
                                <h2 className="mb-4 text-sm font-semibold uppercase tracking-wide text-muted-foreground">Summary Statistics</h2>
                                <div className="grid grid-cols-5 gap-3 text-center">
                                    {(
                                        [
                                            { key: 'total_issues', label: 'Total' },
                                            { key: 'critical', label: 'Critical' },
                                            { key: 'serious', label: 'Serious' },
                                            { key: 'moderate', label: 'Moderate' },
                                            { key: 'minor', label: 'Minor' },
                                        ] as { key: keyof SummaryStatistics; label: string }[]
                                    ).map(({ key, label }) => (
                                        <div key={key} className="rounded-lg border p-3">
                                            <div className="text-2xl font-bold">{audit.summary_statistics![key] ?? 0}</div>
                                            <div className="text-xs text-muted-foreground">{label}</div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        {/* WCAG Compliance */}
                        {audit.compliance_status && (
                            <div className="rounded-xl border bg-card p-6">
                                <h2 className="mb-4 text-sm font-semibold uppercase tracking-wide text-muted-foreground">WCAG Compliance</h2>
                                <div className="grid grid-cols-3 gap-4">
                                    {(
                                        [
                                            { key: 'wcag_a', label: 'WCAG A' },
                                            { key: 'wcag_aa', label: 'WCAG AA' },
                                            { key: 'wcag_aaa', label: 'WCAG AAA' },
                                        ] as { key: keyof ComplianceStatus; label: string }[]
                                    ).map(({ key, label }) => {
                                        const item = audit.compliance_status![key];
                                        return (
                                            <div key={key} className="rounded-lg border p-4">
                                                <div className="mb-1 font-semibold">{label}</div>
                                                <div className={`text-sm font-medium capitalize ${complianceColor(item?.status)}`}>
                                                    {item?.status ?? '—'}
                                                </div>
                                                <div className="mt-1 text-xs text-muted-foreground">{item?.notes}</div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        )}

                        {/* Top 5 Critical Risks */}
                        {audit.top_risks && audit.top_risks.length > 0 && (
                            <div className="rounded-xl border bg-card p-6">
                                <h2 className="mb-4 text-sm font-semibold uppercase tracking-wide text-muted-foreground">Top 5 Critical Issues</h2>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead className="border-b text-xs text-muted-foreground">
                                            <tr>
                                                <th className="pb-2 pr-4 text-left font-medium">#</th>
                                                <th className="pb-2 pr-4 text-left font-medium">Risk</th>
                                                <th className="pb-2 pr-4 text-left font-medium">Severity</th>
                                                <th className="pb-2 pr-4 text-left font-medium">WCAG</th>
                                                <th className="pb-2 text-right font-medium">Occurrences</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y">
                                            {audit.top_risks.slice(0, 5).map((risk) => (
                                                <tr key={risk.rank} className="hover:bg-muted/30">
                                                    <td className="py-2 pr-4 tabular-nums text-muted-foreground">{risk.rank}</td>
                                                    <td className="py-2 pr-4 font-medium">{risk.title}</td>
                                                    <td className="py-2 pr-4">
                                                        <Badge variant={severityVariant(risk.severity)}>{risk.severity}</Badge>
                                                    </td>
                                                    <td className="py-2 pr-4 font-mono text-xs">{risk.wcag_criteria}</td>
                                                    <td className="py-2 text-right tabular-nums">{risk.occurrences}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        )}

                        {/* Score Trend Chart */}
                        {audit.property && (
                            <div className="rounded-xl border bg-card p-6">
                                <h2 className="mb-4 text-sm font-semibold uppercase tracking-wide text-muted-foreground">Score Trend</h2>
                                <AuditScoreTrendChart propertyId={audit.property.id} />
                            </div>
                        )}
                    </>
                )}

                {/* ── Details Tab ─────────────────────────────────────────── */}
                {audit.status === 'completed' && tab === 'details' && (
                    <>
                        {/* Score + Summary */}
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-[auto_1fr]">
                            <div className="flex flex-col items-center justify-center rounded-xl border bg-card p-6 gap-2">
                                <span className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Overall Score</span>
                                <span className={`rounded-full px-5 py-2 text-3xl font-bold ${scoreColor(audit.overall_score)}`}>
                                    {audit.overall_score ?? '—'}
                                </span>
                                <span className="text-xs text-muted-foreground">out of 100</span>
                            </div>
                            <div className="rounded-xl border bg-card p-6">
                                <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-muted-foreground">Executive Summary</h2>
                                <div className="space-y-2 text-sm leading-relaxed">
                                    {(audit.executive_summary ?? '').split(/\n{2,}/).map((para, i) => (
                                        <p key={i}>{para}</p>
                                    ))}
                                </div>
                            </div>
                        </div>

                        {/* Statistics */}
                        {audit.summary_statistics && (
                            <div className="rounded-xl border bg-card p-6">
                                <h2 className="mb-4 text-sm font-semibold uppercase tracking-wide text-muted-foreground">Summary Statistics</h2>
                                <div className="grid grid-cols-5 gap-3 text-center">
                                    {(
                                        [
                                            { key: 'total_issues', label: 'Total' },
                                            { key: 'critical', label: 'Critical' },
                                            { key: 'serious', label: 'Serious' },
                                            { key: 'moderate', label: 'Moderate' },
                                            { key: 'minor', label: 'Minor' },
                                        ] as { key: keyof SummaryStatistics; label: string }[]
                                    ).map(({ key, label }) => (
                                        <div key={key} className="rounded-lg border p-3">
                                            <div className="text-2xl font-bold">{audit.summary_statistics![key] ?? 0}</div>
                                            <div className="text-xs text-muted-foreground">{label}</div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        {/* Compliance Status */}
                        {audit.compliance_status && (
                            <div className="rounded-xl border bg-card p-6">
                                <h2 className="mb-4 text-sm font-semibold uppercase tracking-wide text-muted-foreground">WCAG Compliance</h2>
                                <div className="grid grid-cols-3 gap-4">
                                    {(
                                        [
                                            { key: 'wcag_a', label: 'WCAG A' },
                                            { key: 'wcag_aa', label: 'WCAG AA' },
                                            { key: 'wcag_aaa', label: 'WCAG AAA' },
                                        ] as { key: keyof ComplianceStatus; label: string }[]
                                    ).map(({ key, label }) => {
                                        const item = audit.compliance_status![key];
                                        return (
                                            <div key={key} className="rounded-lg border p-4">
                                                <div className="mb-1 font-semibold">{label}</div>
                                                <div className={`text-sm font-medium capitalize ${complianceColor(item?.status)}`}>
                                                    {item?.status ?? '—'}
                                                </div>
                                                <div className="mt-1 text-xs text-muted-foreground">{item?.notes}</div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        )}

                        {/* Top Risks */}
                        {audit.top_risks && audit.top_risks.length > 0 && (
                            <div className="rounded-xl border bg-card p-6">
                                <h2 className="mb-4 text-sm font-semibold uppercase tracking-wide text-muted-foreground">Top Risks</h2>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead className="border-b text-xs text-muted-foreground">
                                            <tr>
                                                <th className="pb-2 pr-4 text-left font-medium">#</th>
                                                <th className="pb-2 pr-4 text-left font-medium">Risk</th>
                                                <th className="pb-2 pr-4 text-left font-medium">Severity</th>
                                                <th className="pb-2 pr-4 text-left font-medium">WCAG</th>
                                                <th className="pb-2 pr-4 text-left font-medium">Impact</th>
                                                <th className="pb-2 text-right font-medium">Occurrences</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y">
                                            {audit.top_risks.map((risk) => (
                                                <tr key={risk.rank} className="hover:bg-muted/30">
                                                    <td className="py-2 pr-4 tabular-nums text-muted-foreground">{risk.rank}</td>
                                                    <td className="py-2 pr-4 font-medium">{risk.title}</td>
                                                    <td className="py-2 pr-4">
                                                        <Badge variant={severityVariant(risk.severity)}>{risk.severity}</Badge>
                                                    </td>
                                                    <td className="py-2 pr-4 font-mono text-xs">{risk.wcag_criteria}</td>
                                                    <td className="py-2 pr-4 text-muted-foreground">{risk.impact}</td>
                                                    <td className="py-2 text-right tabular-nums">{risk.occurrences}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        )}

                        {/* Issue Details */}
                        {audit.issue_details && audit.issue_details.length > 0 && (
                            <div className="rounded-xl border bg-card p-6">
                                <h2 className="mb-4 text-sm font-semibold uppercase tracking-wide text-muted-foreground">Issue Details</h2>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead className="border-b text-xs text-muted-foreground">
                                            <tr>
                                                <th className="pb-2 pr-4 text-left font-medium">Rule</th>
                                                <th className="pb-2 pr-4 text-left font-medium">Title</th>
                                                <th className="pb-2 pr-4 text-left font-medium">Severity</th>
                                                <th className="pb-2 pr-4 text-left font-medium">WCAG</th>
                                                <th className="pb-2 pr-4 text-right font-medium">Pages</th>
                                                <th className="pb-2 text-left font-medium">Quick Fix</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y">
                                            {audit.issue_details.map((issue, i) => (
                                                <tr key={i} className="hover:bg-muted/30">
                                                    <td className="py-2 pr-4 font-mono text-xs text-muted-foreground">{issue.rule_key}</td>
                                                    <td className="py-2 pr-4 font-medium">{issue.title}</td>
                                                    <td className="py-2 pr-4">
                                                        <Badge variant={severityVariant(issue.severity)}>{issue.severity}</Badge>
                                                    </td>
                                                    <td className="py-2 pr-4 font-mono text-xs">{issue.wcag_criteria}</td>
                                                    <td className="py-2 pr-4 text-right tabular-nums">{issue.affected_pages}</td>
                                                    <td className="py-2 text-muted-foreground">{issue.remediation_hint}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        )}

                        {/* Remediations */}
                        {audit.remediations && audit.remediations.length > 0 && (
                            <div className="rounded-xl border bg-card p-6">
                                <h2 className="mb-4 text-sm font-semibold uppercase tracking-wide text-muted-foreground">Remediations</h2>
                                <div className="flex flex-col gap-4">
                                    {audit.remediations.map((rem, i) => (
                                        <div key={i} className="rounded-lg border p-4">
                                            <div className="mb-2 flex items-center gap-2">
                                                <Badge variant={priorityVariant(rem.priority)}>{rem.priority} priority</Badge>
                                                <span className="font-medium">{rem.title}</span>
                                            </div>
                                            <p className="mb-3 text-sm text-muted-foreground">{rem.description}</p>
                                            {rem.steps.length > 0 && (
                                                <ol className="mb-3 list-decimal space-y-1 pl-5 text-sm">
                                                    {rem.steps.map((step, si) => (
                                                        <li key={si}>{step}</li>
                                                    ))}
                                                </ol>
                                            )}
                                            {rem.code_example && (
                                                <pre className="overflow-x-auto rounded-md bg-muted px-4 py-3 text-xs">
                                                    <code>{rem.code_example}</code>
                                                </pre>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        {/* Legal Precedents */}
                        {audit.legal_precedents && audit.legal_precedents.length > 0 && (
                            <div className="rounded-xl border bg-card p-6">
                                <div className="mb-4 flex items-center gap-2">
                                    <Scale className="h-4 w-4 text-muted-foreground" />
                                    <h2 className="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Legal Precedents</h2>
                                </div>
                                <div className="space-y-3">
                                    {audit.legal_precedents.map((prec, i) => (
                                        <div key={i} className="rounded-lg border p-4">
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
                            </div>
                        )}
                    </>
                )}
            </div>
        </AppLayout>
    );
}
