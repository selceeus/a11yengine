import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ExternalLink } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Violation = {
    id: number;
    rule_key: string;
    severity: 'critical' | 'serious' | 'moderate' | 'minor' | 'info';
    wcag_criteria: string | null;
    description: string;
    element_context: string | null;
    page_number: number | null;
};

type PdfDocument = {
    id: number;
    url: string;
    filename: string | null;
    status: 'pending' | 'scanning' | 'completed' | 'failed';
    violation_count: number;
    error_message: string | null;
    scanned_at: string | null;
    property: { id: number; name: string; base_url: string };
    scan: { id: number; status: string };
    violations: Violation[];
};

const SEVERITY_BADGE: Record<Violation['severity'], 'destructive' | 'default' | 'secondary' | 'outline'> = {
    critical: 'destructive',
    serious: 'destructive',
    moderate: 'default',
    minor: 'secondary',
    info: 'outline',
};

export default function Show({ document }: { document: PdfDocument }) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: document.property.name, href: `/properties/${document.property.id}` },
        { title: `Scan #${document.scan.id}`, href: `/scans/${document.scan.id}` },
        { title: document.filename ?? 'PDF Document', href: '#' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`PDF — ${document.filename ?? document.url}`} />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex items-start gap-4">
                    <Link href={`/scans/${document.scan.id}`} className="mt-1 text-muted-foreground hover:text-foreground">
                        <ArrowLeft className="h-5 w-5" />
                    </Link>
                    <div className="flex-1 min-w-0">
                        <h1 className="truncate text-2xl font-semibold">
                            {document.filename ?? 'PDF Document'}
                        </h1>
                        <div className="mt-1 flex items-center gap-2 text-sm text-muted-foreground">
                            <a
                                href={document.url}
                                target="_blank"
                                rel="noreferrer"
                                className="flex items-center gap-1 hover:underline"
                            >
                                <ExternalLink className="h-3.5 w-3.5" />
                                {document.url}
                            </a>
                        </div>
                    </div>
                    <StatusBadge status={document.status} />
                </div>

                {/* Stat cards */}
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                    <div className="rounded border bg-card p-4">
                        <p className="text-xs text-muted-foreground">Property</p>
                        <p className="mt-1 truncate text-sm font-medium">{document.property.name}</p>
                    </div>
                    <div className="rounded border bg-card p-4">
                        <p className="text-xs text-muted-foreground">Violations found</p>
                        <p className="mt-1 text-2xl font-semibold tabular-nums">
                            {document.status === 'completed' ? document.violation_count : '—'}
                        </p>
                    </div>
                    <div className="rounded border bg-card p-4">
                        <p className="text-xs text-muted-foreground">Scanned at</p>
                        <p className="mt-1 text-sm font-medium">
                            {document.scanned_at ? new Date(document.scanned_at).toLocaleString() : '—'}
                        </p>
                    </div>
                </div>

                {/* Error banner */}
                {document.status === 'failed' && document.error_message && (
                    <div className="rounded border border-destructive/30 bg-destructive/5 px-5 py-4 text-sm text-destructive">
                        <span className="font-semibold mr-2">Scan failed:</span>
                        {document.error_message}
                    </div>
                )}

                {/* Pending / scanning state */}
                {(document.status === 'pending' || document.status === 'scanning') && (
                    <div className="rounded border bg-muted/40 px-6 py-5 text-sm text-muted-foreground">
                        <span className="inline-block size-2 animate-pulse rounded-full bg-primary mr-2 align-middle" />
                        PDF accessibility scan in progress…
                    </div>
                )}

                {/* Violations table */}
                {document.status === 'completed' && (
                    document.violations.length > 0 ? (
                        <div>
                            <h2 className="mb-3 text-sm font-semibold">Accessibility Violations</h2>
                            <div className="rounded border">
                                <table className="w-full text-sm data-table">
                                    <caption className="px-4 py-3 text-left text-sm font-medium">
                                        {document.violation_count} violation{document.violation_count !== 1 ? 's' : ''} found
                                    </caption>
                                    <thead className="border-b bg-muted/50">
                                        <tr className="text-xs text-muted-foreground">
                                            <th className="px-4 py-3 text-left font-medium">Rule</th>
                                            <th className="px-4 py-3 text-left font-medium">Severity</th>
                                            <th className="px-4 py-3 text-left font-medium">WCAG</th>
                                            <th className="px-4 py-3 text-left font-medium">Description</th>
                                            <th className="px-4 py-3 text-right font-medium">Page</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {document.violations.map((v) => (
                                            <tr key={v.id} className="transition-colors hover:bg-muted/30">
                                                <td className="px-4 py-3 font-mono text-xs">{v.rule_key}</td>
                                                <td className="px-4 py-3">
                                                    <Badge variant={SEVERITY_BADGE[v.severity]} className="capitalize">
                                                        {v.severity}
                                                    </Badge>
                                                </td>
                                                <td className="px-4 py-3 text-xs text-muted-foreground">
                                                    {v.wcag_criteria ?? '—'}
                                                </td>
                                                <td className="px-4 py-3 text-sm">
                                                    <p>{v.description}</p>
                                                    {v.element_context && (
                                                        <p className="mt-1 font-mono text-xs text-muted-foreground">
                                                            {v.element_context}
                                                        </p>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 text-right tabular-nums text-xs text-muted-foreground">
                                                    {v.page_number ?? '—'}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    ) : (
                        <div className="rounded border px-6 py-10 text-center text-sm text-muted-foreground">
                            No accessibility violations were found in this PDF.
                        </div>
                    )
                )}
            </div>
        </AppLayout>
    );
}

function StatusBadge({ status }: { status: PdfDocument['status'] }) {
    const variants: Record<PdfDocument['status'], 'default' | 'secondary' | 'destructive' | 'outline'> = {
        completed: 'default',
        scanning: 'secondary',
        failed: 'destructive',
        pending: 'outline',
    };

    return (
        <Badge variant={variants[status]} className="mt-1 capitalize shrink-0">
            {status === 'scanning' && (
                <span className="mr-1.5 inline-block size-2 animate-pulse rounded-full bg-current" />
            )}
            {status}
        </Badge>
    );
}
