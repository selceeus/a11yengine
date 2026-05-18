import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { index as scheduledScansIndex } from '@/routes/scheduled-scans';
import ScheduledScansController from '@/actions/App/Http/Controllers/Settings/ScheduledScansController';

type SimpleProperty = { id: number; name: string };
type Organization = { id: number; name: string };
type Property = { id: number; name: string; base_url: string };

type ScheduledScan = {
    id: number;
    type: 'once' | 'recurring';
    frequency: 'daily' | 'weekly' | 'monthly' | 'quarterly' | null;
    is_active: boolean;
    next_run_at: string | null;
    last_run_at: string | null;
    run_time: string | null;
    property: Property | null;
    organization: Organization | null;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Scheduled Scans', href: scheduledScansIndex().url },
];

function frequencyLabel(scan: ScheduledScan): string {
    if (scan.type === 'once') return 'One-time';
    return scan.frequency ? scan.frequency.charAt(0).toUpperCase() + scan.frequency.slice(1) : '—';
}

export default function ScheduledScansIndex({
    scheduledScans: initialScans,
    properties,
}: {
    scheduledScans: ScheduledScan[];
    properties: SimpleProperty[];
}) {
    const [scans, setScans] = useState(initialScans);
    const [toggling, setToggling] = useState<number | null>(null);
    const [deleting, setDeleting] = useState<number | null>(null);

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

    function openScheduleDialog() {
        setSchedulePropertyId('');
        setScheduleType('recurring');
        setScheduleFrequency('weekly');
        setScheduleTime('09:00');
        setScheduleDayOfWeek('1');
        setScheduleDayOfMonth('1');
        setScheduleAt('');
        setScheduleError(null);
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

        setScheduleOpen(false);
        router.reload();
    }

    async function toggleScan(scan: ScheduledScan) {
        setToggling(scan.id);

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

        const res = await fetch(
            `/api/properties/${scan.property?.id}/scheduled-scan/${scan.id}/toggle`,
            {
                method: 'PATCH',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
            },
        );

        setToggling(null);

        if (!res.ok) return;

        const json = await res.json();
        setScans((prev) =>
            prev.map((s) => (s.id === scan.id ? { ...s, is_active: json.scheduledScan.is_active } : s)),
        );
    }

    async function deleteScheduledScan(scan: ScheduledScan) {
        if (!confirm(`Delete the schedule for "${scan.property?.name ?? `Schedule #${scan.id}`}"? This cannot be undone.`)) {
            return;
        }

        setDeleting(scan.id);

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

        const res = await fetch(
            `/api/properties/${scan.property?.id}/scheduled-scan/${scan.id}`,
            {
                method: 'DELETE',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
            },
        );

        setDeleting(null);

        if (!res.ok) return;

        setScans((prev) => prev.filter((s) => s.id !== scan.id));
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Scheduled Scans" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Scheduled Scans</h1>
                        <p className="text-sm text-muted-foreground">View and manage all automated scan schedules across your agency.</p>
                    </div>
                    <Button size="sm" onClick={openScheduleDialog}>Schedule Scan</Button>
                </div>

                    {scans.length === 0 ? (
                        <div className="rounded border px-6 py-10 text-center text-sm text-muted-foreground">
                            No scheduled scans configured. Open any property to create one.
                        </div>
                    ) : (
                        <div className="rounded border">
                            <table className="w-full text-sm data-table">
                                <thead className="border-b bg-muted/50">
                                    <tr className="text-xs text-muted-foreground">
                                        <th className="px-4 py-3 text-left font-medium">Property</th>
                                        <th className="px-4 py-3 text-left font-medium">Organization</th>
                                        <th className="px-4 py-3 text-left font-medium">Frequency</th>
                                        <th className="px-4 py-3 text-left font-medium">Next Run</th>
                                        <th className="px-4 py-3 text-left font-medium">Last Run</th>
                                        <th className="px-4 py-3 text-left font-medium">Status</th>
                                        <th className="px-4 py-3 text-right font-medium">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {scans.map((scan) => (
                                        <tr key={scan.id} className="transition-colors hover:bg-muted/30">
                                            <td className="px-4 py-3">
                                                {scan.property ? (
                                                    <div className="space-y-0.5">
                                                        <div className="font-medium">{scan.property.name}</div>
                                                        <div className="font-mono text-xs text-muted-foreground">
                                                            {scan.property.base_url}
                                                        </div>
                                                    </div>
                                                ) : (
                                                    '—'
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground">
                                                {scan.organization?.name ?? '—'}
                                            </td>
                                            <td className="px-4 py-3">
                                                <span className="capitalize">{frequencyLabel(scan)}</span>
                                                {scan.run_time && (
                                                    <span className="ml-1 text-xs text-muted-foreground">
                                                        @ {scan.run_time}
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground">
                                                {scan.next_run_at
                                                    ? new Date(scan.next_run_at).toLocaleString()
                                                    : '—'}
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground">
                                                {scan.last_run_at
                                                    ? new Date(scan.last_run_at).toLocaleString()
                                                    : 'Never'}
                                            </td>
                                            <td className="px-4 py-3">
                                                <Badge variant={scan.is_active ? 'default' : 'secondary'}>
                                                    {scan.is_active ? 'Active' : 'Paused'}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <div className="flex items-center justify-end gap-3">
                                                    <Link
                                                        href={ScheduledScansController.show(scan.id).url}
                                                        className="text-sm text-primary hover:underline"
                                                    >
                                                        View
                                                    </Link>
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        disabled={toggling === scan.id || deleting === scan.id}
                                                        onClick={() => toggleScan(scan)}
                                                    >
                                                        {toggling === scan.id
                                                            ? '…'
                                                            : scan.is_active
                                                              ? 'Pause'
                                                              : 'Resume'}
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="destructive"
                                                        disabled={toggling === scan.id || deleting === scan.id}
                                                        onClick={() => deleteScheduledScan(scan)}
                                                    >
                                                        {deleting === scan.id ? '…' : 'Delete'}
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
            </div>
            <Dialog open={scheduleOpen} onOpenChange={(open) => { if (!open) setScheduleOpen(false); }}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Schedule a scan</DialogTitle>
                    </DialogHeader>

                    <form onSubmit={submitSchedule} className="flex flex-col gap-5 pt-1">
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="schedule-property">Property</Label>
                            <Select value={schedulePropertyId} onValueChange={setSchedulePropertyId} required>
                                <SelectTrigger id="schedule-property">
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
                                    <input id="s-time" type="time" value={scheduleTime} onChange={(e) => setScheduleTime(e.target.value)} required className="flex h-9 w-full rounded border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
                                </div>
                            </div>
                        )}

                        {scheduleType === 'once' && (
                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="s-at">Date &amp; time</Label>
                                <input id="s-at" type="datetime-local" value={scheduleAt} onChange={(e) => setScheduleAt(e.target.value)} required className="flex h-9 w-full rounded border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
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
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
