import { useState, useEffect } from 'react';
import { Head, Link, router, usePoll } from '@inertiajs/react';
import ScanController from '@/actions/App/Http/Controllers/ScanController';
import PdfDocumentController from '@/actions/App/Http/Controllers/PdfDocumentController';
import AuditController from '@/actions/App/Http/Controllers/AuditController';
import ContentAuditController from '@/actions/App/Http/Controllers/ContentAuditController';
import RiskAdvisoryController from '@/actions/App/Http/Controllers/RiskAdvisoryController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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

type ScanJourneyStep = {
    id: number;
    position: number;
    label: string;
    url: string;
};

type Scan = {
    id: number;
    status: 'pending' | 'running' | 'completed' | 'failed';
    pages_scanned: number | null;
    pages_discovered: number | null;
    total_violations: number | null;
    error_message: string | null;
    started_at: string | null;
    completed_at: string | null;
    created_at: string;
    target_url: string | null;
    scan_journey: { id: number; name: string; steps: ScanJourneyStep[] } | null;
    property: Property | null;
    scan_pages: ScanPage[];
};

type LighthouseResult = {
    url: string;
    form_factor: 'mobile' | 'desktop';
    performance_score: number | null;
    accessibility_score: number | null;
    best_practices_score: number | null;
    seo_score: number | null;
    largest_contentful_paint: number | null;
    first_contentful_paint: number | null;
    total_blocking_time: number | null;
    cumulative_layout_shift: number | null;
};

type Delta = {
    new_count: number;
    resolved_count: number;
    risk_trend: number | null;
    lighthouse_accessibility_delta: number | null;
    experience_score_delta: number | null;
};

type ExperiencePillars = {
    experience_score: number;
    accessibility_score: number | null;
    performance_score: number | null;
    best_practices_score: number | null;
    seo_score: number | null;
} | null;

type PdfDocument = {
    id: number;
    url: string;
    filename: string | null;
    status: 'pending' | 'scanning' | 'completed' | 'failed';
    violation_count: number;
    scanned_at: string | null;
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

function pdfStatusVariant(status: PdfDocument['status']): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (status) {
        case 'completed': return 'default';
        case 'scanning': return 'secondary';
        case 'failed': return 'destructive';
        default: return 'outline';
    }
}

export default function Show({
    scan,
    severityBreakdown,
    topRules,
    lighthouseResults,
    delta,
    experiencePillars,
    pdfDocuments,
    pdfScannerAvailable,
}: {
    scan: Scan;
    severityBreakdown: SeverityRow[];
    topRules: Record<string, number>;
    lighthouseResults: LighthouseResult[];
    delta: Delta | null;
    experiencePillars: ExperiencePillars;
    pdfDocuments: PdfDocument[];
    pdfScannerAvailable: boolean;
}) {
    const isActive = scan.status === 'pending' || scan.status === 'running';
    const { start, stop } = usePoll(3000, {}, { autoStart: false });
    const [tab, setTab] = useState<'wcag' | 'lighthouse' | 'pdfs'>('wcag');
    const [lighthouseFormFactor, setLighthouseFormFactor] = useState<'mobile' | 'desktop'>('mobile');

    const mobileLighthouse = lighthouseResults.filter((r) => r.form_factor === 'mobile');
    const desktopLighthouse = lighthouseResults.filter((r) => r.form_factor === 'desktop');

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
                        <h1 className="text-2xl font-semibold pb-2">
                            {scan.property?.name ?? `Scan #${scan.id}`}
                        </h1>
                        {scan.property && (
                            <p className="text-sm text-muted-foreground">{scan.property.base_url}</p>
                        )}
                        {scan.target_url && (
                            <p className="text-sm text-muted-foreground">Single page: {scan.target_url}</p>
                        )}
                        {scan.scan_journey && (
                            <p className="text-sm text-muted-foreground">Journey: {scan.scan_journey.name} ({scan.scan_journey.steps.length} steps)</p>
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

                {/* Delta bar — changes since last scan */}
                {delta && scan.status === 'completed' && (
                    <div className="flex flex-wrap items-center gap-4 rounded-xl border bg-muted/30 px-5 py-3 text-sm">
                        <span className="font-medium text-foreground">Changes vs. previous scan</span>
                        <div className="flex items-center gap-1.5">
                            <span className="text-red-600 font-medium">+{delta.new_count}</span>
                            <span className="text-muted-foreground">new</span>
                        </div>
                        <div className="flex items-center gap-1.5">
                            <span className="text-green-600 font-medium">−{delta.resolved_count}</span>
                            <span className="text-muted-foreground">resolved</span>
                        </div>
                        {delta.risk_trend !== null && (
                            <div className="flex items-center gap-1.5">
                                <span className={delta.risk_trend > 0 ? 'text-red-600 font-medium' : 'text-green-600 font-medium'}>
                                    {delta.risk_trend > 0 ? '↑' : '↓'} {Math.abs(delta.risk_trend).toFixed(2)}
                                </span>
                                <span className="text-muted-foreground">risk</span>
                            </div>
                        )}
                        {delta.lighthouse_accessibility_delta !== null && (
                            <div className="flex items-center gap-1.5">
                                <span className={delta.lighthouse_accessibility_delta >= 0 ? 'text-green-600 font-medium' : 'text-red-600 font-medium'}>
                                    {delta.lighthouse_accessibility_delta >= 0 ? '+' : ''}{delta.lighthouse_accessibility_delta.toFixed(1)}
                                </span>
                                <span className="text-muted-foreground">a11y score</span>
                            </div>
                        )}
                        {delta.experience_score_delta !== null && (
                            <div className="flex items-center gap-1.5">
                                <span className={delta.experience_score_delta >= 0 ? 'text-green-600 font-medium' : 'text-red-600 font-medium'}>
                                    {delta.experience_score_delta >= 0 ? '+' : ''}{delta.experience_score_delta.toFixed(1)}
                                </span>
                                <span className="text-muted-foreground">experience</span>
                            </div>
                        )}
                        <Link
                            href={`/scans/${scan.id}/diff`}
                            className="ml-auto text-primary hover:underline"
                        >
                            Full comparison →
                        </Link>
                    </div>
                )}

                {/* Pending / running state */}
                {isActive && (
                    <div className="rounded-xl border bg-muted/40 px-6 py-5 space-y-3">
                        <div className="flex items-center justify-between text-sm">
                            <span className="flex items-center gap-2 font-medium">
                                <span className="inline-block size-2 animate-pulse rounded-full bg-primary" />
                                Scan in progress — refreshing automatically…
                            </span>
                            {scan.pages_discovered != null && scan.pages_scanned != null && (
                                <span className="tabular-nums text-muted-foreground">
                                    {scan.pages_scanned} / {scan.pages_discovered} pages
                                    {' '}&#40;{Math.round((scan.pages_scanned / scan.pages_discovered) * 100)}%&#41;
                                </span>
                            )}
                        </div>
                        <div className="h-2 w-full overflow-hidden rounded-full bg-muted">
                            {scan.pages_discovered != null && scan.pages_scanned != null ? (
                                <div
                                    className="h-full rounded-full bg-primary transition-all duration-500"
                                    style={{ width: `${Math.round((scan.pages_scanned / scan.pages_discovered) * 100)}%` }}
                                />
                            ) : (
                                <div className="h-full w-full animate-pulse rounded-full bg-primary/40" />
                            )}
                        </div>
                    </div>
                )}

                {/* Failed state error message */}
                {scan.status === 'failed' && scan.error_message && (
                    <div className="rounded-xl border border-destructive/30 bg-destructive/5 px-6 py-4 text-sm text-destructive">
                        <span className="font-semibold mr-2">Scan failed:</span>
                        {scan.error_message}
                    </div>
                )}

                {/* Lighthouse averages — only show once completed */}
                {scan.status === 'completed' && lighthouseResults.length > 0 && (() => {
                    const avgFor = (results: LighthouseResult[], pick: (r: LighthouseResult) => number | null) => {
                        const vals = results.map(pick).filter((v): v is number => v !== null);
                        return vals.length > 0 ? Math.round(vals.reduce((s, v) => s + v, 0) / vals.length) : null;
                    };
                    return (
                        <div>
                            <h3 className="mb-3 text-sm font-semibold">Lighthouse Averages</h3>
                            <div className="flex flex-col gap-4">
                                {mobileLighthouse.length > 0 && (
                                    <div>
                                        <p className="mb-2 text-xs font-medium text-muted-foreground uppercase tracking-wide">Mobile</p>
                                        <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                                            <GaugeCard label="Performance" score={avgFor(mobileLighthouse, (r) => r.performance_score)} />
                                            <GaugeCard label="Accessibility" score={avgFor(mobileLighthouse, (r) => r.accessibility_score)} />
                                            <GaugeCard label="Best Practices" score={avgFor(mobileLighthouse, (r) => r.best_practices_score)} />
                                            <GaugeCard label="SEO" score={avgFor(mobileLighthouse, (r) => r.seo_score)} />
                                        </div>
                                    </div>
                                )}
                                {desktopLighthouse.length > 0 && (
                                    <div>
                                        <p className="mb-2 text-xs font-medium text-muted-foreground uppercase tracking-wide">Desktop</p>
                                        <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                                            <GaugeCard label="Performance" score={avgFor(desktopLighthouse, (r) => r.performance_score)} />
                                            <GaugeCard label="Accessibility" score={avgFor(desktopLighthouse, (r) => r.accessibility_score)} />
                                            <GaugeCard label="Best Practices" score={avgFor(desktopLighthouse, (r) => r.best_practices_score)} />
                                            <GaugeCard label="SEO" score={avgFor(desktopLighthouse, (r) => r.seo_score)} />
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>
                    );
                })()}

                 {/* Experience Score pillar breakdown */}
                {scan.status === 'completed' && experiencePillars !== null && (
                    <div>
                        <h3 className="mb-3 text-sm font-semibold">Experience Score</h3>
                        <div className="rounded-xl border bg-card p-5">
                            <div className="mb-4">
                                <GaugeCard label="Composite score (0–100)" score={Math.round(experiencePillars.experience_score)} />
                            </div>
                            <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                                <PillarCard label="Accessibility" weight="40%" score={experiencePillars.accessibility_score} />
                                <PillarCard label="Performance" weight="25%" score={experiencePillars.performance_score} />
                                <PillarCard label="Tech Quality" weight="20%" score={experiencePillars.best_practices_score} />
                                <PillarCard label="Discoverability" weight="15%" score={experiencePillars.seo_score} />
                            </div>
                        </div>
                    </div>
                )}

                 {/* Breakdown — only show once completed */}
                {scan.status === 'completed' && severityBreakdown.length > 0 && (
                    <div>
                        <h3 className="mb-3 text-sm font-semibold">WCAG Results</h3>
                        <div className="grid gap-4 sm:grid-cols-2">
                            {/* Severity breakdown */}
                            <div className="rounded-xl border p-4">
                                <h3 className="mb-3 text-sm font-semibold">Violations by severity</h3>
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
                    </div>
                )}

                {/* Tabbed results — only shown once completed */}
                {scan.status === 'completed' && (
                    <div className="flex flex-col gap-4">
                        <Tabs value={tab} onValueChange={(v) => setTab(v as 'wcag' | 'lighthouse' | 'pdfs')}>
                            <TabsList>
                                <TabsTrigger value="wcag">WCAG Scores</TabsTrigger>
                                <TabsTrigger value="lighthouse" disabled={lighthouseResults.length === 0}>
                                    Lighthouse Scores
                                    {lighthouseResults.length === 0 && (
                                        <span className="ml-1.5 text-xs opacity-50">(none)</span>
                                    )}
                                </TabsTrigger>
                                <TabsTrigger value="pdfs">
                                    PDFs
                                    {pdfDocuments.length > 0 && (
                                        <span className="ml-1.5 rounded-full bg-muted px-1.5 py-0.5 text-xs tabular-nums">
                                            {pdfDocuments.length}
                                        </span>
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
                            <div className="flex flex-col gap-3">
                                <div className="flex gap-2">
                                    <button
                                        onClick={() => setLighthouseFormFactor('mobile')}
                                        className={`rounded-md px-3 py-1.5 text-xs font-medium transition-colors ${
                                            lighthouseFormFactor === 'mobile'
                                                ? 'bg-primary text-primary-foreground'
                                                : 'bg-muted text-muted-foreground hover:text-foreground'
                                        }`}
                                    >
                                        Mobile
                                    </button>
                                    <button
                                        onClick={() => setLighthouseFormFactor('desktop')}
                                        className={`rounded-md px-3 py-1.5 text-xs font-medium transition-colors ${
                                            lighthouseFormFactor === 'desktop'
                                                ? 'bg-primary text-primary-foreground'
                                                : 'bg-muted text-muted-foreground hover:text-foreground'
                                        }`}
                                    >
                                        Desktop
                                    </button>
                                </div>
                                <div className="rounded-xl border">
                                    <div className="overflow-x-auto">
                                        <table className="w-full text-sm">
                                            <caption className="px-4 py-3">
                                                Lighthouse Scoring Results — {lighthouseFormFactor === 'mobile' ? 'Mobile' : 'Desktop'}
                                            </caption>
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
                                                {lighthouseResults
                                                    .filter((r) => r.form_factor === lighthouseFormFactor)
                                                    .map((result) => (
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
                            </div>
                        )}

                        {/* PDFs tab */}
                        {tab === 'pdfs' && (
                            <>
                            {!pdfScannerAvailable && (
                                <div className="mb-4 rounded-xl border border-yellow-400/40 bg-yellow-50/50 px-4 py-3 text-sm text-yellow-800 dark:border-yellow-500/30 dark:bg-yellow-950/30 dark:text-yellow-300">
                                    The PDF scanner service is currently unavailable. PDF accessibility scanning is disabled.
                                </div>
                            )}
                            {pdfDocuments.length > 0 ? (
                                <div className="rounded-xl border">
                                    <table className="w-full text-sm">
                                        <caption className="px-4 py-3">PDF Documents</caption>
                                        <thead className="border-b bg-muted/50">
                                            <tr className="text-xs text-muted-foreground">
                                                <th className="px-4 py-3 text-left font-medium">File</th>
                                                <th className="px-4 py-3 text-left font-medium">Status</th>
                                                <th className="px-4 py-3 text-right font-medium">Violations</th>
                                                <th className="px-4 py-3 text-right font-medium">Scanned</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y">
                                            {pdfDocuments.map((doc) => (
                                                <tr key={doc.id} className="transition-colors hover:bg-muted/30">
                                                    <td className="max-w-sm px-4 py-3">
                                                        <Link
                                                            href={PdfDocumentController.show(doc.id).url}
                                                            className="font-mono text-xs hover:underline"
                                                        >
                                                            {doc.filename ?? doc.url}
                                                        </Link>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <Badge variant={pdfStatusVariant(doc.status)} className="capitalize">
                                                            {doc.status === 'scanning' && (
                                                                <span className="mr-1.5 inline-block size-2 animate-pulse rounded-full bg-current" />
                                                            )}
                                                            {doc.status}
                                                        </Badge>
                                                    </td>
                                                    <td className="px-4 py-3 text-right tabular-nums">
                                                        {doc.status === 'completed' ? doc.violation_count : '—'}
                                                    </td>
                                                    <td className="px-4 py-3 text-right text-xs text-muted-foreground">
                                                        {doc.scanned_at ? new Date(doc.scanned_at).toLocaleTimeString() : '—'}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            ) : (
                                <div className="rounded-xl border px-6 py-10 text-center text-sm text-muted-foreground">
                                    No PDF documents were discovered during this scan.
                                </div>
                            )}
                            </>
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

                <GenerateReports scan={scan} />

                <div className="text-sm">
                    <Link href={ScanController.index().url} className="text-primary hover:underline">
                        ← Back to scans
                    </Link>
                </div>
            </div>
        </AppLayout>
    );
}

function GenerateReports({ scan }: { scan: Scan }) {
    if (scan.status !== 'completed' || !scan.property) return null;

    const propertyId = scan.property.id;
    const [auditLoading, setAuditLoading] = useState(false);
    const [contentLoading, setContentLoading] = useState(false);
    const [riskLoading, setRiskLoading] = useState(false);
    const anyLoading = auditLoading || contentLoading || riskLoading;

    function generateAudit() {
        setAuditLoading(true);
        router.post(
            AuditController.store().url,
            { property_id: propertyId, scan_ids: [scan.id] },
            { onFinish: () => setAuditLoading(false) },
        );
    }

    async function generateContentAudit() {
        setContentLoading(true);
        try {
            const res = await fetch(`/api/properties/${propertyId}/content-audit/generate`, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                },
            });
            if (res.ok || res.status === 202) {
                router.visit(ContentAuditController.index().url);
            }
        } finally {
            setContentLoading(false);
        }
    }

    async function generateRiskAdvisory() {
        setRiskLoading(true);
        try {
            const res = await fetch(`/api/properties/${propertyId}/risk-advisory/generate`, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                },
            });
            if (res.ok || res.status === 202) {
                router.visit(RiskAdvisoryController.index().url);
            }
        } finally {
            setRiskLoading(false);
        }
    }

    return (
        <div className="rounded-xl border bg-card p-5">
            <h3 className="mb-1 text-sm font-semibold">Generate reports</h3>
            <p className="mb-4 text-xs text-muted-foreground">
                Run AI-powered analysis on this scan to produce remediation plans and insights.
            </p>
            <div className="flex flex-wrap gap-3">
                <Button
                    onClick={generateAudit}
                    disabled={anyLoading}
                    size="sm"
                >
                    {auditLoading ? 'Creating…' : 'AI Accessibility Audit'}
                </Button>
                <Button
                    variant="outline"
                    onClick={generateContentAudit}
                    disabled={anyLoading}
                    size="sm"
                >
                    {contentLoading ? 'Creating…' : 'Content Audit'}
                </Button>
                <Button
                    variant="outline"
                    onClick={generateRiskAdvisory}
                    disabled={anyLoading}
                    size="sm"
                >
                    {riskLoading ? 'Creating…' : 'Risk Advisory'}
                </Button>
            </div>
        </div>
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

function GaugeCard({ label, score }: { label: string; score: number | null }) {
    const pct = score !== null ? Math.max(0, Math.min(100, score)) : 0;

    const barColour =
        score === null ? 'bg-slate-300' :
        score >= 90 ? 'bg-green-500' :
        score >= 50 ? 'bg-orange-500' :
        'bg-red-500';

    const textColour =
        score === null ? 'text-muted-foreground' :
        score >= 90 ? 'text-green-600' :
        score >= 50 ? 'text-orange-500' :
        'text-red-600';

    return (
        <div className="rounded-xl border bg-card p-4 flex flex-col gap-2">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className={`text-2xl font-bold tabular-nums leading-none ${textColour}`}>
                {score ?? '—'}
            </p>
            <div className="h-2 w-full overflow-hidden rounded-full bg-muted">
                <div
                    className={`h-2 rounded-full transition-all ${barColour}`}
                    style={{ width: `${pct}%` }}
                />
            </div>
        </div>
    );
}

function PillarCard({ label, weight, score }: { label: string; weight: string; score: number | null }) {
    const pct = score !== null ? Math.max(0, Math.min(100, score)) : 0;

    const barColour =
        score === null ? 'bg-slate-300' :
        score >= 90 ? 'bg-green-500' :
        score >= 50 ? 'bg-orange-500' :
        'bg-red-500';

    const textColour =
        score === null ? 'text-muted-foreground' :
        score >= 90 ? 'text-green-600' :
        score >= 50 ? 'text-orange-500' :
        'text-red-600';

    return (
        <div className="flex flex-col gap-1.5 rounded-xl border bg-muted/30 p-3">
            <div className="flex items-center justify-between">
                <p className="text-xs font-medium">{label}</p>
                <span className="text-xs text-muted-foreground">{weight}</span>
            </div>
            <p className={`text-xl font-bold tabular-nums leading-none ${textColour}`}>
                {score !== null ? Math.round(score) : '—'}
            </p>
            <div className="h-1.5 w-full overflow-hidden rounded-full bg-muted">
                <div
                    className={`h-1.5 rounded-full transition-all ${barColour}`}
                    style={{ width: `${pct}%` }}
                />
            </div>
        </div>
    );
}
