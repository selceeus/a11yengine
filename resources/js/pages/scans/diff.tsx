import { Head, Link } from '@inertiajs/react';
import ScanController from '@/actions/App/Http/Controllers/ScanController';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Property = { id: number; name: string; base_url: string };

type ScanSummary = {
    id: number;
    status: string;
    pages_scanned: number | null;
    total_violations: number | null;
    created_at: string;
    property: Property | null;
};

type ComparableScan = {
    id: number;
    created_at: string;
    pages_scanned: number | null;
    total_violations: number | null;
};

type FindingRow = {
    id: number;
    rule_key: string;
    severity: 'critical' | 'serious' | 'moderate' | 'minor' | 'info';
    page_url: string;
    element_identifier: string | null;
    message: string | null;
    wcag_criteria: string | null;
};

const SEVERITY_COLOURS: Record<FindingRow['severity'], string> = {
    critical: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
    serious: 'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-300',
    moderate: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300',
    minor: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
    info: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
};

function FindingsTable({ findings, emptyMessage }: { findings: FindingRow[]; emptyMessage: string }) {
    if (findings.length === 0) {
        return (
            <div className="rounded border px-6 py-8 text-center text-sm text-muted-foreground">
                {emptyMessage}
            </div>
        );
    }

    return (
        <div className="rounded border">
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead className="border-b bg-muted/50">
                        <tr className="text-xs text-muted-foreground">
                            <th className="px-4 py-3 text-left font-medium">Rule</th>
                            <th className="px-4 py-3 text-left font-medium">Severity</th>
                            <th className="px-4 py-3 text-left font-medium">Page</th>
                            <th className="px-4 py-3 text-left font-medium">Element</th>
                            <th className="px-4 py-3 text-left font-medium">WCAG</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {findings.map((f) => (
                            <tr key={f.id} className="transition-colors hover:bg-muted/30">
                                <td className="px-4 py-3 font-mono text-xs">{f.rule_key}</td>
                                <td className="px-4 py-3">
                                    <span
                                        className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize ${SEVERITY_COLOURS[f.severity] ?? ''}`}
                                    >
                                        {f.severity}
                                    </span>
                                </td>
                                <td className="max-w-xs truncate px-4 py-3 font-mono text-xs">
                                    <a
                                        href={f.page_url}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="hover:underline"
                                    >
                                        {f.page_url}
                                    </a>
                                </td>
                                <td className="max-w-xs truncate px-4 py-3 font-mono text-xs text-muted-foreground">
                                    {f.element_identifier ?? '—'}
                                </td>
                                <td className="px-4 py-3 text-xs text-muted-foreground">
                                    {f.wcag_criteria ?? '—'}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

export default function Diff({
    scan,
    comparableScan,
    newFindings,
    resolvedFindings,
    unchangedCount,
}: {
    scan: ScanSummary;
    comparableScan: ComparableScan | null;
    newFindings: FindingRow[];
    resolvedFindings: FindingRow[];
    unchangedCount: number;
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Scans', href: ScanController.index().url },
        { title: scan.property?.name ?? `Scan #${scan.id}`, href: ScanController.show(scan.id).url },
        { title: 'Comparison', href: `/scans/${scan.id}/diff` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Comparison — ${scan.property?.name ?? `Scan #${scan.id}`}`} />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex items-start justify-between">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-semibold pb-2">
                            Scan comparison — {scan.property?.name ?? `#${scan.id}`}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {scan.property?.base_url}
                        </p>
                    </div>
                </div>

                {/* Scan pair summary */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div className="rounded border bg-card p-4">
                        <p className="text-xs text-muted-foreground mb-1">Current scan</p>
                        <p className="font-semibold">#{scan.id}</p>
                        <p className="text-xs text-muted-foreground mt-1">
                            {new Date(scan.created_at).toLocaleString()} · {scan.pages_scanned ?? '—'} pages · {scan.total_violations ?? '—'} violations
                        </p>
                    </div>
                    {comparableScan ? (
                        <div className="rounded border bg-card p-4">
                            <p className="text-xs text-muted-foreground mb-1">Previous scan</p>
                            <p className="font-semibold">
                                <Link
                                    href={ScanController.show(comparableScan.id).url}
                                    className="text-primary hover:underline"
                                >
                                    #{comparableScan.id}
                                </Link>
                            </p>
                            <p className="text-xs text-muted-foreground mt-1">
                                {new Date(comparableScan.created_at).toLocaleString()} · {comparableScan.pages_scanned ?? '—'} pages · {comparableScan.total_violations ?? '—'} violations
                            </p>
                        </div>
                    ) : (
                        <div className="rounded border bg-muted/30 p-4 flex items-center justify-center">
                            <p className="text-sm text-muted-foreground">No previous completed scan found</p>
                        </div>
                    )}
                </div>

                {comparableScan ? (
                    <>
                        {/* Summary bar */}
                        <div className="flex flex-wrap items-center gap-6 rounded border bg-muted/20 px-5 py-3 text-sm">
                            <div className="flex items-center gap-2">
                                <span className="text-2xl font-bold text-red-600">+{newFindings.length}</span>
                                <span className="text-muted-foreground">new findings</span>
                            </div>
                            <div className="flex items-center gap-2">
                                <span className="text-2xl font-bold text-green-600">−{resolvedFindings.length}</span>
                                <span className="text-muted-foreground">resolved</span>
                            </div>
                            <div className="flex items-center gap-2">
                                <span className="text-2xl font-bold text-muted-foreground">{unchangedCount}</span>
                                <span className="text-muted-foreground">unchanged</span>
                            </div>
                        </div>

                        {/* New findings */}
                        <div>
                            <div className="mb-3 flex items-center gap-2">
                                <h2 className="text-sm font-semibold">New findings</h2>
                                <Badge variant="destructive">{newFindings.length}</Badge>
                            </div>
                            <FindingsTable
                                findings={newFindings}
                                emptyMessage="No new findings — great work!"
                            />
                        </div>

                        {/* Resolved findings */}
                        <div>
                            <div className="mb-3 flex items-center gap-2">
                                <h2 className="text-sm font-semibold">Resolved findings</h2>
                                <Badge variant="default">{resolvedFindings.length}</Badge>
                            </div>
                            <FindingsTable
                                findings={resolvedFindings}
                                emptyMessage="No findings were resolved in this scan."
                            />
                        </div>
                    </>
                ) : (
                    <div className="rounded border px-6 py-12 text-center text-sm text-muted-foreground">
                        A comparison requires at least two completed scans for this property.
                    </div>
                )}

                <div className="text-sm">
                    <Link
                        href={ScanController.show(scan.id).url}
                        className="text-primary hover:underline"
                    >
                        ← Back to scan
                    </Link>
                </div>
            </div>
        </AppLayout>
    );
}
