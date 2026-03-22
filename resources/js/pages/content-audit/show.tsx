import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Download, Printer } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type ContentIssue = {
    type: string;
    severity: string;
    page_url: string;
    description: string;
    element: string;
    recommendation: string;
};

type Audit = {
    id: number;
    status: string;
    content_issues: ContentIssue[];
    total_issues: number | null;
    pages_analyzed: number | null;
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
                            <h1 className="text-xl font-semibold">Content Audit — {propertyName}</h1>
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
                    <div className="rounded-xl border border-destructive/40 bg-destructive/5 p-4">
                        <p className="text-sm font-medium text-destructive">Content audit generation failed.</p>
                        {audit.error_message && (
                            <p className="mt-1 text-xs text-muted-foreground">{audit.error_message}</p>
                        )}
                    </div>
                )}

                {/* Summary stats */}
                <div className="grid gap-4 sm:grid-cols-3 print:grid-cols-3">
                    <div className="rounded-xl border bg-card p-4">
                        <p className="text-xs font-medium text-muted-foreground uppercase tracking-wide">Total Issues</p>
                        <p className="mt-1 text-2xl font-bold tabular-nums">{audit.total_issues ?? 0}</p>
                    </div>
                    <div className="rounded-xl border bg-card p-4">
                        <p className="text-xs font-medium text-muted-foreground uppercase tracking-wide">Pages Analyzed</p>
                        <p className="mt-1 text-2xl font-bold tabular-nums">{audit.pages_analyzed ?? 0}</p>
                    </div>
                    <div className="rounded-xl border bg-card p-4">
                        <p className="text-xs font-medium text-muted-foreground uppercase tracking-wide">Content Issues</p>
                        <p className="mt-1 text-2xl font-bold tabular-nums">{audit.content_issues.length}</p>
                    </div>
                </div>

                {/* Content issues table */}
                {audit.content_issues.length > 0 && (
                    <div className="rounded-xl border">
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
                                            <td className="px-4 py-3 capitalize">{issue.type}</td>
                                            <td className="px-4 py-3">
                                                <Badge variant={severityVariant(issue.severity)} className="capitalize">
                                                    {issue.severity}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-3 font-mono text-xs max-w-[200px] truncate text-muted-foreground">
                                                {issue.page_url}
                                            </td>
                                            <td className="px-4 py-3 text-xs">{issue.description}</td>
                                            <td className="px-4 py-3 font-mono text-xs max-w-[150px] truncate">{issue.element}</td>
                                            <td className="px-4 py-3 text-xs text-muted-foreground">{issue.recommendation}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {audit.content_issues.length === 0 && audit.status === 'completed' && (
                    <div className="rounded-xl border p-8 text-center">
                        <p className="text-sm text-muted-foreground">No content issues found in this audit.</p>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
