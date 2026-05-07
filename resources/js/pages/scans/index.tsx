import { useState, useEffect } from 'react';
import { Head, Link, router, useForm, usePoll } from '@inertiajs/react';
import ScanController from '@/actions/App/Http/Controllers/ScanController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Property = {
    id: number;
    name: string;
    base_url: string;
};

type Scan = {
    id: number;
    status: 'pending' | 'running' | 'completed' | 'failed';
    pages_scanned: number | null;
    pages_discovered: number | null;
    total_violations: number | null;
    created_at: string;
    target_url: string | null;
    property: Property | null;
    canDelete: boolean;
};

type SeverityRow = {
    severity: 'critical' | 'serious' | 'moderate' | 'minor' | 'info';
    count: number;
};

type LighthouseAverages = {
    performance: number | null;
    accessibility: number | null;
    best_practices: number | null;
    seo: number | null;
};

type OverviewState =
    | { open: false }
    | { open: true; scanId: number; scanName: string; loading: true }
    | { open: true; scanId: number; scanName: string; loading: false; severityBreakdown: SeverityRow[]; lighthouseAverages: LighthouseAverages | null };

const SEVERITY_COLOURS: Record<SeverityRow['severity'], string> = {
    critical: 'bg-red-500',
    serious: 'bg-orange-500',
    moderate: 'bg-yellow-500',
    minor: 'bg-blue-400',
    info: 'bg-slate-400',
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Scans', href: ScanController.index().url },
];

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

export default function Index({ scans, properties }: { scans: Scan[]; properties: Property[] }) {
    const { data, setData, post, processing, errors } = useForm({ property_id: '', target_url: '' });
    const [singlePage, setSinglePage] = useState(false);
    const [overview, setOverview] = useState<OverviewState>({ open: false });

    // Schedule scan dialog state
    const [scheduleOpen, setScheduleOpen] = useState(false);
    const [schedulePropertyId, setSchedulePropertyId] = useState('');
    const [scheduleType, setScheduleType] = useState<'once' | 'recurring'>('recurring');
    const [scheduleFrequency, setScheduleFrequency] = useState('weekly');
    const [scheduleTime, setScheduleTime] = useState('09:00');
    const [scheduleDayOfWeek, setScheduleDayOfWeek] = useState('1');
    const [scheduleDayOfMonth, setScheduleDayOfMonth] = useState('1');
    const [scheduleAt, setScheduleAt] = useState('');
    const [scheduleSubmitting, setScheduleSubmitting] = useState(false);
    const [scheduleError, setScheduleError] = useState<string | null>(null);
    const [scheduleSaved, setScheduleSaved] = useState(false);

    function openScheduleDialog() {
        setSchedulePropertyId('');
        setScheduleType('recurring');
        setScheduleFrequency('weekly');
        setScheduleTime('09:00');
        setScheduleDayOfWeek('1');
        setScheduleDayOfMonth('1');
        setScheduleAt('');
        setScheduleError(null);
        setScheduleSaved(false);
        setScheduleOpen(true);
    }

    async function submitSchedule(e: React.FormEvent) {
        e.preventDefault();
        setScheduleSubmitting(true);
        setScheduleError(null);

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

        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
        const res = await fetch(`/api/properties/${schedulePropertyId}/scheduled-scan`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify(body),
        });

        setScheduleSubmitting(false);

        if (!res.ok) {
            const json = await res.json().catch(() => ({}));
            setScheduleError(json.message ?? 'Failed to save schedule.');
            return;
        }

        setScheduleSaved(true);
    }

    const hasActiveScans = scans.some((s) => s.status === 'pending' || s.status === 'running');
    const { start, stop } = usePoll(3000, {}, { autoStart: false });
    useEffect(() => {
        if (hasActiveScans) {
            start();
            return () => stop();
        }
    }, [hasActiveScans]);

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post(ScanController.store().url, {
            data: {
                property_id: data.property_id,
                ...(singlePage && data.target_url ? { target_url: data.target_url } : {}),
            },
        });
    }

    function deleteScan(scan: Scan) {
        if (!confirm(`Delete the scan for "${scan.property?.name ?? `Scan #${scan.id}`}"? This cannot be undone.`)) {
            return;
        }
        router.delete(ScanController.destroy(scan.id).url);
    }

    async function openOverview(scan: Scan) {
        setOverview({ open: true, scanId: scan.id, scanName: scan.property?.name ?? `Scan #${scan.id}`, loading: true });

        const res = await fetch(`/api/scans/${scan.id}/overview`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        const json = await res.json();

        setOverview({
            open: true,
            scanId: scan.id,
            scanName: scan.property?.name ?? `Scan #${scan.id}`,
            loading: false,
            severityBreakdown: json.severityBreakdown,
            lighthouseAverages: json.lighthouseAverages,
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Scans" />

            <div className="flex flex-col gap-6 p-6">
                {/* Page header */}
                <div className="flex items-center justify-between gap-4">
                    <h1 className="text-xl font-semibold">Scans</h1>
                    <Button variant="outline" size="sm" onClick={openScheduleDialog}>Schedule Scan</Button>
                </div>

                {/* Trigger form */}
                <div className="rounded-xl border bg-card p-6">
                    <h2 className="mb-4 text-base font-semibold">Run a new scan</h2>

                    <form onSubmit={submit} className="flex flex-wrap items-end gap-4">
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="property_id">Property</Label>
                            <Select value={data.property_id} onValueChange={(v) => setData('property_id', v)}>
                                <SelectTrigger id="property_id" className="w-64">
                                    <SelectValue placeholder="Select a property…" />
                                </SelectTrigger>
                                <SelectContent>
                                    {properties.map((p) => (
                                        <SelectItem key={p.id} value={String(p.id)}>
                                            {p.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.property_id && (
                                <p className="text-xs text-destructive">{errors.property_id}</p>
                            )}
                        </div>

                        <div className="flex flex-col gap-1.5">
                            <label className="flex cursor-pointer items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={singlePage}
                                    onChange={(e) => {
                                        setSinglePage(e.target.checked);
                                        if (!e.target.checked) setData('target_url', '');
                                    }}
                                    className="rounded border-input"
                                />
                                Scan a single page only
                            </label>
                            {singlePage && (
                                <div className="flex flex-col gap-1">
                                    <Input
                                        id="target_url"
                                        type="url"
                                        placeholder="https://example.com/page"
                                        value={data.target_url}
                                        onChange={(e) => setData('target_url', e.target.value)}
                                        className="w-80"
                                    />
                                    {errors.target_url && (
                                        <p className="text-xs text-destructive">{errors.target_url}</p>
                                    )}
                                </div>
                            )}
                        </div>

                        <Button type="submit" disabled={processing || !data.property_id || (singlePage && !data.target_url)}>
                            {processing ? 'Starting…' : 'Start scan'}
                        </Button>
                    </form>
                </div>

                {/* Scan list */}
                <div className="rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-muted/50">
                            <tr className="text-xs text-muted-foreground">
                                <th className="px-4 py-3 text-left font-medium">Property</th>
                                <th className="px-4 py-3 text-left font-medium">Status</th>
                                <th className="px-4 py-3 text-right font-medium">Pages</th>
                                <th className="px-4 py-3 text-right font-medium">Violations</th>
                                <th className="px-4 py-3 text-left font-medium">Started</th>
                                <th className="px-4 py-3"><span className="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {scans.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-4 py-10 text-center text-sm text-muted-foreground"
                                    >
                                        No scans yet. Run one above.
                                    </td>
                                </tr>
                            ) : (
                                scans.map((scan) => (
                                    <tr key={scan.id} className="transition-colors hover:bg-muted/30">
                                        <td className="px-4 py-3 font-medium">
                                            <div className="flex items-center gap-2">
                                                {scan.property?.name ?? '—'}
                                                {scan.target_url && (
                                                    <Badge variant="outline" className="text-xs">Single page</Badge>
                                                )}
                                            </div>
                                        </td>
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
                                                {scan.canDelete && scan.status !== 'pending' && scan.status !== 'running' && (
                                                    <button
                                                        onClick={() => deleteScan(scan)}
                                                        className="text-sm text-destructive hover:underline"
                                                    >
                                                        Delete
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Overview Dialog */}
            <Dialog open={overview.open} onOpenChange={(open) => { if (!open) setOverview({ open: false }); }}>
                <DialogContent className="max-w-2xl sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>
                            Overview — {overview.open ? overview.scanName : ''}
                        </DialogTitle>
                    </DialogHeader>

                    {overview.open && overview.loading && (
                        <div className="flex items-center justify-center py-10">
                            <Spinner className="h-6 w-6" />
                        </div>
                    )}

                    {overview.open && !overview.loading && (
                        <div className="flex flex-col gap-6">
                            {/* Lighthouse Averages */}
                            <div>
                                <h3 className="mb-3 text-sm font-semibold">Lighthouse Averages</h3>
                                {overview.lighthouseAverages ? (
                                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                                        <OverviewBarCard label="Performance" score={overview.lighthouseAverages.performance} />
                                        <OverviewBarCard label="Accessibility" score={overview.lighthouseAverages.accessibility} />
                                        <OverviewBarCard label="Best Practices" score={overview.lighthouseAverages.best_practices} />
                                        <OverviewBarCard label="SEO" score={overview.lighthouseAverages.seo} />
                                    </div>
                                ) : (
                                    <p className="text-sm text-muted-foreground">No Lighthouse data available.</p>
                                )}
                            </div>

                            {/* WCAG Violations by Severity */}
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

            {/* Schedule scan dialog */}
            <Dialog open={scheduleOpen} onOpenChange={(open) => { if (!open) setScheduleOpen(false); }}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Schedule a scan</DialogTitle>
                    </DialogHeader>

                    {scheduleSaved ? (
                        <div className="flex flex-col gap-4 pt-1">
                            <p className="text-sm text-green-600">Schedule saved successfully.</p>
                            <div className="flex justify-end">
                                <Button variant="outline" onClick={() => setScheduleOpen(false)}>Close</Button>
                            </div>
                        </div>
                    ) : (
                        <form onSubmit={submitSchedule} className="flex flex-col gap-5 pt-1">
                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="s-property">Property</Label>
                                <Select value={schedulePropertyId} onValueChange={setSchedulePropertyId} required>
                                    <SelectTrigger id="s-property">
                                        <SelectValue placeholder="Select a property…" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {properties.map((p) => (
                                            <SelectItem key={p.id} value={String(p.id)}>{p.name}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="flex flex-col gap-1.5">
                                <Label>Scan type</Label>
                                <div className="flex gap-4">
                                    {(['recurring', 'once'] as const).map((t) => (
                                        <label key={t} className="flex cursor-pointer items-center gap-2 text-sm">
                                            <input type="radio" name="s-type" value={t} checked={scheduleType === t} onChange={() => setScheduleType(t)} className="accent-primary" />
                                            <span className="capitalize">{t === 'once' ? 'One-time' : 'Recurring'}</span>
                                        </label>
                                    ))}
                                </div>
                            </div>

                            {scheduleType === 'recurring' && (
                                <div className="flex flex-col gap-4">
                                    <div className="flex flex-col gap-1.5">
                                        <Label htmlFor="s-frequency">Frequency</Label>
                                        <Select value={scheduleFrequency} onValueChange={setScheduleFrequency}>
                                            <SelectTrigger id="s-frequency"><SelectValue /></SelectTrigger>
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
                                            <Label htmlFor="s-dow">Day of week</Label>
                                            <Select value={scheduleDayOfWeek} onValueChange={setScheduleDayOfWeek}>
                                                <SelectTrigger id="s-dow"><SelectValue /></SelectTrigger>
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
                                            <Label htmlFor="s-dom">{scheduleFrequency === 'quarterly' ? 'Day of month (per quarter)' : 'Day of month'}</Label>
                                            <Select value={scheduleDayOfMonth} onValueChange={setScheduleDayOfMonth}>
                                                <SelectTrigger id="s-dom"><SelectValue /></SelectTrigger>
                                                <SelectContent>
                                                    {Array.from({ length: 28 }, (_, i) => (
                                                        <SelectItem key={i + 1} value={String(i + 1)}>{i + 1}</SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    )}
                                    <div className="flex flex-col gap-1.5">
                                        <Label htmlFor="s-time">Time</Label>
                                        <input id="s-time" type="time" value={scheduleTime} onChange={(e) => setScheduleTime(e.target.value)} required className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
                                    </div>
                                </div>
                            )}

                            {scheduleType === 'once' && (
                                <div className="flex flex-col gap-1.5">
                                    <Label htmlFor="s-at">Date &amp; time</Label>
                                    <input id="s-at" type="datetime-local" value={scheduleAt} onChange={(e) => setScheduleAt(e.target.value)} required className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
                                </div>
                            )}

                            {scheduleError && <p className="text-xs text-destructive">{scheduleError}</p>}

                            <div className="ml-auto flex gap-2">
                                <Button type="button" variant="outline" onClick={() => setScheduleOpen(false)}>Cancel</Button>
                                <Button type="submit" disabled={scheduleSubmitting || !schedulePropertyId}>
                                    {scheduleSubmitting ? 'Saving…' : 'Save schedule'}
                                </Button>
                            </div>
                        </form>
                    )}
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}

function OverviewBarCard({ label, score }: { label: string; score: number | null }) {
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
