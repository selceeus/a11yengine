import { useState, useEffect } from 'react';
import { Head, Link, useForm, usePoll } from '@inertiajs/react';
import * as PropertyController from '@/actions/App/Http/Controllers/PropertyController';
import ScanController from '@/actions/App/Http/Controllers/ScanController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { PropertyRiskTrendsChart } from '@/components/charts/PropertyRiskTrendsChart';
import { PropertyScanActivityChart } from '@/components/charts/PropertyScanActivityChart';
import { IssueClusterPanel } from '@/components/IssueClusterPanel';
import { RiskPriorityPanel } from '@/components/RiskPriorityPanel';
import { ContentAuditPanel } from '@/components/ContentAuditPanel';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Organization = {
    id: number;
    name: string;
};

type Property = {
    id: number;
    name: string;
    base_url: string;
    industry: string | null;
    industry_label: string | null;
    legal_risk_level: 'high' | 'medium' | 'low' | null;
    status: string;
    organization: Organization | null;
};

type Scan = {
    id: number;
    status: 'pending' | 'running' | 'completed' | 'failed';
    pages_scanned: number | null;
    pages_discovered: number | null;
    total_violations: number | null;
    created_at: string;
};

type LighthouseAverages = {
    performance_score: number;
    accessibility_score: number;
    best_practices_score: number;
    seo_score: number;
} | null;

type SeverityRow = {
    severity: 'critical' | 'serious' | 'moderate' | 'minor' | 'info';
    count: number;
};

type ScheduledScan = {
    id: number;
    type: 'once' | 'recurring';
    frequency: 'daily' | 'weekly' | 'monthly' | 'quarterly' | null;
    scheduled_at: string | null;
    next_run_at: string;
    run_time: string | null;
    run_day_of_week: number | null;
    run_day_of_month: number | null;
    is_active: boolean;
    last_run_at: string | null;
};

type ScheduleDialogState =
    | { open: false }
    | { open: true; mode: 'create' | 'edit' };

type LighthouseOverviewAverages = {
    performance: number | null;
    accessibility: number | null;
    best_practices: number | null;
    seo: number | null;
};

type OverviewState =
    | { open: false }
    | { open: true; scanId: number; loading: true }
    | { open: true; scanId: number; loading: false; severityBreakdown: SeverityRow[]; lighthouseAverages: LighthouseOverviewAverages | null };

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

function toLocalInputValue(utcIso: string): string {
    const d = new Date(utcIso);
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

export default function Show({
    property,
    recentScans,
    lighthouseAverages,
    severityBreakdown,
    topRules,
    scheduledScan: initialScheduledScan,
    latestExperienceScore,
    experienceScoreDelta,
}: {
    property: Property;
    recentScans: Scan[];
    lighthouseAverages: LighthouseAverages;
    severityBreakdown: SeverityRow[];
    topRules: Record<string, number>;
    scheduledScan: ScheduledScan | null;
    latestExperienceScore: number | null;
    experienceScoreDelta: number | null;
}) {
    const { delete: destroy, processing } = useForm();
    const [overview, setOverview] = useState<OverviewState>({ open: false });
    const hasActiveScans = recentScans.some((s) => s.status === 'pending' || s.status === 'running');
    const { start, stop } = usePoll(3000, {}, { autoStart: false, only: ['recentScans'] });
    useEffect(() => {
        if (hasActiveScans) {
            start();
            return () => stop();
        }
    }, [hasActiveScans]);
    const [scheduledScan, setScheduledScan] = useState<ScheduledScan | null>(initialScheduledScan);
    const [scheduleDialog, setScheduleDialog] = useState<ScheduleDialogState>({ open: false });
    const [scheduleType, setScheduleType] = useState<'once' | 'recurring'>('recurring');
    const [scheduleFrequency, setScheduleFrequency] = useState<string>('weekly');
    const [scheduleAt, setScheduleAt] = useState<string>('');
    const [scheduleTime, setScheduleTime] = useState<string>('09:00');
    const [scheduleDayOfWeek, setScheduleDayOfWeek] = useState<string>('1');
    const [scheduleDayOfMonth, setScheduleDayOfMonth] = useState<string>('1');
    const [scheduleRemoveConfirm, setScheduleRemoveConfirm] = useState(false);
    const [scheduleRemoving, setScheduleRemoving] = useState(false);
    const [scheduleSubmitting, setScheduleSubmitting] = useState(false);
    const [scheduleError, setScheduleError] = useState<string | null>(null);
    const [scheduleToggling, setScheduleToggling] = useState(false);

    function openScheduleDialog(mode: 'create' | 'edit') {
        if (mode === 'edit' && scheduledScan) {
            setScheduleType(scheduledScan.type);
            setScheduleFrequency(scheduledScan.frequency ?? 'weekly');
            setScheduleAt(scheduledScan.scheduled_at ? toLocalInputValue(scheduledScan.scheduled_at) : '');
            setScheduleTime(scheduledScan.run_time ?? '09:00');
            setScheduleDayOfWeek(scheduledScan.run_day_of_week?.toString() ?? '1');
            setScheduleDayOfMonth(scheduledScan.run_day_of_month?.toString() ?? '1');
        } else {
            setScheduleType('recurring');
            setScheduleFrequency('weekly');
            setScheduleAt('');
            setScheduleTime('09:00');
            setScheduleDayOfWeek('1');
            setScheduleDayOfMonth('1');
        }
        setScheduleError(null);
        setScheduleRemoveConfirm(false);
        setScheduleDialog({ open: true, mode });
    }

    async function submitSchedule(e: React.FormEvent) {
        e.preventDefault();
        setScheduleSubmitting(true);
        setScheduleError(null);

        const isEdit = scheduleDialog.open && scheduleDialog.mode === 'edit' && scheduledScan;
        const url = isEdit
            ? `/api/properties/${property.id}/scheduled-scan/${scheduledScan!.id}`
            : `/api/properties/${property.id}/scheduled-scan`;

        const body: Record<string, string | number> = {
            type: scheduleType,
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
        };
        if (scheduleType === 'once') {
            body.scheduled_at = new Date(scheduleAt).toISOString();
        } else {
            body.frequency = scheduleFrequency;
            body.run_time = scheduleTime;
            if (scheduleFrequency === 'weekly') {
                body.run_day_of_week = Number(scheduleDayOfWeek);
            }
            if (scheduleFrequency === 'monthly' || scheduleFrequency === 'quarterly') {
                body.run_day_of_month = Number(scheduleDayOfMonth);
            }
        }

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

        const res = await fetch(url, {
            method: isEdit ? 'PUT' : 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify(body),
        });

        setScheduleSubmitting(false);

        if (!res.ok) {
            const json = await res.json().catch(() => ({}));
            setScheduleError(json.message ?? 'Failed to save schedule.');
            return;
        }

        const json = await res.json();
        setScheduledScan(json.scheduledScan);
        setScheduleDialog({ open: false });
    }

    async function removeSchedule() {
        if (!scheduledScan) return;
        setScheduleRemoving(true);
        setScheduleError(null);

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

        const res = await fetch(`/api/properties/${property.id}/scheduled-scan/${scheduledScan.id}`, {
            method: 'DELETE',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
            },
        });

        setScheduleRemoving(false);

        if (!res.ok) {
            const json = await res.json().catch(() => ({}));
            setScheduleError(json.message ?? 'Failed to remove schedule.');
            return;
        }

        setScheduledScan(null);
        setScheduleDialog({ open: false });
    }

    async function toggleSchedule() {
        if (!scheduledScan) return;
        setScheduleToggling(true);

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

        const res = await fetch(
            `/api/properties/${property.id}/scheduled-scan/${scheduledScan.id}/toggle`,
            {
                method: 'PATCH',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
            },
        );

        setScheduleToggling(false);

        if (!res.ok) return;

        const json = await res.json();
        setScheduledScan((prev) => (prev ? { ...prev, is_active: json.scheduledScan.is_active } : prev));
    }

    async function openOverview(scan: Scan) {
        setOverview({ open: true, scanId: scan.id, loading: true });

        const res = await fetch(`/api/scans/${scan.id}/overview`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        const json = await res.json();

        setOverview({
            open: true,
            scanId: scan.id,
            loading: false,
            severityBreakdown: json.severityBreakdown,
            lighthouseAverages: json.lighthouseAverages,
        });
    }

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Properties', href: PropertyController.index().url },
        { title: property.name, href: PropertyController.show(property.id).url },
    ];

    function handleDelete() {
        if (!confirm(`Delete "${property.name}"? This cannot be undone.`)) return;
        destroy(PropertyController.destroy(property.id).url);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={property.name} />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex items-start justify-between">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-semibold pb-2">{property.name}</h1>
                        <a
                            href={property.base_url}
                            target="_blank"
                            rel="noreferrer"
                            className="text-sm text-muted-foreground hover:underline"
                        >
                            {property.base_url}
                        </a>
                    </div>

                    <div className="flex items-center gap-2">
                        <Button variant="outline" asChild>
                            <Link className="cursor-pointer" href={PropertyController.edit(property.id).url}>Edit</Link>
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={handleDelete}
                            disabled={processing}
                            className="cursor-pointer"
                        >
                            Delete
                        </Button>
                    </div>
                </div>

                {/* Scores & Metadata — 3-column grid */}
                <div className="grid gap-4 sm:grid-cols-3">
                    {/* Col 1: Property meta */}
                    <div className="rounded border bg-card p-4">
                        <h2 className="mb-3 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Property</h2>
                        <dl className="space-y-2 text-sm">
                            <div className="flex items-center justify-between gap-2">
                                <dt className="text-muted-foreground">Organisation</dt>
                                <dd className="font-medium">{property.organization?.name ?? '—'}</dd>
                            </div>
                            <div className="flex items-center justify-between gap-2">
                                <dt className="text-muted-foreground">Status</dt>
                                <dd className="font-medium capitalize">{property.status}</dd>
                            </div>
                            {property.industry_label && (
                                <div className="flex items-center justify-between gap-2">
                                    <dt className="text-muted-foreground">Industry</dt>
                                    <dd className="flex items-center gap-1.5">
                                        <span className="font-medium">{property.industry_label}</span>
                                        {property.legal_risk_level && (
                                            <Badge
                                                variant={
                                                    property.legal_risk_level === 'high'
                                                        ? 'destructive'
                                                        : property.legal_risk_level === 'medium'
                                                          ? 'default'
                                                          : 'secondary'
                                                }
                                                className="capitalize text-xs"
                                            >
                                                {property.legal_risk_level} risk
                                            </Badge>
                                        )}
                                    </dd>
                                </div>
                            )}
                        </dl>
                    </div>

                    {/* Col 2: Experience Score */}
                    <div className="rounded border bg-card p-4">
                        <div className="mb-3 flex items-center justify-between">
                            <h2 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Experience Score</h2>
                            {experienceScoreDelta !== null && (
                                <span
                                    className={`text-xs font-medium tabular-nums ${
                                        experienceScoreDelta > 0
                                            ? 'text-green-600'
                                            : experienceScoreDelta < 0
                                              ? 'text-red-600'
                                              : 'text-muted-foreground'
                                    }`}
                                >
                                    {experienceScoreDelta > 0 ? '↑' : experienceScoreDelta < 0 ? '↓' : '→'}{' '}
                                    {Math.abs(experienceScoreDelta).toFixed(1)} vs. last scan
                                </span>
                            )}
                        </div>
                        {latestExperienceScore !== null ? (
                            <>
                                <GaugeCard label="Composite (0–100)" score={Math.round(latestExperienceScore)} />
                                <p className="mt-2 text-xs text-muted-foreground">
                                    A11y 40% · Perf 25% · Tech 20% · SEO 15%
                                </p>
                            </>
                        ) : (
                            <p className="text-sm text-muted-foreground">No score yet.</p>
                        )}
                    </div>

                    {/* Col 3: Lighthouse averages (compact list) */}
                    <div className="rounded border bg-card p-4">
                        <h2 className="mb-3 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Lighthouse averages</h2>
                        {lighthouseAverages ? (
                            <div className="space-y-2.5">
                                {(
                                    [
                                        { label: 'Performance', score: lighthouseAverages.performance_score },
                                        { label: 'Accessibility', score: lighthouseAverages.accessibility_score },
                                        { label: 'Best Practices', score: lighthouseAverages.best_practices_score },
                                        { label: 'SEO', score: lighthouseAverages.seo_score },
                                    ] as { label: string; score: number }[]
                                ).map(({ label, score }) => {
                                    const pct = Math.max(0, Math.min(100, score ?? 0));
                                    const colour =
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
                                        <div key={label} className="flex items-center gap-3 text-xs">
                                            <span className="w-24 shrink-0 text-muted-foreground">{label}</span>
                                            <div className="h-1.5 flex-1 overflow-hidden rounded-full bg-muted">
                                                <div className={`h-1.5 rounded-full ${colour}`} style={{ width: `${pct}%` }} />
                                            </div>
                                            <span className={`w-6 shrink-0 text-right font-medium tabular-nums ${textColour}`}>{score ?? '—'}</span>
                                        </div>
                                    );
                                })}
                            </div>
                        ) : (
                            <p className="text-sm text-muted-foreground">No Lighthouse data yet.</p>
                        )}
                    </div>
                </div>

                {/* Violations by severity & top rules */}
                {severityBreakdown.length > 0 && (
                    <div className="grid gap-3 sm:grid-cols-2">
                        <div className="rounded border p-3">
                            <h2 className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Violations by Severity (Avg)</h2>
                            <div className="space-y-6">
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
                                            <div className="h-1.5 w-full overflow-hidden rounded-full bg-muted">
                                                <div
                                                    className={`h-1.5 rounded-full ${SEVERITY_COLOURS[row.severity]}`}
                                                    style={{ width: `${pct}%` }}
                                                />
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                        <div className="rounded border p-3">
                            <h2 className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Top Violated Rules (Avg)</h2>
                            <ol className="space-y-1">
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

                {/* Charts */}
                <div className="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm">Scan Activity</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="h-40">
                                <PropertyScanActivityChart propertyId={property.id} />
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm">Property Risk Trends</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="h-40">
                                <PropertyRiskTrendsChart propertyId={property.id} />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Analysis — tabbed to avoid stacking */}
                <Card>
                    <Tabs defaultValue="clusters">
                        <CardHeader className="pb-0">
                            <TabsList>
                                <TabsTrigger value="clusters">Issue Clusters</TabsTrigger>
                                <TabsTrigger value="risk">Risk Advisory</TabsTrigger>
                                <TabsTrigger value="content">Content Audit</TabsTrigger>
                            </TabsList>
                        </CardHeader>
                        <CardContent className="pt-4">
                            <TabsContent value="clusters" className="mt-0 data-[state=inactive]:hidden" forceMount>
                                <IssueClusterPanel propertyId={property.id} />
                            </TabsContent>
                            <TabsContent value="risk" className="mt-0 data-[state=inactive]:hidden" forceMount>
                                <RiskPriorityPanel propertyId={property.id} />
                            </TabsContent>
                            <TabsContent value="content" className="mt-0 data-[state=inactive]:hidden" forceMount>
                                <ContentAuditPanel propertyId={property.id} />
                            </TabsContent>
                        </CardContent>
                    </Tabs>
                </Card>

                {/* Recent scans */}
                <div className="rounded border">
                    <div className="flex items-center justify-between border-b px-4 py-3">
                        <h2 className="text-sm font-semibold">Recent scans</h2>
                        <div className="flex items-center gap-3">
                            {scheduledScan ? (
                            <div className="flex items-center gap-3 text-sm text-muted-foreground">
                                <Badge variant={scheduledScan.is_active ? 'default' : 'secondary'} className="text-xs">
                                    {scheduledScan.is_active ? 'Active' : 'Paused'}
                                </Badge>
                                <span>
                                    Next:{' '}
                                    <span className="font-medium text-foreground">
                                        {new Date(scheduledScan.next_run_at).toLocaleString()}
                                    </span>
                                    {scheduledScan.frequency && (
                                        <span className="ml-1 capitalize">· {scheduledScan.frequency}</span>
                                    )}
                                </span>
                                {scheduledScan.last_run_at && (
                                    <span className="hidden sm:inline">
                                        Last:{' '}
                                        <span className="font-medium text-foreground">
                                            {new Date(scheduledScan.last_run_at).toLocaleString()}
                                        </span>
                                    </span>
                                )}
                                <button
                                    onClick={toggleSchedule}
                                    disabled={scheduleToggling}
                                    className="text-primary hover:underline disabled:opacity-50"
                                >
                                    {scheduleToggling ? '…' : scheduledScan.is_active ? 'Pause' : 'Resume'}
                                </button>
                                <button
                                    onClick={() => openScheduleDialog('edit')}
                                    className="text-primary hover:underline"
                                >
                                    Edit
                                </button>
                            </div>
                        ) : (
                            <Button size="sm" variant="outline" onClick={() => openScheduleDialog('create')}>
                                Schedule scan
                            </Button>
                        )}
                            <Button size="sm" asChild>
                                <Link href={ScanController.index().url}>Run scan</Link>
                            </Button>
                        </div>
                    </div>
                    <table className="w-full text-sm data-table">
                        <thead className="border-b bg-muted/50">
                            <tr className="text-xs text-muted-foreground">
                                <th className="px-4 py-3 text-left font-medium">Status</th>
                                <th className="px-4 py-3 text-right font-medium">Pages</th>
                                <th className="px-4 py-3 text-right font-medium">Violations</th>
                                <th className="px-4 py-3 text-left font-medium">Started</th>
                                <th className="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {recentScans.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-10 text-center text-sm text-muted-foreground"
                                    >
                                        No scans yet.
                                    </td>
                                </tr>
                            ) : (
                                recentScans.map((scan) => (
                                    <tr
                                        key={scan.id}
                                        className="transition-colors hover:bg-muted/30"
                                    >
                                        <td className="px-4 py-3">
                                            <Badge variant={statusVariant(scan.status)}>
                                                {scan.status}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3 text-right tabular-nums text-muted-foreground">
                                            {(scan.status === 'running' || scan.status === 'pending') && scan.pages_discovered != null && scan.pages_scanned != null ? (
                                                <div className="space-y-1">
                                                    <div className="text-xs">
                                                        {scan.pages_scanned}/{scan.pages_discovered}
                                                        <span className="ml-1 font-medium text-primary">
                                                            {Math.round((scan.pages_scanned / scan.pages_discovered) * 100)}%
                                                        </span>
                                                    </div>
                                                    <div className="h-1.5 w-full overflow-hidden rounded-full bg-muted">
                                                        <div
                                                            className="h-full rounded-full bg-primary transition-all"
                                                            style={{ width: `${Math.round((scan.pages_scanned / scan.pages_discovered) * 100)}%` }}
                                                        />
                                                    </div>
                                                </div>
                                            ) : (
                                                scan.pages_scanned ?? '—'
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-right tabular-nums text-muted-foreground">
                                            {scan.total_violations ?? '—'}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {new Date(scan.created_at).toLocaleString()}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="flex items-center justify-end gap-3">
                                                <button
                                                    onClick={() => openOverview(scan)}
                                                    className="text-sm text-primary hover:underline"
                                                >
                                                    Overview
                                                </button>
                                                <Link
                                                    href={ScanController.show(scan.id).url}
                                                    className="text-sm text-primary hover:underline"
                                                >
                                                    View
                                                </Link>
                                                {scan.status === 'completed' && (
                                                    <Link
                                                        href={`/scans/${scan.id}/diff`}
                                                        className="text-sm text-primary hover:underline"
                                                    >
                                                        Compare
                                                    </Link>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="text-sm">
                    <Link
                        href={PropertyController.index().url}
                        className="text-primary hover:underline"
                    >
                        ← Back to properties
                    </Link>
                </div>
            </div>

            {/* Scan Overview Dialog */}
            <Dialog open={overview.open} onOpenChange={(open) => { if (!open) setOverview({ open: false }); }}>
                <DialogContent className="max-w-2xl sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Scan Overview</DialogTitle>
                    </DialogHeader>

                    {overview.open && overview.loading && (
                        <div className="flex items-center justify-center py-10">
                            <Spinner className="h-6 w-6" />
                        </div>
                    )}

                    {overview.open && !overview.loading && (
                        <div className="flex flex-col gap-6">
                            <div>
                                <h3 className="mb-3 text-sm font-semibold">Lighthouse Averages</h3>
                                {overview.lighthouseAverages ? (
                                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                                        <GaugeCard label="Performance" score={overview.lighthouseAverages.performance} />
                                        <GaugeCard label="Accessibility" score={overview.lighthouseAverages.accessibility} />
                                        <GaugeCard label="Best Practices" score={overview.lighthouseAverages.best_practices} />
                                        <GaugeCard label="SEO" score={overview.lighthouseAverages.seo} />
                                    </div>
                                ) : (
                                    <p className="text-sm text-muted-foreground">No Lighthouse data available.</p>
                                )}
                            </div>

                            <div>
                                <h3 className="mb-3 text-sm font-semibold">WCAG Violations by Severity</h3>
                                {overview.severityBreakdown.length > 0 ? (
                                    <div className="space-y-2">
                                        {overview.severityBreakdown.map((row) => {
                                            const total = overview.severityBreakdown.reduce((s, r) => s + r.count, 0);
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
                                ) : (
                                    <p className="text-sm text-muted-foreground">No violation data available.</p>
                                )}
                            </div>
                        </div>
                    )}
                </DialogContent>
            </Dialog>

            {/* Schedule Scan Dialog */}
            <Dialog open={scheduleDialog.open} onOpenChange={(open) => { if (!open) setScheduleDialog({ open: false }); }}>
                <DialogContent className="max-w-md sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            {scheduleDialog.open && scheduleDialog.mode === 'edit' ? 'Update scheduled scan' : 'Schedule a scan'}
                        </DialogTitle>
                    </DialogHeader>

                    <form onSubmit={submitSchedule} className="flex flex-col gap-5 pt-1">
                        {/* Type toggle */}
                        <div className="flex flex-col gap-1.5">
                            <Label>Scan type</Label>
                            <div className="flex gap-4">
                                {(['recurring', 'once'] as const).map((t) => (
                                    <label key={t} className="flex cursor-pointer items-center gap-2 text-sm">
                                        <input
                                            type="radio"
                                            name="type"
                                            value={t}
                                            checked={scheduleType === t}
                                            onChange={() => setScheduleType(t)}
                                            className="accent-primary"
                                        />
                                        <span className="capitalize">{t === 'once' ? 'One-time' : 'Recurring'}</span>
                                    </label>
                                ))}
                            </div>
                        </div>

                        {/* Recurring: frequency + day + time */}
                        {scheduleType === 'recurring' && (
                            <div className="flex flex-col gap-4">
                                <div className="flex flex-col gap-1.5">
                                    <Label htmlFor="frequency">Frequency</Label>
                                    <Select value={scheduleFrequency} onValueChange={setScheduleFrequency}>
                                        <SelectTrigger id="frequency">
                                            <SelectValue placeholder="Select frequency…" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="daily">Daily</SelectItem>
                                            <SelectItem value="weekly">Weekly</SelectItem>
                                            <SelectItem value="monthly">Monthly</SelectItem>
                                            <SelectItem value="quarterly">Quarterly</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                {scheduleFrequency === 'weekly' && (
                                    <div className="flex flex-col gap-1.5">
                                        <Label htmlFor="run_day_of_week">Day of week</Label>
                                        <Select value={scheduleDayOfWeek} onValueChange={setScheduleDayOfWeek}>
                                            <SelectTrigger id="run_day_of_week">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="0">Sunday</SelectItem>
                                                <SelectItem value="1">Monday</SelectItem>
                                                <SelectItem value="2">Tuesday</SelectItem>
                                                <SelectItem value="3">Wednesday</SelectItem>
                                                <SelectItem value="4">Thursday</SelectItem>
                                                <SelectItem value="5">Friday</SelectItem>
                                                <SelectItem value="6">Saturday</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                )}

                                {(scheduleFrequency === 'monthly' || scheduleFrequency === 'quarterly') && (
                                    <div className="flex flex-col gap-1.5">
                                        <Label htmlFor="run_day_of_month">
                                            {scheduleFrequency === 'quarterly' ? 'Day of month (per quarter)' : 'Day of month'}
                                        </Label>
                                        <Select value={scheduleDayOfMonth} onValueChange={setScheduleDayOfMonth}>
                                            <SelectTrigger id="run_day_of_month">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {Array.from({ length: 28 }, (_, i) => (
                                                    <SelectItem key={i + 1} value={String(i + 1)}>
                                                        {i + 1}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                )}

                                <div className="flex flex-col gap-1.5">
                                    <Label htmlFor="run_time">Time</Label>
                                    <input
                                        id="run_time"
                                        type="time"
                                        value={scheduleTime}
                                        onChange={(e) => setScheduleTime(e.target.value)}
                                        required
                                        className="flex h-9 w-full rounded border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                    />
                                </div>
                            </div>
                        )}

                        {/* One-time: datetime */}
                        {scheduleType === 'once' && (
                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="scheduled_at">Date &amp; time</Label>
                                <input
                                    id="scheduled_at"
                                    type="datetime-local"
                                    value={scheduleAt}
                                    onChange={(e) => setScheduleAt(e.target.value)}
                                    required
                                    className="flex h-9 w-full rounded border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                />
                            </div>
                        )}

                        {scheduleError && (
                            <p className="text-xs text-destructive">{scheduleError}</p>
                        )}

                        <div className="flex items-center gap-2">
                            {/* Remove button (edit mode only) */}
                            {scheduleDialog.open && scheduleDialog.mode === 'edit' && (
                                <div className="flex items-center gap-2">
                                    {!scheduleRemoveConfirm ? (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                            onClick={() => setScheduleRemoveConfirm(true)}
                                        >
                                            Remove
                                        </Button>
                                    ) : (
                                        <>
                                            <Button
                                                type="button"
                                                variant="destructive"
                                                size="sm"
                                                disabled={scheduleRemoving}
                                                onClick={removeSchedule}
                                            >
                                                {scheduleRemoving ? 'Removing…' : 'Confirm removal'}
                                            </Button>
                                            <button
                                                type="button"
                                                className="text-xs text-muted-foreground hover:text-foreground"
                                                onClick={() => setScheduleRemoveConfirm(false)}
                                            >
                                                cancel
                                            </button>
                                        </>
                                    )}
                                </div>
                            )}

                            {/* Cancel + Save */}
                            <div className="ml-auto flex gap-2">
                                <Button type="button" variant="outline" onClick={() => setScheduleDialog({ open: false })}>
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={scheduleSubmitting}>
                                    {scheduleSubmitting ? 'Saving…' : 'Save schedule'}
                                </Button>
                            </div>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
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
        <div className="flex flex-col gap-2 rounded border bg-card p-4">
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
