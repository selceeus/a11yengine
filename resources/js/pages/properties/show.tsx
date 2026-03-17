import { Head, Link, useForm } from '@inertiajs/react';
import * as PropertyController from '@/actions/App/Http/Controllers/PropertyController';
import ScanController from '@/actions/App/Http/Controllers/ScanController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { PropertyRiskTrendsChart } from '@/components/charts/PropertyRiskTrendsChart';
import { PropertyScanActivityChart } from '@/components/charts/PropertyScanActivityChart';
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
}: {
    property: Property;
    recentScans: Scan[];
    lighthouseAverages: LighthouseAverages;
}) {
    const { delete: destroy, processing } = useForm();

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

                {/* Recent scans */}
                <div className="rounded-xl border">
                    <div className="flex items-center justify-between border-b px-4 py-3">
                        <h2 className="text-sm font-semibold">Recent scans</h2>
                        <Button size="sm" asChild>
                            <Link href={ScanController.index().url}>Run scan</Link>
                        </Button>
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
                                            <Link
                                                href={ScanController.show(scan.id).url}
                                                className="text-sm text-primary hover:underline"
                                            >
                                                View
                                            </Link>
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
