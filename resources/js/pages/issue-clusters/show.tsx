import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import * as IssueClusterController from '@/actions/App/Http/Controllers/IssueClusterController';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Cluster = {
    cluster_name: string;
    common_component: string | null;
    recommended_fix: string;
    severity: 'critical' | 'high' | 'medium' | 'low';
    priority: 'high' | 'medium' | 'low';
    issue_ids: number[];
    wcag_categories: string[];
    affected_pages: number;
    ai_notes: string;
};

type IssueClusterDetail = {
    id: number;
    status: string;
    total_clusters: number | null;
    open_issues_analyzed: number | null;
    generated_at: string | null;
    error_message: string | null;
    clusters: Cluster[];
    property: { id: number; name: string; base_url: string } | null;
};

type PageProps = { cluster: IssueClusterDetail };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Issue Clusters', href: IssueClusterController.index().url },
    { title: 'Details', href: '#' },
];

function severityVariant(sev: Cluster['severity']): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (sev) {
        case 'critical':
            return 'destructive';
        case 'high':
            return 'default';
        case 'medium':
            return 'secondary';
        case 'low':
            return 'outline';
    }
}

function priorityVariant(priority: Cluster['priority']): 'default' | 'secondary' | 'outline' {
    switch (priority) {
        case 'high':
            return 'default';
        case 'medium':
            return 'secondary';
        case 'low':
            return 'outline';
    }
}

export default function Show({ cluster }: PageProps) {
    const propertyName = cluster.property?.name ?? 'Unknown property';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Issue Clusters — ${propertyName}`} />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex items-start justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <Link
                            href={IssueClusterController.index().url}
                            className="text-muted-foreground hover:text-foreground"
                        >
                            <ArrowLeft className="h-5 w-5" />
                        </Link>
                        <div>
                            <h1 className="text-2xl font-semibold">Issue Clusters — {propertyName}</h1>
                            <p className="text-sm text-muted-foreground">
                                {cluster.property?.base_url}
                                {cluster.generated_at && (
                                    <> &middot; Generated {new Date(cluster.generated_at).toLocaleDateString()}</>
                                )}
                            </p>
                        </div>
                    </div>
                </div>

                {/* Error state */}
                {cluster.status === 'failed' && (
                    <div className="rounded border border-destructive/40 bg-destructive/5 p-4">
                        <p className="text-sm font-medium text-destructive">Cluster generation failed.</p>
                        {cluster.error_message && (
                            <p className="mt-1 text-xs text-muted-foreground">{cluster.error_message}</p>
                        )}
                    </div>
                )}

                {/* Summary stats */}
                <div className="grid gap-4 sm:grid-cols-3">
                    <div className="rounded border bg-card p-4">
                        <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Clusters</p>
                        <p className="mt-1 text-2xl font-bold tabular-nums">{cluster.total_clusters ?? 0}</p>
                    </div>
                    <div className="rounded border bg-card p-4">
                        <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Issues Analysed</p>
                        <p className="mt-1 text-2xl font-bold tabular-nums">{cluster.open_issues_analyzed ?? 0}</p>
                    </div>
                    <div className="rounded border bg-card p-4">
                        <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Status</p>
                        <p className="mt-1 text-2xl font-bold capitalize">{cluster.status}</p>
                    </div>
                </div>

                {/* Clusters table */}
                {cluster.clusters.length > 0 && (
                    <div className="rounded border">
                        <div className="border-b bg-muted/30 px-4 py-3">
                            <h2 className="text-sm font-semibold">Identified Clusters</h2>
                        </div>
                        <div className="divide-y">
                            {cluster.clusters.map((c, i) => (
                                <div key={i} className="p-4">
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div className="flex-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <h3 className="font-medium">{c.cluster_name}</h3>
                                                <Badge variant={severityVariant(c.severity)} className="capitalize">
                                                    {c.severity}
                                                </Badge>
                                                <Badge variant={priorityVariant(c.priority)} className="capitalize">
                                                    {c.priority} priority
                                                </Badge>
                                            </div>
                                            {c.common_component && (
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    Component: <span className="font-mono">{c.common_component}</span>
                                                </p>
                                            )}
                                        </div>
                                        <div className="flex shrink-0 gap-4 text-right text-xs text-muted-foreground">
                                            <div>
                                                <p className="font-medium tabular-nums">{c.affected_pages}</p>
                                                <p>pages affected</p>
                                            </div>
                                            <div>
                                                <p className="font-medium tabular-nums">{c.issue_ids.length}</p>
                                                <p>issues</p>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="mt-3 grid gap-3 sm:grid-cols-2">
                                        <div>
                                            <p className="text-xs font-medium text-muted-foreground">Recommended Fix</p>
                                            <p className="mt-1 text-sm">{c.recommended_fix}</p>
                                        </div>
                                        <div>
                                            <p className="text-xs font-medium text-muted-foreground">AI Notes</p>
                                            <p className="mt-1 text-sm text-muted-foreground">{c.ai_notes}</p>
                                        </div>
                                    </div>

                                    {c.wcag_categories.length > 0 && (
                                        <div className="mt-3 flex flex-wrap gap-1.5">
                                            {c.wcag_categories.map((cat) => (
                                                <span
                                                    key={cat}
                                                    className="rounded bg-muted px-2 py-0.5 font-mono text-xs text-muted-foreground"
                                                >
                                                    {cat}
                                                </span>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {cluster.clusters.length === 0 && cluster.status === 'completed' && (
                    <div className="rounded border p-8 text-center">
                        <p className="text-sm text-muted-foreground">No clusters were identified for this analysis.</p>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
