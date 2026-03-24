import { useCallback, useEffect, useRef, useState } from 'react';
import { Copy, FileText, RefreshCw, Sparkles } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';

type ReadingMetric = {
    page_url: string;
    reading_level: string;
    reading_time: string;
    reading_time_seconds: number;
    word_count: number;
    flesch_score: number | null;
};

type ContentIssue = {
    page_url: string;
    issue_id: number | null;
    rule_key: string;
    category: 'link_text' | 'alt_text' | 'heading_structure' | 'form_label' | 'readability';
    issue_type: string;
    element_html: string | null;
    current_text: string | null;
    issue: string;
    suggestion: string;
    severity: 'critical' | 'serious' | 'moderate' | 'minor';
    wcag_criteria: string | null;
    writer_note: string | null;
    developer_note: string | null;
};

type ContentAuditReport = {
    id: number | null;
    status: string | null;
    content_issues: ContentIssue[];
    total_issues: number;
    pages_analyzed: number;
    reading_metrics: ReadingMetric[];
    avg_reading_level: string | null;
    avg_reading_time_seconds: number | null;
    generated_at: string | null;
    error_message: string | null;
};

type CategoryFilter = 'all' | ContentIssue['category'];

const CATEGORIES: { key: CategoryFilter; label: string }[] = [
    { key: 'all', label: 'All' },
    { key: 'link_text', label: 'Links' },
    { key: 'alt_text', label: 'Images' },
    { key: 'heading_structure', label: 'Headings' },
    { key: 'form_label', label: 'Forms' },
    { key: 'readability', label: 'Readability' },
];

function getCsrfToken(): string {
    return (document.head.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
}

function formatReadingTime(seconds: number): string {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    if (mins > 0 && secs > 0) return `${mins} min ${secs} sec`;
    if (mins > 0) return `${mins} min`;
    return `${secs} sec`;
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

export type ContentAuditPanelProps = {
    propertyId: number;
};

export function ContentAuditPanel({ propertyId }: ContentAuditPanelProps) {
    const [report, setReport] = useState<ContentAuditReport | null>(null);
    const [loadError, setLoadError] = useState<string | null>(null);
    const [generating, setGenerating] = useState(false);
    const [activeCategory, setActiveCategory] = useState<CategoryFilter>('all');
    const [expandedIssue, setExpandedIssue] = useState<number | null>(null);
    const [copiedIndex, setCopiedIndex] = useState<number | null>(null);
    const pollRef = useRef<ReturnType<typeof setInterval> | null>(null);

    const isInProgress = report?.status === 'pending' || report?.status === 'processing';
    const hasResult = report?.status === 'completed';
    const hasFailed = report?.status === 'failed';

    const fetchReport = useCallback(
        async (signal?: AbortSignal) => {
            const res = await fetch(`/api/properties/${propertyId}/content-audit`, {
                headers: { Accept: 'application/json' },
                signal,
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            return res.json() as Promise<ContentAuditReport>;
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
                    setLoadError('Failed to load content audit data.');
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
            const res = await fetch(`/api/properties/${propertyId}/content-audit/generate`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
            });

            if (!res.ok) throw new Error(`HTTP ${res.status}`);

            const pending = (await res.json()) as ContentAuditReport;
            setReport(pending);
        } catch {
            setLoadError('Failed to start content audit. Please try again.');
        } finally {
            setGenerating(false);
        }
    }

    async function handleCopy(text: string, index: number) {
        await navigator.clipboard.writeText(text);
        setCopiedIndex(index);
        setTimeout(() => setCopiedIndex(null), 2000);
    }

    const filteredIssues =
        hasResult && report.content_issues
            ? activeCategory === 'all'
                ? report.content_issues
                : report.content_issues.filter((i) => i.category === activeCategory)
            : [];

    // Group filtered issues by page_url
    const issuesByPage = filteredIssues.reduce<Record<string, ContentIssue[]>>((acc, issue) => {
        (acc[issue.page_url] ??= []).push(issue);
        return acc;
    }, {});

    return (
        <div className="space-y-4">
            {/* Header */}
            <div className="flex items-center justify-between">
                <div>
                    <h3 className="text-sm font-semibold">AI Content Audit</h3>
                    {hasResult && report.generated_at && (
                        <p className="text-xs text-muted-foreground">
                            {report.total_issues} issues &middot; {report.pages_analyzed} pages analysed
                            {report.avg_reading_level && <> &middot; {report.avg_reading_level}</>}
                            {report.avg_reading_time_seconds != null && (
                                <> &middot; ~{formatReadingTime(report.avg_reading_time_seconds)} avg read</>
                            )}
                            {' '}&middot; {new Date(report.generated_at).toLocaleDateString()}
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
                    {hasResult ? 'Regenerate' : 'Generate Audit'}
                </Button>
            </div>

            {loadError && <p className="text-sm text-destructive">{loadError}</p>}
            {hasFailed && <p className="text-sm text-destructive">Audit failed: {report.error_message ?? 'Unknown error'}</p>}

            {/* Skeleton while processing */}
            {isInProgress && (
                <div className="space-y-3">
                    {Array.from({ length: 4 }).map((_, i) => (
                        <Skeleton key={i} className="h-16 w-full animate-pulse rounded-lg" />
                    ))}
                    <p className="text-center text-xs text-muted-foreground">Analysing page content&hellip;</p>
                </div>
            )}

            {/* Empty / no data */}
            {!isInProgress && report !== null && report.status === null && (
                <div className="flex flex-col items-center gap-3 py-10 text-center text-muted-foreground">
                    <FileText className="h-8 w-8 opacity-40" />
                    <p className="text-sm">No content audit yet. Click "Generate Audit" to start.</p>
                </div>
            )}

            {/* Results */}
            {hasResult && (
                <>
                    {/* Category filter tabs */}
                    <div className="flex flex-wrap gap-1">
                        {CATEGORIES.map((cat) => {
                            const count =
                                cat.key === 'all'
                                    ? report.content_issues.length
                                    : report.content_issues.filter((i) => i.category === cat.key).length;
                            return (
                                <button
                                    key={cat.key}
                                    onClick={() => setActiveCategory(cat.key)}
                                    className={`rounded-full px-3 py-1 text-xs font-medium transition-colors ${
                                        activeCategory === cat.key
                                            ? 'bg-primary text-primary-foreground'
                                            : 'bg-muted text-muted-foreground hover:bg-muted/80'
                                    }`}
                                >
                                    {cat.label}
                                    {count > 0 && <span className="ml-1 opacity-70">({count})</span>}
                                </button>
                            );
                        })}
                    </div>

                    {filteredIssues.length === 0 ? (
                        <p className="py-6 text-center text-sm text-muted-foreground">No issues in this category.</p>
                    ) : (
                        <div className="space-y-6">
                            {Object.entries(issuesByPage).map(([pageUrl, pageIssues]) => (
                                <div key={pageUrl}>
                                    <p className="mb-2 truncate text-xs font-semibold text-muted-foreground">{pageUrl}</p>
                                    {/* Reading metrics for this page */}
                                    {(() => {
                                        const metric = report.reading_metrics?.find((m) => m.page_url === pageUrl);
                                        if (!metric) return null;
                                        return (
                                            <div className="mb-2 flex flex-wrap gap-3 text-xs text-muted-foreground">
                                                <span>📖 {metric.reading_level}</span>
                                                <span>⏱ {metric.reading_time}</span>
                                                <span>{metric.word_count.toLocaleString()} words</span>
                                                {metric.flesch_score != null && (
                                                    <span>Flesch {metric.flesch_score.toFixed(0)}</span>
                                                )}
                                            </div>
                                        );
                                    })()}
                                    <div className="space-y-2">
                                        {pageIssues.map((issue, idx) => {
                                            const globalIdx = filteredIssues.indexOf(issue);
                                            const isExpanded = expandedIssue === globalIdx;

                                            return (
                                                <div
                                                    key={idx}
                                                    className="rounded-lg border bg-card transition-colors"
                                                >
                                                    {/* Issue header — click to expand */}
                                                    <button
                                                        className="flex w-full items-start gap-3 px-4 py-3 text-left"
                                                        onClick={() =>
                                                            setExpandedIssue(isExpanded ? null : globalIdx)
                                                        }
                                                    >
                                                        <Badge
                                                            variant={severityVariant(issue.severity)}
                                                            className="mt-0.5 shrink-0 capitalize"
                                                        >
                                                            {issue.severity}
                                                        </Badge>
                                                        <div className="min-w-0 flex-1">
                                                            <p className="text-sm font-medium leading-snug">
                                                                {issue.issue_type}
                                                            </p>
                                                            <p className="mt-0.5 text-xs text-muted-foreground">
                                                                {issue.issue}
                                                            </p>
                                                        </div>
                                                        <div className="flex shrink-0 items-center gap-2">
                                                            {issue.wcag_criteria && (
                                                                <span className="text-xs text-muted-foreground">
                                                                    WCAG {issue.wcag_criteria}
                                                                </span>
                                                            )}
                                                            {issue.issue_id !== null && (
                                                                <a
                                                                    href={`/issues/${issue.issue_id}`}
                                                                    className="text-xs text-primary hover:underline"
                                                                    onClick={(e) => e.stopPropagation()}
                                                                >
                                                                    #{issue.issue_id}
                                                                </a>
                                                            )}
                                                        </div>
                                                    </button>

                                                    {/* Expanded content */}
                                                    {isExpanded && (
                                                        <div className="space-y-3 border-t px-4 py-3">
                                                            {/* Element HTML */}
                                                            {issue.element_html && (
                                                                <div>
                                                                    <div className="mb-1 flex items-center justify-between">
                                                                        <span className="text-xs font-medium text-muted-foreground">
                                                                            Element
                                                                        </span>
                                                                        <button
                                                                            className="flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                                                                            onClick={() =>
                                                                                handleCopy(issue.element_html!, globalIdx)
                                                                            }
                                                                        >
                                                                            <Copy className="h-3 w-3" />
                                                                            {copiedIndex === globalIdx
                                                                                ? 'Copied!'
                                                                                : 'Copy'}
                                                                        </button>
                                                                    </div>
                                                                    <pre className="overflow-x-auto rounded-md bg-muted px-3 py-2 text-xs">
                                                                        <code>{issue.element_html}</code>
                                                                    </pre>
                                                                </div>
                                                            )}

                                                            {/* Suggestion */}
                                                            <div>
                                                                <p className="mb-1 text-xs font-medium text-muted-foreground">
                                                                    Suggestion
                                                                </p>
                                                                <p className="text-sm">{issue.suggestion}</p>
                                                            </div>

                                                            {/* Writer / Developer notes */}
                                                            <div className="grid gap-3 sm:grid-cols-2">
                                                                {issue.writer_note && (
                                                                    <div className="rounded-md border border-blue-200 bg-blue-50 px-3 py-2 dark:border-blue-900 dark:bg-blue-950/30">
                                                                        <p className="mb-1 text-xs font-semibold text-blue-700 dark:text-blue-400">
                                                                            ✍ For writers
                                                                        </p>
                                                                        <p className="text-xs text-blue-800 dark:text-blue-300">
                                                                            {issue.writer_note}
                                                                        </p>
                                                                    </div>
                                                                )}
                                                                {issue.developer_note && (
                                                                    <div className="rounded-md border border-violet-200 bg-violet-50 px-3 py-2 dark:border-violet-900 dark:bg-violet-950/30">
                                                                        <p className="mb-1 text-xs font-semibold text-violet-700 dark:text-violet-400">
                                                                            {'</>'} For developers
                                                                        </p>
                                                                        <p className="text-xs text-violet-800 dark:text-violet-300">
                                                                            {issue.developer_note}
                                                                        </p>
                                                                    </div>
                                                                )}
                                                            </div>
                                                        </div>
                                                    )}
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </>
            )}
        </div>
    );
}
