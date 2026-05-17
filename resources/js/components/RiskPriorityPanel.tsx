import { useCallback, useEffect, useRef, useState } from 'react';
import { ArrowDown, ArrowUp, ArrowUpDown, RefreshCw, ShieldAlert, Sparkles, Zap } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';

type PriorityItem = {
    rank: number;
    issue_id: number;
    title: string;
    rule_key: string;
    severity: 'critical' | 'serious' | 'moderate' | 'minor';
    risk_reduction_score: number;
    ease_of_remediation: 'easy' | 'moderate' | 'complex';
    user_impact: 'high' | 'medium' | 'low';
    compliance_importance: 'high' | 'medium' | 'low';
    affected_pages: number;
    affected_page_urls: string[];
    quick_win: boolean;
    rationale: string;
};

type AdvisoryReport = {
    id: number | null;
    status: string | null;
    priorities: PriorityItem[];
    total_recommendations: number;
    issues_analyzed: number;
    generated_at: string | null;
    error_message: string | null;
};

type SortKey = keyof Pick<PriorityItem, 'rank' | 'risk_reduction_score' | 'affected_pages' | 'severity' | 'ease_of_remediation'>;
type SortDir = 'asc' | 'desc';

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

function easeVariant(e: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (e) {
        case 'easy':
            return 'default';
        case 'moderate':
            return 'secondary';
        default:
            return 'destructive';
    }
}

function impactVariant(v: string): 'default' | 'secondary' | 'outline' {
    switch (v) {
        case 'high':
            return 'default';
        case 'medium':
            return 'secondary';
        default:
            return 'outline';
    }
}

function riskBarColor(score: number): string {
    if (score >= 70) return 'bg-red-500';
    if (score >= 40) return 'bg-orange-500';
    return 'bg-green-500';
}

const SORT_ORDER: Record<'critical' | 'serious' | 'moderate' | 'minor', number> = {
    critical: 4,
    serious: 3,
    moderate: 2,
    minor: 1,
};

const EASE_ORDER: Record<'easy' | 'moderate' | 'complex', number> = {
    easy: 3,
    moderate: 2,
    complex: 1,
};

function sortItems(items: PriorityItem[], key: SortKey, dir: SortDir): PriorityItem[] {
    return [...items].sort((a, b) => {
        let aVal: number;
        let bVal: number;

        if (key === 'severity') {
            aVal = SORT_ORDER[a.severity] ?? 0;
            bVal = SORT_ORDER[b.severity] ?? 0;
        } else if (key === 'ease_of_remediation') {
            aVal = EASE_ORDER[a.ease_of_remediation] ?? 0;
            bVal = EASE_ORDER[b.ease_of_remediation] ?? 0;
        } else {
            aVal = a[key] as number;
            bVal = b[key] as number;
        }

        return dir === 'asc' ? aVal - bVal : bVal - aVal;
    });
}

export type RiskPriorityPanelProps = {
    propertyId: number;
};

export function RiskPriorityPanel({ propertyId }: RiskPriorityPanelProps) {
    const [report, setReport] = useState<AdvisoryReport | null>(null);
    const [loadError, setLoadError] = useState<string | null>(null);
    const [generating, setGenerating] = useState(false);
    const [expandedRationale, setExpandedRationale] = useState<number | null>(null);
    const [sortKey, setSortKey] = useState<SortKey>('rank');
    const [sortDir, setSortDir] = useState<SortDir>('asc');
    const pollRef = useRef<ReturnType<typeof setInterval> | null>(null);

    const isInProgress = report?.status === 'pending' || report?.status === 'processing';
    const hasResult = report?.status === 'completed';
    const hasFailed = report?.status === 'failed';
    const hasNoData = report !== null && report.status === null;

    const fetchReport = useCallback(
        async (signal?: AbortSignal) => {
            const res = await fetch(`/api/properties/${propertyId}/risk-advisory`, {
                headers: { Accept: 'application/json' },
                signal,
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            return res.json() as Promise<AdvisoryReport>;
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
                    setLoadError('Failed to load risk advisory data.');
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
            const res = await fetch(`/api/properties/${propertyId}/risk-advisory/generate`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
            });

            if (!res.ok) throw new Error(`HTTP ${res.status}`);

            const pending = (await res.json()) as AdvisoryReport;
            setReport(pending);
        } catch {
            setLoadError('Failed to start risk advisory generation. Please try again.');
        } finally {
            setGenerating(false);
        }
    }

    function handleSort(key: SortKey) {
        if (key === sortKey) {
            setSortDir((d) => (d === 'asc' ? 'desc' : 'asc'));
        } else {
            setSortKey(key);
            setSortDir('asc');
        }
    }

    function SortIcon({ col }: { col: SortKey }) {
        if (col !== sortKey) return <ArrowUpDown className="ml-1 inline h-3 w-3 text-muted-foreground/50" />;
        return sortDir === 'asc' ? (
            <ArrowUp className="ml-1 inline h-3 w-3 text-primary" />
        ) : (
            <ArrowDown className="ml-1 inline h-3 w-3 text-primary" />
        );
    }

    const sortedPriorities = hasResult ? sortItems(report.priorities, sortKey, sortDir) : [];

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <div>
                    <h3 className="text-sm font-semibold">Risk Priorities</h3>
                    {hasResult && report.generated_at && (
                        <p className="text-xs text-muted-foreground">
                            {report.total_recommendations} recommendations &middot; {report.issues_analyzed} issues analysed &middot;{' '}
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
                    {hasResult ? 'Regenerate' : 'Generate Priorities'}
                </Button>
            </div>

            {loadError && <p className="text-sm text-destructive">{loadError}</p>}

            {hasFailed && (
                <p className="text-sm text-destructive">Analysis failed: {report.error_message ?? 'Unknown error'}</p>
            )}

            {isInProgress && (
                <div className="space-y-3">
                    {Array.from({ length: 5 }).map((_, i) => (
                        <Skeleton key={i} className="h-12 w-full rounded" />
                    ))}
                    <p className="animate-pulse text-xs text-muted-foreground">Analysing and ranking your open issues&hellip;</p>
                </div>
            )}

            {hasNoData && !loadError && (
                <div className="flex flex-col items-center gap-2 py-8 text-center">
                    <ShieldAlert className="h-8 w-8 text-muted-foreground/50" />
                    <p className="text-sm text-muted-foreground">
                        No risk advisory generated yet. Click &ldquo;Generate Priorities&rdquo; to start.
                    </p>
                </div>
            )}

            {hasResult && report.priorities.length === 0 && (
                <p className="text-sm text-muted-foreground">No open issues found to prioritise.</p>
            )}

            {hasResult && report.priorities.length > 0 && (
                <div className="overflow-x-auto rounded border">
                    <table className="w-full text-xs">
                        <thead className="border-b bg-muted/50">
                            <tr className="text-xs text-muted-foreground">
                                <th
                                    className="cursor-pointer px-3 py-2.5 text-left font-medium hover:text-foreground"
                                    onClick={() => handleSort('rank')}
                                >
                                    # <SortIcon col="rank" />
                                </th>
                                <th className="px-3 py-2.5 text-left font-medium">Issue</th>
                                <th
                                    className="cursor-pointer px-3 py-2.5 text-left font-medium hover:text-foreground"
                                    onClick={() => handleSort('severity')}
                                >
                                    Severity <SortIcon col="severity" />
                                </th>
                                <th
                                    className="cursor-pointer px-3 py-2.5 text-right font-medium hover:text-foreground"
                                    onClick={() => handleSort('risk_reduction_score')}
                                >
                                    Risk Reduction <SortIcon col="risk_reduction_score" />
                                </th>
                                <th
                                    className="cursor-pointer px-3 py-2.5 text-left font-medium hover:text-foreground"
                                    onClick={() => handleSort('ease_of_remediation')}
                                >
                                    Ease <SortIcon col="ease_of_remediation" />
                                </th>
                                <th className="px-3 py-2.5 text-left font-medium">Impact</th>
                                <th className="px-3 py-2.5 text-left font-medium">Compliance</th>
                                <th
                                    className="cursor-pointer px-3 py-2.5 text-right font-medium hover:text-foreground"
                                    onClick={() => handleSort('affected_pages')}
                                >
                                    Pages <SortIcon col="affected_pages" />
                                </th>
                                <th className="px-3 py-2.5 text-center font-medium">Quick Win</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {sortedPriorities.map((item) => (
                                <>
                                    <tr
                                        key={item.issue_id}
                                        className="cursor-pointer transition-colors hover:bg-muted/30"
                                        onClick={() =>
                                            setExpandedRationale((prev) => (prev === item.issue_id ? null : item.issue_id))
                                        }
                                    >
                                        <td className="px-3 py-2.5 tabular-nums text-muted-foreground">
                                            {item.rank}
                                        </td>
                                        <td className="px-3 py-2.5">
                                            <p className="font-medium text-foreground">{item.title}</p>
                                            <code className="text-[10px] text-muted-foreground">{item.rule_key}</code>
                                        </td>
                                        <td className="px-3 py-2.5">
                                            <Badge variant={severityVariant(item.severity)} className="capitalize text-[10px]">
                                                {item.severity}
                                            </Badge>
                                        </td>
                                        <td className="px-3 py-2.5">
                                            <div className="flex items-center justify-end gap-2">
                                                <div className="h-2 w-20 overflow-hidden rounded-full bg-muted">
                                                    <div
                                                        className={`h-full rounded-full ${riskBarColor(item.risk_reduction_score)}`}
                                                        style={{ width: `${item.risk_reduction_score}%` }}
                                                    />
                                                </div>
                                                <span className="w-6 tabular-nums text-right">{item.risk_reduction_score}</span>
                                            </div>
                                        </td>
                                        <td className="px-3 py-2.5">
                                            <Badge variant={easeVariant(item.ease_of_remediation)} className="capitalize text-[10px]">
                                                {item.ease_of_remediation}
                                            </Badge>
                                        </td>
                                        <td className="px-3 py-2.5">
                                            <Badge variant={impactVariant(item.user_impact)} className="capitalize text-[10px]">
                                                {item.user_impact}
                                            </Badge>
                                        </td>
                                        <td className="px-3 py-2.5">
                                            <Badge variant={impactVariant(item.compliance_importance)} className="capitalize text-[10px]">
                                                {item.compliance_importance}
                                            </Badge>
                                        </td>
                                        <td className="px-3 py-2.5 text-right tabular-nums text-muted-foreground">
                                            {item.affected_pages}
                                        </td>
                                        <td className="px-3 py-2.5 text-center">
                                            {item.quick_win && (
                                                <Zap className="mx-auto h-3.5 w-3.5 text-amber-500" aria-label="Quick win" />
                                            )}
                                        </td>
                                    </tr>
                                    {expandedRationale === item.issue_id && (
                                        <tr key={`${item.issue_id}-rationale`} className="bg-muted/20">
                                            <td colSpan={9} className="px-4 py-2.5 text-xs italic leading-relaxed text-muted-foreground">
                                                {item.rationale}
                                            </td>
                                        </tr>
                                    )}
                                </>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}
