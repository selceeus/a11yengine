import { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import * as PropertyController from '@/actions/App/Http/Controllers/PropertyController';
import ScanController from '@/actions/App/Http/Controllers/ScanController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { PropertyRiskTrendsChart } from '@/components/charts/PropertyRiskTrendsChart';
import { PropertyScanActivityChart } from '@/components/charts/PropertyScanActivityChart';
import { IssueClusterPanel } from '@/components/IssueClusterPanel';
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
    status: string;
    organization: Organization | null;
};

type Scan = {
    id: number;
    status: 'pending' | 'running' | 'completed' | 'failed';
    pages_scanned: number | null;
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

export default function Show({
    property,
    recentScans,
    lighthouseAverages,
    severityBreakdown,
    topRules,
    scheduledScan: initialScheduledScan,
}: {
    property: Property;
    recentScans: Scan[];
    lighthouseAverages: LighthouseAverages;
    severityBreakdown: SeverityRow[];
    topRules: Record<string, number>;
    scheduledScan: ScheduledScan | null;
}) {
    const { delete: destroy, processing } = useForm();
    const [overview, setOverview] = useState<OverviewState>({ open: false });
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

    function openScheduleDialog(mode: 'create' | 'edit') {
        if (mode === 'edit' && scheduledScan) {
            setScheduleType(scheduledScan.type);
            setScheduleFrequency(scheduledScan.frequency ?? 'weekly');
            setScheduleAt(scheduledScan.scheduled_at ? scheduledScan.scheduled_at.slice(0, 16) : '');
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

        const body: Record<string, string | number> = { type: scheduleType };
        if (scheduleType === 'once') {
            body.scheduled_at = scheduleAt;
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
                            <Link href={PropertyController.edit(property.id).url}>Edit</Link>
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={handleDelete}
                            disabled={processing}
                        >
                            Delete
                        </Button>
                    </div>
                </div>

                {/* Meta */}
                <dl className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                    <StatCard label="Organization" value={property.organization?.name ?? '—'} />
                    <StatCard label="Status" value={property.status} capitalize />
                </dl>

                {/* Lighthouse averages */}
                {lighthouseAverages && (
                    <div>
                        <h2 className="mb-3 text-sm font-semibold">Lighthouse averages (all scans)</h2>
                        <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                            <GaugeCard label="Performance" score={lighthouseAverages.performance_score} />
                            <GaugeCard label="Accessibility" score={lighthouseAverages.accessibility_score} />
                            <GaugeCard label="Best Practices" score={lighthouseAverages.best_practices_score} />
                            <GaugeCard label="SEO" score={lighthouseAverages.seo_score} />
                        </div>
                    </div>
                )}

                {/* Violations by severity & top rules */}
                {severityBreakdown.length > 0 && (
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="rounded-xl border p-4">
                            <h2 className="mb-3 text-sm font-semibold">Violations by Severity (Avg)</h2>
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
                        <div className="rounded-xl border p-4">
                            <h2 className="mb-3 text-sm font-semibold">Top Violated Rules (Avg)</h2>
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

                {/* Charts */}
                <div className="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Scan Activity</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <PropertyScanActivityChart propertyId={property.id} />
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Property Risk Trends</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <PropertyRiskTrendsChart propertyId={property.id} />
                        </CardContent>
                    </Card>
                </div>

                {/* AI Issue Clusters */}
                <Card id="ai-clusters">
                    <CardHeader>
                        <CardTitle>AI Issue Clusters</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <IssueClusterPanel propertyId={property.id} />
                    </CardContent>
                </Card>

                {/* Recent scans */}
                <div className="rounded-xl border">
                    <div className="flex items-center justify-between border-b px-4 py-3">
                        <h2 className="text-sm font-semibold">Recent scans</h2>
                        <div className="flex items-center gap-3">
                            {scheduledScan ? (
                                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <span>
                                        Next:{' '}
                                        <span className="font-medium text-foreground">
                                            {new Date(scheduledScan.next_run_at).toLocaleString()}
                                        </span>
                                        {scheduledScan.frequency && (
                                            <span className="ml-1 capitalize">· {scheduledScan.frequency}</span>
                                        )}
                                    </span>
                                    <button
                                        onClick={() => openScheduleDialog('edit')}
                                        className="text-primary hover:underline"
                                    >
                                        Update
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
                    <table className="w-full text-sm">
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
                                            {scan.pages_scanned ?? '—'}
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
                                        className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
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
                                    className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
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

function StatCard({
    label,
    value,
    capitalize,
}: {
    label: string;
    value: string | number;
    capitalize?: boolean;
}) {
    return (
        <div className="rounded-xl border bg-card p-4">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className={`mt-1 text-base font-semibold ${capitalize ? 'capitalize' : ''}`}>
                {value}
            </p>
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
        <div className="flex flex-col gap-2 rounded-xl border bg-card p-4">
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
