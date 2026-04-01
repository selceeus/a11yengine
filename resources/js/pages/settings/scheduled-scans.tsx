import { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { index as scheduledScansIndex } from '@/routes/scheduled-scans';
import ScheduledScansController from '@/actions/App/Http/Controllers/Settings/ScheduledScansController';

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
}: {
    scheduledScans: ScheduledScan[];
}) {
    const [scans, setScans] = useState(initialScans);
    const [toggling, setToggling] = useState<number | null>(null);

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

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Scheduled Scans" />

            <div className="flex flex-col gap-6 p-6">
                <div>
                    <h1 className="text-xl font-semibold">Scheduled Scans</h1>
                    <p className="text-sm text-muted-foreground">View and manage all automated scan schedules across your agency.</p>
                </div>

                    {scans.length === 0 ? (
                        <div className="rounded-xl border px-6 py-10 text-center text-sm text-muted-foreground">
                            No scheduled scans configured. Open any property to create one.
                        </div>
                    ) : (
                        <div className="rounded-xl border">
                            <table className="w-full text-sm">
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
                                                        disabled={toggling === scan.id}
                                                        onClick={() => toggleScan(scan)}
                                                    >
                                                        {toggling === scan.id
                                                            ? '…'
                                                            : scan.is_active
                                                              ? 'Pause'
                                                              : 'Resume'}
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
        </AppLayout>
    );
}
