import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Download, Printer } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type ContentIssue = {
    issue_type: string;
    severity: string;
    page_url: string;
    issue: string;
    element_html: string | null;
    suggestion: string;
    suggested_alt_text: string | null;
    category: string;
};

type ReadingMetric = {
    page_url: string;
    reading_level: string;
    reading_time: string;
    reading_time_seconds: number;
    word_count: number;
    flesch_score: number | null;
};

type Audit = {
    id: number;
    status: string;
    content_issues: ContentIssue[];
    total_issues: number | null;
    pages_analyzed: number | null;
    reading_metrics: ReadingMetric[];
    avg_reading_level: string | null;
    avg_reading_time_seconds: number | null;
    generated_at: string | null;
    error_message: string | null;
    property: { id: number; name: string; base_url: string } | null;
    organization: { id: number; name: string } | null;
};

type PageProps = { audit: Audit };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Content Audit', href: '/content-audit' },
    { title: 'Details', href: '#' },
];

function severityVariant(sev: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (sev.toLowerCase()) {
        case 'critical':
        case 'high':
            return 'destructive';
        case 'medium':
            return 'default';
        case 'low':
            return 'secondary';
        default:
            return 'outline';
    }
}

function formatReadingTime(seconds: number): string {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    if (mins > 0 && secs > 0) return `${mins} min ${secs} sec`;
    if (mins > 0) return `${mins} min`;
    return `${secs} sec`;
}

export default function Show({ audit }: PageProps) {
    const propertyName = audit.property?.name ?? 'Unknown property';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Content Audit — ${propertyName}`} />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex items-start justify-between gap-4 print:hidden">
                    <div className="flex items-center gap-3">
                        <Link href="/content-audit" className="text-muted-foreground hover:text-foreground">
                            <ArrowLeft className="h-5 w-5" />
                        </Link>
                        <div>
                            <h1 className="text-2xl font-semibold">Content Audit — {propertyName}</h1>
                            <p className="text-sm text-muted-foreground">
                                {audit.property?.base_url}
                                {audit.generated_at && (
                                    <> &middot; Generated {new Date(audit.generated_at).toLocaleDateString()}</>
                                )}
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <Button size="sm" variant="outline" onClick={() => window.print()}>
                            <Printer className="mr-1.5 h-4 w-4" />
                            Print
                        </Button>
                        <Button size="sm" variant="outline" asChild>
                            <a href={`/content-audit/${audit.id}/export/csv`}>
                                <Download className="mr-1.5 h-4 w-4" />
                                CSV
                            </a>
                        </Button>
                        <Button size="sm" variant="outline" asChild>
                            <a href={`/content-audit/${audit.id}/export/json`}>
                                <Download className="mr-1.5 h-4 w-4" />
                                JSON
                            </a>
                        </Button>
                    </div>
                </div>

                {/* Print header */}
                <div className="hidden print:block">
                    <h1 className="text-2xl font-bold">Content Audit — {propertyName}</h1>
                    {audit.generated_at && (
                        <p className="text-sm text-muted-foreground">Generated: {new Date(audit.generated_at).toLocaleDateString()}</p>
                    )}
                </div>

                {/* Status */}
                {audit.status === 'failed' && (
                    <div className="rounded border border-destructive/40 bg-destructive/5 p-4">
                        <p className="text-sm font-medium text-destructive">Content audit generation failed.</p>
                        {audit.error_message && (
                            <p className="mt-1 text-xs text-muted-foreground">{audit.error_message}</p>
                        )}
                    </div>
                )}

                {/* Summary stats */}
                <div className="grid gap-4 sm:grid-cols-3 print:grid-cols-3">
                    <div className="rounded border bg-card p-4">
                        <p className="text-xs font-medium text-muted-foreground uppercase tracking-wide">Total Issues</p>
                        <p className="mt-1 text-2xl font-bold tabular-nums">{audit.total_issues ?? 0}</p>
                    </div>
                    <div className="rounded border bg-card p-4">
                        <p className="text-xs font-medium text-muted-foreground uppercase tracking-wide">Pages Analyzed</p>
                        <p className="mt-1 text-2xl font-bold tabular-nums">{audit.pages_analyzed ?? 0}</p>
                    </div>
                    <div className="rounded border bg-card p-4">
                        <p className="text-xs font-medium text-muted-foreground uppercase tracking-wide">Content Issues</p>
                        <p className="mt-1 text-2xl font-bold tabular-nums">{audit.content_issues.length}</p>
                    </div>
                    <div className="rounded border bg-card p-4">
                        <p className="text-xs font-medium text-muted-foreground uppercase tracking-wide">Avg Reading Level</p>
                        <p className="mt-1 text-2xl font-bold">{audit.avg_reading_level ?? '—'}</p>
                    </div>
                    <div className="rounded border bg-card p-4">
                        <p className="text-xs font-medium text-muted-foreground uppercase tracking-wide">Avg Reading Time</p>
                        <p className="mt-1 text-2xl font-bold">
                            {audit.avg_reading_time_seconds != null ? formatReadingTime(audit.avg_reading_time_seconds) : '—'}
                        </p>
                    </div>
                </div>

                {/* Content issues table */}
                {audit.content_issues.length > 0 && (
                    <div className="rounded border">
                        <div className="border-b bg-muted/30 px-4 py-3">
                            <h2 className="text-sm font-semibold">Content Issues</h2>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="border-b bg-muted/50">
                                    <tr className="text-xs text-muted-foreground">
                                        <th className="px-4 py-3 text-left font-medium w-24">Type</th>
                                        <th className="px-4 py-3 text-left font-medium w-20">Severity</th>
                                        <th className="px-4 py-3 text-left font-medium">Page URL</th>
                                        <th className="px-4 py-3 text-left font-medium">Description</th>
                                        <th className="px-4 py-3 text-left font-medium">Element</th>
                                        <th className="px-4 py-3 text-left font-medium">Recommendation</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {audit.content_issues.map((issue, i) => (
                                        <tr key={i} className="transition-colors hover:bg-muted/30">
                                            <td className="px-4 py-3 capitalize">{issue.issue_type}</td>
                                            <td className="px-4 py-3">
                                                <Badge variant={severityVariant(issue.severity)} className="capitalize">
                                                    {issue.severity}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-3 font-mono text-xs max-w-[200px] truncate text-muted-foreground">
                                                {issue.page_url}
                                            </td>
                                            <td className="px-4 py-3 text-xs">{issue.issue}</td>
                                            <td className="px-4 py-3 font-mono text-xs max-w-[150px] truncate">{issue.element_html}</td>
                                            <td className="px-4 py-3 text-xs text-muted-foreground">
                                                <span>{issue.suggestion}</span>
                                                {issue.category === 'alt_text' && issue.suggested_alt_text != null && (
                                                    <div className="mt-1.5">
                                                        <p className="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">Suggested alt text</p>
                                                        <code className="mt-0.5 block rounded bg-muted px-2 py-1 text-xs text-foreground">
                                                            {issue.suggested_alt_text === '' ? '(empty — decorative image)' : issue.suggested_alt_text}
                                                        </code>
                                                    </div>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {audit.content_issues.length === 0 && audit.status === 'completed' && (
                    <div className="rounded border p-8 text-center">
                        <p className="text-sm text-muted-foreground">No content issues found in this audit.</p>
                    </div>
                )}

                {/* Per-page reading metrics */}
                {audit.reading_metrics.length > 0 && (
                    <div className="rounded border">
                        <div className="border-b bg-muted/30 px-4 py-3">
                            <h2 className="text-sm font-semibold">Reading Metrics by Page</h2>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="border-b bg-muted/50">
                                    <tr className="text-xs text-muted-foreground">
                                        <th className="px-4 py-3 text-left font-medium">Page URL</th>
                                        <th className="px-4 py-3 text-left font-medium">Reading Level</th>
                                        <th className="px-4 py-3 text-left font-medium">Reading Time</th>
                                        <th className="px-4 py-3 text-left font-medium">Word Count</th>
                                        <th className="px-4 py-3 text-left font-medium">Flesch Score</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {audit.reading_metrics.map((metric, i) => (
                                        <tr key={i} className="transition-colors hover:bg-muted/30">
                                            <td className="px-4 py-3 font-mono text-xs max-w-[220px] truncate text-muted-foreground">
                                                {metric.page_url}
                                            </td>
                                            <td className="px-4 py-3 text-xs">{metric.reading_level}</td>
                                            <td className="px-4 py-3 text-xs">{metric.reading_time}</td>
                                            <td className="px-4 py-3 text-xs tabular-nums">{metric.word_count.toLocaleString()}</td>
                                            <td className="px-4 py-3 text-xs tabular-nums">
                                                {metric.flesch_score != null ? metric.flesch_score.toFixed(0) : '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
