import { useCallback, useEffect, useRef, useState } from 'react';
import { RefreshCw, Sparkles } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';

type ClusterItem = {
    id: number;
    name: string;
    component: string;
    priority: 'high' | 'medium' | 'low';
    severity: 'critical' | 'serious' | 'moderate' | 'minor';
    wcag_categories: string[];
    recommended_fix: string;
    ai_notes: string;
    issue_ids: number[];
};

type ClusterReport = {
    id: number | null;
    status: string | null;
    clusters: ClusterItem[];
    total_clusters: number;
    open_issues_analyzed: number;
    generated_at: string | null;
    error_message: string | null;
};

const CLUSTER_HUES = [
    'border-l-blue-500',
    'border-l-emerald-500',
    'border-l-amber-500',
    'border-l-red-500',
    'border-l-violet-500',
    'border-l-pink-500',
    'border-l-cyan-500',
    'border-l-lime-500',
    'border-l-orange-500',
    'border-l-indigo-500',
] as const;

function getCsrfToken(): string {
    return (document.head.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
}

function severityVariant(s: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (s) {
        case 'critical':
            return 'destructive';
        case 'serious':
            return 'default';
        case 'moderate':
            return 'secondary';
        default:
            return 'outline';
    }
}

function priorityVariant(p: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (p) {
        case 'high':
            return 'destructive';
        case 'medium':
            return 'default';
        default:
            return 'secondary';
    }
}

export type IssueClusterPanelProps = {
    propertyId: number;
};

export function IssueClusterPanel({ propertyId }: IssueClusterPanelProps) {
    const [report, setReport] = useState<ClusterReport | null>(null);
    const [loadError, setLoadError] = useState<string | null>(null);
    const [generating, setGenerating] = useState(false);
    const pollRef = useRef<ReturnType<typeof setInterval> | null>(null);

    const isInProgress = report?.status === 'pending' || report?.status === 'processing';
    const hasResult = report?.status === 'completed';
    const hasFailed = report?.status === 'failed';
    const hasNoData = report !== null && report.status === null;

    const fetchReport = useCallback(
        async (signal?: AbortSignal) => {
            const res = await fetch(`/api/properties/${propertyId}/issue-clusters`, {
                headers: { Accept: 'application/json' },
                signal,
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            return res.json() as Promise<ClusterReport>;
        },
        [propertyId],
    );

    useEffect(() => {
        const ctrl = new AbortController();
        setLoadError(null);

        fetchReport(ctrl.signal)
            .then(setReport)
            .catch((err: unknown) => {
                if (err instanceof Error && err.name !== 'AbortError') {
                    setLoadError('Failed to load cluster data.');
                }
            });

        return () => ctrl.abort();
    }, [fetchReport]);

    useEffect(() => {
        if (pollRef.current) {
            clearInterval(pollRef.current);
            pollRef.current = null;
        }

        if (!isInProgress) return;

        pollRef.current = setInterval(() => {
            fetchReport()
                .then(setReport)
                .catch(() => {
                    // silent poll failure
                });
        }, 3000);

        return () => {
            if (pollRef.current) {
                clearInterval(pollRef.current);
            }
        };
    }, [isInProgress, fetchReport]);

    async function handleGenerate() {
        setGenerating(true);
        setLoadError(null);

        try {
            const res = await fetch(`/api/properties/${propertyId}/issue-clusters/generate`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
            });

            if (!res.ok) throw new Error(`HTTP ${res.status}`);

            const pending = (await res.json()) as ClusterReport;
            setReport(pending);
        } catch {
            setLoadError('Failed to start clustering. Please try again.');
        } finally {
            setGenerating(false);
        }
    }

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <div>
                    <h3 className="text-sm font-semibold">Issue Clusters</h3>
                    {hasResult && report.generated_at && (
                        <p className="text-xs text-muted-foreground">
                            {report.total_clusters} clusters &middot; {report.open_issues_analyzed} issues analysed &middot;{' '}
                            {new Date(report.generated_at).toLocaleDateString()}
                        </p>
                    )}
                </div>

                <Button size="sm" variant="outline" onClick={handleGenerate} disabled={generating || isInProgress}>
                    {generating || isInProgress ? (
                        <Spinner className="mr-1.5 h-3.5 w-3.5" />
                    ) : hasResult ? (
                        <RefreshCw className="mr-1.5 h-3.5 w-3.5" />
                    ) : (
                        <Sparkles className="mr-1.5 h-3.5 w-3.5" />
                    )}
                    {hasResult ? 'Regenerate' : 'Generate Clusters'}
                </Button>
            </div>

            {loadError && <p className="text-sm text-destructive">{loadError}</p>}

            {hasFailed && (
                <p className="text-sm text-destructive">Clustering failed: {report.error_message ?? 'Unknown error'}</p>
            )}

            {isInProgress && (
                <div className="space-y-3">
                    {Array.from({ length: 3 }).map((_, i) => (
                        <Skeleton key={i} className="h-24 w-full rounded-lg" />
                    ))}
                    <p className="animate-pulse text-xs text-muted-foreground">Analysing your open issues&hellip;</p>
                </div>
            )}

            {hasNoData && !loadError && (
                <p className="text-sm text-muted-foreground">
                    No clusters generated yet. Click &ldquo;Generate Clusters&rdquo; to start.
                </p>
            )}

            {hasResult && report.clusters.length > 0 && (
                <div className="space-y-3">
                    {report.clusters.map((cluster, idx) => (
                        <div
                            key={cluster.id}
                            className={`rounded-lg border border-l-4 bg-card p-4 shadow-sm ${CLUSTER_HUES[idx % CLUSTER_HUES.length]}`}
                        >
                            <div className="flex items-start justify-between gap-2">
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-medium">{cluster.name}</p>
                                    <p className="text-xs text-muted-foreground">{cluster.component}</p>
                                </div>
                                <div className="flex flex-shrink-0 items-center gap-1.5">
                                    <Badge variant={severityVariant(cluster.severity)} className="text-xs">
                                        {cluster.severity}
                                    </Badge>
                                    <Badge variant={priorityVariant(cluster.priority)} className="text-xs">
                                        {cluster.priority} priority
                                    </Badge>
                                </div>
                            </div>

                            {cluster.recommended_fix && (
                                <p className="mt-2 text-xs leading-relaxed text-foreground">
                                    <span className="font-medium">Fix: </span>
                                    {cluster.recommended_fix}
                                </p>
                            )}

                            {cluster.ai_notes && (
                                <p className="mt-1 text-xs italic leading-relaxed text-muted-foreground">{cluster.ai_notes}</p>
                            )}

                            {cluster.wcag_categories.length > 0 && (
                                <div className="mt-2 flex flex-wrap gap-1">
                                    {cluster.wcag_categories.map((cat) => (
                                        <span
                                            key={cat}
                                            className="inline-flex items-center rounded-full bg-muted px-2 py-0.5 text-[10px] font-medium text-muted-foreground"
                                        >
                                            {cat}
                                        </span>
                                    ))}
                                </div>
                            )}

                            <p className="mt-2 text-[10px] text-muted-foreground">
                                {cluster.issue_ids.length} issue{cluster.issue_ids.length !== 1 ? 's' : ''}
                            </p>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
