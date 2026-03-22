import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Download, Printer } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Priority = {
    rank: number;
    rule_key: string;
    severity: string;
    risk_reduction_score: number;
    rationale: string;
    recommended_action: string;
};

type Advisory = {
    id: number;
    status: string;
    priorities: Priority[];
    total_recommendations: number | null;
    issues_analyzed: number | null;
    generated_at: string | null;
    error_message: string | null;
    property: { id: number; name: string; base_url: string } | null;
    organization: { id: number; name: string } | null;
};

type PageProps = { advisory: Advisory };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Risk Advisory', href: '/risk-advisory' },
    { title: 'Details', href: '#' },
];

function severityVariant(sev: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (sev.toLowerCase()) {
        case 'critical':
            return 'destructive';
        case 'serious':
        case 'high':
            return 'default';
        case 'moderate':
        case 'medium':
            return 'secondary';
        default:
            return 'outline';
    }
}

export default function Show({ advisory }: PageProps) {
    const propertyName = advisory.property?.name ?? 'Unknown property';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Risk Advisory — ${propertyName}`} />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex items-start justify-between gap-4 print:hidden">
                    <div className="flex items-center gap-3">
                        <Link href="/risk-advisory" className="text-muted-foreground hover:text-foreground">
                            <ArrowLeft className="h-5 w-5" />
                        </Link>
                        <div>
                            <h1 className="text-xl font-semibold">Risk Advisory — {propertyName}</h1>
                            <p className="text-sm text-muted-foreground">
                                {advisory.property?.base_url}
                                {advisory.generated_at && (
                                    <> &middot; Generated {new Date(advisory.generated_at).toLocaleDateString()}</>
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
                            <a href={`/risk-advisory/${advisory.id}/export/csv`}>
                                <Download className="mr-1.5 h-4 w-4" />
                                CSV
                            </a>
                        </Button>
                        <Button size="sm" variant="outline" asChild>
                            <a href={`/risk-advisory/${advisory.id}/export/json`}>
                                <Download className="mr-1.5 h-4 w-4" />
                                JSON
                            </a>
                        </Button>
                    </div>
                </div>

                {/* Print header */}
                <div className="hidden print:block">
                    <h1 className="text-2xl font-bold">Risk Advisory — {propertyName}</h1>
                    {advisory.generated_at && (
                        <p className="text-sm text-muted-foreground">Generated: {new Date(advisory.generated_at).toLocaleDateString()}</p>
                    )}
                </div>

                {/* Status */}
                {advisory.status === 'failed' && (
                    <div className="rounded-xl border border-destructive/40 bg-destructive/5 p-4">
                        <p className="text-sm font-medium text-destructive">Advisory generation failed.</p>
                        {advisory.error_message && (
                            <p className="mt-1 text-xs text-muted-foreground">{advisory.error_message}</p>
                        )}
                    </div>
                )}

                {/* Summary stats */}
                <div className="grid gap-4 sm:grid-cols-3 print:grid-cols-3">
                    <div className="rounded-xl border bg-card p-4">
                        <p className="text-xs font-medium text-muted-foreground uppercase tracking-wide">Recommendations</p>
                        <p className="mt-1 text-2xl font-bold tabular-nums">{advisory.total_recommendations ?? 0}</p>
                    </div>
                    <div className="rounded-xl border bg-card p-4">
                        <p className="text-xs font-medium text-muted-foreground uppercase tracking-wide">Issues Analyzed</p>
                        <p className="mt-1 text-2xl font-bold tabular-nums">{advisory.issues_analyzed ?? 0}</p>
                    </div>
                    <div className="rounded-xl border bg-card p-4">
                        <p className="text-xs font-medium text-muted-foreground uppercase tracking-wide">Priorities</p>
                        <p className="mt-1 text-2xl font-bold tabular-nums">{advisory.priorities.length}</p>
                    </div>
                </div>

                {/* Priorities table */}
                {advisory.priorities.length > 0 && (
                    <div className="rounded-xl border">
                        <div className="border-b bg-muted/30 px-4 py-3">
                            <h2 className="text-sm font-semibold">Prioritized Recommendations</h2>
                        </div>
                        <table className="w-full text-sm">
                            <thead className="border-b bg-muted/50">
                                <tr className="text-xs text-muted-foreground">
                                    <th className="px-4 py-3 text-left font-medium w-14">Rank</th>
                                    <th className="px-4 py-3 text-left font-medium">Rule Key</th>
                                    <th className="px-4 py-3 text-left font-medium w-24">Severity</th>
                                    <th className="px-4 py-3 text-right font-medium w-20">Risk Score</th>
                                    <th className="px-4 py-3 text-left font-medium">Rationale</th>
                                    <th className="px-4 py-3 text-left font-medium">Recommended Action</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {advisory.priorities.map((p) => (
                                    <tr key={p.rank} className="transition-colors hover:bg-muted/30">
                                        <td className="px-4 py-3 tabular-nums font-medium">{p.rank}</td>
                                        <td className="px-4 py-3 font-mono text-xs">{p.rule_key}</td>
                                        <td className="px-4 py-3">
                                            <Badge variant={severityVariant(p.severity)} className="capitalize">
                                                {p.severity}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3 text-right tabular-nums">{p.risk_reduction_score}</td>
                                        <td className="px-4 py-3 text-muted-foreground text-xs">{p.rationale}</td>
                                        <td className="px-4 py-3 text-xs">{p.recommended_action}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {advisory.priorities.length === 0 && advisory.status === 'completed' && (
                    <div className="rounded-xl border p-8 text-center">
                        <p className="text-sm text-muted-foreground">No priorities generated for this advisory.</p>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
