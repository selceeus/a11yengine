import { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import ScanController from '@/actions/App/Http/Controllers/ScanController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
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
    total_violations: number | null;
    created_at: string;
    property: Property | null;
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
    const { data, setData, post, processing, errors } = useForm({ property_id: '' });
    const [overview, setOverview] = useState<OverviewState>({ open: false });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post(ScanController.store().url);
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

                        <Button type="submit" disabled={processing || !data.property_id}>
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
                                <th className="px-4 py-3"></th>
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
                                            {scan.property?.name ?? '—'}
                                        </td>
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
