import { useState, useEffect } from 'react';
import { Head, Link, usePoll } from '@inertiajs/react';
import ScanController from '@/actions/App/Http/Controllers/ScanController';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Property = {
    id: number;
    name: string;
    base_url: string;
};

type ScanPage = {
    id: number;
    url: string;
    violations_count: number;
    status: 'completed' | 'failed';
};

type SeverityRow = {
    severity: 'critical' | 'serious' | 'moderate' | 'minor' | 'info';
    count: number;
};

type Scan = {
    id: number;
    status: 'pending' | 'running' | 'completed' | 'failed';
    pages_scanned: number | null;
    total_violations: number | null;
    started_at: string | null;
    completed_at: string | null;
    created_at: string;
    property: Property | null;
    scan_pages: ScanPage[];
};

type LighthouseResult = {
    url: string;
    performance_score: number | null;
    accessibility_score: number | null;
    best_practices_score: number | null;
    seo_score: number | null;
    largest_contentful_paint: number | null;
    first_contentful_paint: number | null;
    total_blocking_time: number | null;
    cumulative_layout_shift: number | null;
};

const SEVERITY_COLOURS: Record<SeverityRow['severity'], string> = {
    critical: 'bg-red-500',
    serious: 'bg-orange-500',
    moderate: 'bg-yellow-500',
    minor: 'bg-blue-400',
    info: 'bg-slate-400',
};

function statusVariant(status: Scan['status']): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (status) {
        case 'completed':
            return 'default';
        case 'running':
            return 'secondary';
        case 'failed':
            return 'destructive';
        default:
            return 'outline';
    }
}

function pageStatusVariant(status: ScanPage['status']): 'default' | 'destructive' {
    return status === 'completed' ? 'default' : 'destructive';
}

export default function Show({
    scan,
    severityBreakdown,
    topRules,
    lighthouseResults,
}: {
    scan: Scan;
    severityBreakdown: SeverityRow[];
    topRules: Record<string, number>;
    lighthouseResults: LighthouseResult[];
}) {
    const isActive = scan.status === 'pending' || scan.status === 'running';
    const { start, stop } = usePoll(3000, {}, { autoStart: false });
    const [tab, setTab] = useState<'wcag' | 'lighthouse'>('wcag');

    useEffect(() => {
        if (isActive) {
            start();
            return () => stop();
        }
    }, [isActive]);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Scans', href: ScanController.index().url },
        { title: scan.property?.name ?? `Scan #${scan.id}`, href: ScanController.show(scan.id).url },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Scan — ${scan.property?.name ?? `#${scan.id}`}`} />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex items-start justify-between">
                    <div className="space-y-1">
                        <h1 className="text-xl font-semibold">
                            {scan.property?.name ?? `Scan #${scan.id}`}
                        </h1>
                        {scan.property && (
                            <p className="text-sm text-muted-foreground">{scan.property.base_url}</p>
                        )}
                    </div>
                    <Badge variant={statusVariant(scan.status)} className="mt-1 capitalize">
                        {scan.status === 'running' && (
                            <span className="mr-1.5 inline-block size-2 animate-pulse rounded-full bg-current" />
                        )}
                        {scan.status}
                    </Badge>
                </div>

                {/* Stats */}
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <StatCard label="Pages scanned" value={scan.pages_scanned ?? '—'} />
                    <StatCard label="Total violations" value={scan.total_violations ?? '—'} />
                    <StatCard
                        label="Started"
                        value={scan.started_at ? new Date(scan.started_at).toLocaleTimeString() : '—'}
                    />
                    <StatCard
                        label="Completed"
                        value={scan.completed_at ? new Date(scan.completed_at).toLocaleTimeString() : '—'}
                    />
                </div>

                {/* Pending / running state */}
                {isActive && (
                    <div className="rounded-xl border bg-muted/40 px-6 py-10 text-center text-sm text-muted-foreground">
                        <span className="inline-block size-2 animate-pulse rounded-full bg-primary align-middle mr-2" />
                        Scan in progress — this page refreshes automatically…
                    </div>
                )}

                 {/* Breakdown — only show once completed */}
                {scan.status === 'completed' && severityBreakdown.length > 0 && (
                    <div className="grid gap-4 sm:grid-cols-2">
                        {/* Severity breakdown */}
                        <div className="rounded-xl border p-4">
                            <h2 className="mb-3 text-sm font-semibold">Violations by severity</h2>
                            <div className="space-y-2">
                                {severityBreakdown.map((row) => {
                                    const total = severityBreakdown.reduce((s, r) => s + r.count, 0);
                                    const pct = total > 0 ? Math.round((row.count / total) * 100) : 0;
                                    return (
                                        <div key={row.severity}>
                                            <div className="mb-1 flex justify-between text-xs">
                                                <span className="capitalize">{row.severity}</span>
                                                <span className="tabular-nums text-muted-foreground">
                                                    {row.count} ({pct}%)
                                                </span>
                                            </div>
                                            <div className="h-2 w-full overflow-hidden rounded-full bg-muted">
                                                <div
                                                    className={`h-2 rounded-full ${SEVERITY_COLOURS[row.severity]}`}
                                                    style={{ width: `${pct}%` }}
                                                />
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>

                        {/* Top rules */}
                        <div className="rounded-xl border p-4">
                            <h2 className="mb-3 text-sm font-semibold">Top violated rules</h2>
                            <ol className="space-y-1.5">
                                {Object.entries(topRules).map(([rule, count], i) => (
                                    <li key={rule} className="flex items-center gap-2 text-xs">
                                        <span className="w-4 shrink-0 text-right tabular-nums text-muted-foreground">
                                            {i + 1}.
                                        </span>
                                        <span className="flex-1 truncate font-mono">{rule}</span>
                                        <span className="tabular-nums font-medium">{count}</span>
                                    </li>
                                ))}
                            </ol>
                        </div>
                    </div>
                )}

                {/* Tabbed results — only shown once completed */}
                {scan.status === 'completed' && (
                    <div className="flex flex-col gap-4">
                        <Tabs value={tab} onValueChange={(v) => setTab(v as 'wcag' | 'lighthouse')}>
                            <TabsList>
                                <TabsTrigger value="wcag">WCAG Scores</TabsTrigger>
                                <TabsTrigger value="lighthouse" disabled={lighthouseResults.length === 0}>
                                    Lighthouse Scores
                                    {lighthouseResults.length === 0 && (
                                        <span className="ml-1.5 text-xs opacity-50">(none)</span>
                                    )}
                                </TabsTrigger>
                            </TabsList>
                        </Tabs>

                        {/* WCAG tab */}
                        {tab === 'wcag' && (
                            scan.scan_pages.length > 0 ? (
                                <div className="rounded-xl border">
                                    <table className="w-full text-sm">
                                        <caption className="px-4 py-3">WCAG Scoring Results</caption>
                                        <thead className="border-b bg-muted/50">
                                            <tr className="text-xs text-muted-foreground">
                                                <th className="px-4 py-3 text-left font-medium">Page URL</th>
                                                <th className="px-4 py-3 text-left font-medium">Status</th>
                                                <th className="px-4 py-3 text-right font-medium">Violations</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y">
                                            {scan.scan_pages.map((page) => (
                                                <tr key={page.id} className="transition-colors hover:bg-muted/30">
                                                    <td className="max-w-sm truncate px-4 py-3 font-mono text-xs">
                                                        <a
                                                            href={page.url}
                                                            target="_blank"
                                                            rel="noreferrer"
                                                            className="hover:underline"
                                                        >
                                                            {page.url}
                                                        </a>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <Badge variant={pageStatusVariant(page.status)}>
                                                            {page.status}
                                                        </Badge>
                                                    </td>
                                                    <td className="px-4 py-3 text-right tabular-nums">
                                                        {page.violations_count}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            ) : (
                                <div className="rounded-xl border px-6 py-10 text-center text-sm text-muted-foreground">
                                    No pages were recorded for this scan.
                                </div>
                            )
                        )}

                        {/* Lighthouse tab */}
                        {tab === 'lighthouse' && lighthouseResults.length > 0 && (
                            <div className="rounded-xl border">
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <caption className="px-4 py-3">Lighthouse Scoring Results</caption>
                                        <thead className="border-b bg-muted/50">
                                            <tr className="text-xs text-muted-foreground">
                                                <th className="px-4 py-3 text-left font-medium">Page URL</th>
                                                <th className="px-4 py-3 text-right font-medium">Perf</th>
                                                <th className="px-4 py-3 text-right font-medium">A11y</th>
                                                <th className="px-4 py-3 text-right font-medium">Best Practices</th>
                                                <th className="px-4 py-3 text-right font-medium">SEO</th>
                                                <th className="px-4 py-3 text-right font-medium">LCP (ms)</th>
                                                <th className="px-4 py-3 text-right font-medium">FCP (ms)</th>
                                                <th className="px-4 py-3 text-right font-medium">TBT (ms)</th>
                                                <th className="px-4 py-3 text-right font-medium">CLS</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y">
                                            {lighthouseResults.map((result) => (
                                                <tr key={result.url} className="transition-colors hover:bg-muted/30">
                                                    <td className="max-w-sm truncate px-4 py-3 font-mono text-xs">
                                                        <a href={result.url} target="_blank" rel="noreferrer" className="hover:underline">
                                                            {result.url}
                                                        </a>
                                                    </td>
                                                    <td className="px-4 py-3 text-right tabular-nums">
                                                        <ScoreChip score={result.performance_score} />
                                                    </td>
                                                    <td className="px-4 py-3 text-right tabular-nums">
                                                        <ScoreChip score={result.accessibility_score} />
                                                    </td>
                                                    <td className="px-4 py-3 text-right tabular-nums">
                                                        <ScoreChip score={result.best_practices_score} />
                                                    </td>
                                                    <td className="px-4 py-3 text-right tabular-nums">
                                                        <ScoreChip score={result.seo_score} />
                                                    </td>
                                                    <td className="px-4 py-3 text-right tabular-nums text-muted-foreground">
                                                        {result.largest_contentful_paint?.toFixed(0) ?? '—'}
                                                    </td>
                                                    <td className="px-4 py-3 text-right tabular-nums text-muted-foreground">
                                                        {result.first_contentful_paint?.toFixed(0) ?? '—'}
                                                    </td>
                                                    <td className="px-4 py-3 text-right tabular-nums text-muted-foreground">
                                                        {result.total_blocking_time?.toFixed(0) ?? '—'}
                                                    </td>
                                                    <td className="px-4 py-3 text-right tabular-nums text-muted-foreground">
                                                        {result.cumulative_layout_shift?.toFixed(3) ?? '—'}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        )}
                    </div>
                )}

                {/* In-progress pages list (shown during active scan) */}
                {isActive && scan.scan_pages.length > 0 && (
                    <div className="rounded-xl border">
                        <table className="w-full text-sm">
                            <thead className="border-b bg-muted/50">
                                <tr className="text-xs text-muted-foreground">
                                    <th className="px-4 py-3 text-left font-medium">Page URL</th>
                                    <th className="px-4 py-3 text-left font-medium">Status</th>
                                    <th className="px-4 py-3 text-right font-medium">Violations</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {scan.scan_pages.map((page) => (
                                    <tr key={page.id} className="transition-colors hover:bg-muted/30">
                                        <td className="max-w-sm truncate px-4 py-3 font-mono text-xs">
                                            <a
                                                href={page.url}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="hover:underline"
                                            >
                                                {page.url}
                                            </a>
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge variant={pageStatusVariant(page.status)}>
                                                {page.status}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3 text-right tabular-nums">
                                            {page.violations_count}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <div className="text-sm">
                    <Link href={ScanController.index().url} className="text-primary hover:underline">
                        ← Back to scans
                    </Link>
                </div>
            </div>
        </AppLayout>
    );
}

function ScoreChip({ score }: { score: number | null }) {
    if (score === null) return <span className="text-muted-foreground">—</span>;

    const colour =
        score >= 90 ? 'text-green-600' :
        score >= 50 ? 'text-orange-500' :
        'text-red-600';

    return <span className={`font-semibold tabular-nums ${colour}`}>{score}</span>;
}

function StatCard({ label, value }: { label: string; value: string | number }) {
    return (
        <div className="rounded-xl border bg-card p-4">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="mt-1 text-xl font-semibold tabular-nums">{value}</p>
        </div>
    );
}
