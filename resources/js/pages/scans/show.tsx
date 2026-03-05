import { useEffect } from 'react';
import { Head, Link, usePoll } from '@inertiajs/react';
import ScanController from '@/actions/App/Http/Controllers/ScanController';
import { Badge } from '@/components/ui/badge';
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

export default function Show({ scan }: { scan: Scan }) {
    const isActive = scan.status === 'pending' || scan.status === 'running';
    const { start, stop } = usePoll(3000, {}, { autoStart: false });

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

                {/* Pages table */}
                {scan.scan_pages.length > 0 && (
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

                {/* Empty completed state */}
                {scan.status === 'completed' && scan.scan_pages.length === 0 && (
                    <div className="rounded-xl border px-6 py-10 text-center text-sm text-muted-foreground">
                        No pages were recorded for this scan.
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

function StatCard({ label, value }: { label: string; value: string | number }) {
    return (
        <div className="rounded-xl border bg-card p-4">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="mt-1 text-xl font-semibold tabular-nums">{value}</p>
        </div>
    );
}
