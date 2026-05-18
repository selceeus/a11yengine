import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Globe } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { index as scheduledScansIndex } from '@/routes/scheduled-scans';
import * as PropertyController from '@/actions/App/Http/Controllers/PropertyController';

type Organization = { id: number; name: string };
type Property = { id: number; name: string; base_url: string };

type ScheduledScan = {
    id: number;
    type: 'once' | 'recurring';
    frequency: 'daily' | 'weekly' | 'monthly' | 'quarterly' | null;
    scheduled_at: string | null;
    run_time: string | null;
    timezone: string | null;
    run_day_of_week: number | null;
    run_day_of_month: number | null;
    next_run_at: string | null;
    last_run_at: string | null;
    is_active: boolean;
    property: Property | null;
    organization: Organization | null;
};

type RecentScan = {
    id: number;
    status: 'pending' | 'running' | 'completed' | 'failed';
    pages_scanned: number | null;
    total_violations: number | null;
    started_at: string | null;
    completed_at: string | null;
};

const DAY_NAMES = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

function frequencyLabel(scan: ScheduledScan): string {
    if (scan.type === 'once') {
        return 'One-time';
    }
    return scan.frequency ? scan.frequency.charAt(0).toUpperCase() + scan.frequency.slice(1) : '—';
}

function scheduleDetail(scan: ScheduledScan): string {
    const time = scan.run_time ?? '(no time set)';
    const tz = scan.timezone ?? 'UTC';

    if (scan.type === 'once') {
        return scan.scheduled_at ? `Runs once at ${new Date(scan.scheduled_at).toLocaleString()}` : 'One-time (no date set)';
    }

    if (scan.frequency === 'daily') {
        return `Every day at ${time} (${tz})`;
    }

    if (scan.frequency === 'weekly') {
        const day = scan.run_day_of_week !== null ? DAY_NAMES[scan.run_day_of_week] ?? 'unknown day' : 'unknown day';
        return `Every ${day} at ${time} (${tz})`;
    }

    if (scan.frequency === 'monthly') {
        const dom = scan.run_day_of_month ?? 1;
        return `Monthly on day ${dom} at ${time} (${tz})`;
    }

    if (scan.frequency === 'quarterly') {
        const dom = scan.run_day_of_month ?? 1;
        return `Quarterly on day ${dom} at ${time} (${tz})`;
    }

    return '—';
}

function scanStatusVariant(status: RecentScan['status']): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (status) {
        case 'completed': return 'default';
        case 'running': return 'secondary';
        case 'failed': return 'destructive';
        default: return 'outline';
    }
}

export default function ScheduledScanShow({
    scheduledScan,
    recentScans,
}: {
    scheduledScan: ScheduledScan;
    recentScans: RecentScan[];
}) {
    const [deleting, setDeleting] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Scheduled Scans', href: scheduledScansIndex().url },
        { title: scheduledScan.property?.name ?? `Scan #${scheduledScan.id}`, href: '#' },
    ];

    async function deleteScheduledScan() {
        if (!confirm(`Delete the schedule for "${scheduledScan.property?.name ?? `Schedule #${scheduledScan.id}`}"? This cannot be undone.`)) {
            return;
        }

        setDeleting(true);

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

        const res = await fetch(
            `/api/properties/${scheduledScan.property?.id}/scheduled-scan/${scheduledScan.id}`,
            {
                method: 'DELETE',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
            },
        );

        setDeleting(false);

        if (!res.ok) return;

        router.visit(scheduledScansIndex().url);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={scheduledScan.property?.name ?? `Scheduled Scan #${scheduledScan.id}`} />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex items-start justify-between gap-4">
                    <div className="flex items-start gap-4">
                        <Button variant="ghost" size="sm" asChild className="mt-0.5">
                            <Link href={scheduledScansIndex().url}>
                                <ArrowLeft className="mr-1.5 h-4 w-4" />
                                Back
                            </Link>
                        </Button>

                        <div>
                            <div className="flex items-center gap-3">
                                <h1 className="text-2xl font-semibold">
                                    {scheduledScan.property?.name ?? `Scheduled Scan #${scheduledScan.id}`}
                                </h1>
                                <Badge variant={scheduledScan.is_active ? 'default' : 'secondary'}>
                                    {scheduledScan.is_active ? 'Active' : 'Paused'}
                                </Badge>
                            </div>
                            {scheduledScan.property && (
                                <p className="mt-1 font-mono text-sm text-muted-foreground">
                                    {scheduledScan.property.base_url}
                                </p>
                            )}
                        </div>
                    </div>

                    <Button
                        size="sm"
                        variant="destructive"
                        disabled={deleting}
                        onClick={deleteScheduledScan}
                    >
                        {deleting ? 'Deleting…' : 'Delete'}
                    </Button>
                </div>

                <Separator />

                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Schedule details */}
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle className="text-base">Schedule</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid grid-cols-2 gap-x-6 gap-y-4 text-sm">
                                <div>
                                    <dt className="text-muted-foreground">Type</dt>
                                    <dd className="mt-0.5 font-medium capitalize">{frequencyLabel(scheduledScan)}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">Schedule</dt>
                                    <dd className="mt-0.5 font-medium">{scheduleDetail(scheduledScan)}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">Next Run</dt>
                                    <dd className="mt-0.5 font-medium">
                                        {scheduledScan.next_run_at
                                            ? new Date(scheduledScan.next_run_at).toLocaleString()
                                            : '—'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">Last Run</dt>
                                    <dd className="mt-0.5 font-medium">
                                        {scheduledScan.last_run_at
                                            ? new Date(scheduledScan.last_run_at).toLocaleString()
                                            : 'Never'}
                                    </dd>
                                </div>
                                {scheduledScan.timezone && (
                                    <div>
                                        <dt className="text-muted-foreground">Timezone</dt>
                                        <dd className="mt-0.5 font-medium">{scheduledScan.timezone}</dd>
                                    </div>
                                )}
                            </dl>
                        </CardContent>
                    </Card>

                    {/* Property & org */}
                    <div className="flex flex-col gap-4">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Property</CardTitle>
                            </CardHeader>
                            <CardContent className="text-sm">
                                {scheduledScan.property ? (
                                    <div className="space-y-1">
                                        <Link
                                            href={PropertyController.show(scheduledScan.property.id).url}
                                            className="flex items-center gap-1.5 font-medium text-primary hover:underline"
                                        >
                                            <Globe className="h-4 w-4 shrink-0" />
                                            {scheduledScan.property.name}
                                        </Link>
                                        <p className="font-mono text-xs text-muted-foreground">
                                            {scheduledScan.property.base_url}
                                        </p>
                                    </div>
                                ) : (
                                    <p className="text-muted-foreground">No property linked</p>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Organization</CardTitle>
                            </CardHeader>
                            <CardContent className="text-sm">
                                {scheduledScan.organization ? (
                                    <p className="font-medium">{scheduledScan.organization.name}</p>
                                ) : (
                                    <p className="text-muted-foreground">—</p>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>

                {/* Recent scans */}
                <div>
                    <h2 className="mb-3 text-base font-semibold">Recent Scans</h2>

                    {recentScans.length === 0 ? (
                        <div className="rounded border px-6 py-10 text-center text-sm text-muted-foreground">
                            No scans have been run for this property yet.
                        </div>
                    ) : (
                        <div className="rounded border">
                            <table className="w-full text-sm data-table">
                                <thead className="border-b bg-muted/50">
                                    <tr className="text-xs text-muted-foreground">
                                        <th className="px-4 py-3 text-left font-medium">Started</th>
                                        <th className="px-4 py-3 text-left font-medium">Completed</th>
                                        <th className="px-4 py-3 text-left font-medium">Status</th>
                                        <th className="px-4 py-3 text-right font-medium">Pages</th>
                                        <th className="px-4 py-3 text-right font-medium">Violations</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {recentScans.map((scan) => (
                                        <tr key={scan.id} className="transition-colors hover:bg-muted/30">
                                            <td className="px-4 py-3 text-muted-foreground">
                                                {scan.started_at
                                                    ? new Date(scan.started_at).toLocaleString()
                                                    : '—'}
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground">
                                                {scan.completed_at
                                                    ? new Date(scan.completed_at).toLocaleString()
                                                    : '—'}
                                            </td>
                                            <td className="px-4 py-3">
                                                <Badge variant={scanStatusVariant(scan.status)}>
                                                    {scan.status}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-3 text-right tabular-nums">
                                                {scan.pages_scanned ?? '—'}
                                            </td>
                                            <td className="px-4 py-3 text-right tabular-nums">
                                                {scan.total_violations ?? '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
