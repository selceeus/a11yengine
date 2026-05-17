import { useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Plus, ScrollText, Trash2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Property = { id: number; name: string; base_url: string };

type ReportSummary = {
    id: number;
    report_scope: 'property' | 'agency';
    period_from: string;
    period_to: string;
    status: 'pending' | 'processing' | 'completed' | 'failed';
    is_scheduled: boolean;
    generated_at: string | null;
    error_message: string | null;
    property: Property | null;
};

type PageProps = {
    reports: ReportSummary[];
    properties: Property[];
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Governance', href: '/governance' }];

function statusVariant(status: ReportSummary['status']): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (status) {
        case 'completed':
            return 'default';
        case 'processing':
        case 'pending':
            return 'secondary';
        case 'failed':
            return 'destructive';
    }
}

function today(): string {
    return new Date().toISOString().split('T')[0];
}

function sevenDaysAgo(): string {
    const d = new Date();
    d.setDate(d.getDate() - 7);
    return d.toISOString().split('T')[0];
}

export default function Index({ reports, properties }: PageProps) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [deletingId, setDeletingId] = useState<number | null>(null);

    const { data, setData, post, processing, errors, reset } = useForm({
        report_scope: 'property' as 'property' | 'agency',
        property_id: '' as string,
        period_from: sevenDaysAgo(),
        period_to: today(),
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        post('/governance', {
            onSuccess: () => {
                setDialogOpen(false);
                reset();
            },
        });
    }

    function handleDelete(id: number) {
        if (!confirm('Delete this governance report? This cannot be undone.')) return;
        setDeletingId(id);
        router.delete(`/governance/${id}`, {
            onFinish: () => setDeletingId(null),
        });
    }

    const propertyReports = reports.filter((r) => r.report_scope === 'property');
    const agencyReports = reports.filter((r) => r.report_scope === 'agency');

    function ReportTable({ rows }: { rows: ReportSummary[] }) {
        return (
            <div className="rounded border">
                <table className="w-full text-sm">
                    <thead className="border-b bg-muted/50">
                        <tr className="text-xs text-muted-foreground">
                            <th className="px-4 py-3 text-left font-medium">Scope</th>
                            <th className="px-4 py-3 text-left font-medium">Period</th>
                            <th className="px-4 py-3 text-left font-medium">Status</th>
                            <th className="px-4 py-3 text-left font-medium">Generated</th>
                            <th className="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {rows.length === 0 ? (
                            <tr>
                                <td colSpan={5} className="px-4 py-10 text-center text-sm text-muted-foreground">
                                    No reports yet. Click &ldquo;Generate Report&rdquo; to create one.
                                </td>
                            </tr>
                        ) : (
                            rows.map((report) => (
                                <tr key={report.id} className="hover:bg-muted/30">
                                    <td className="px-4 py-3">
                                        {report.property ? (
                                            <div>
                                                <p className="font-medium">{report.property.name}</p>
                                                <p className="text-xs text-muted-foreground">{report.property.base_url}</p>
                                            </div>
                                        ) : (
                                            <span className="font-medium text-muted-foreground">Agency-wide</span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {report.period_from} &rarr; {report.period_to}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge variant={statusVariant(report.status)} className="capitalize">
                                            {report.status}
                                        </Badge>
                                        {report.is_scheduled && (
                                            <span className="ml-2 text-xs text-muted-foreground">scheduled</span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {report.generated_at
                                            ? new Date(report.generated_at).toLocaleDateString()
                                            : '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center justify-end gap-3">
                                            {report.status === 'completed' && (
                                                <Link
                                                    href={`/governance/${report.id}`}
                                                    className="text-sm text-primary hover:underline"
                                                >
                                                    View
                                                </Link>
                                            )}
                                            <button
                                                onClick={() => handleDelete(report.id)}
                                                disabled={deletingId === report.id}
                                                className="text-muted-foreground hover:text-destructive disabled:opacity-50"
                                                aria-label="Delete report"
                                            >
                                                {deletingId === report.id ? (
                                                    <Spinner className="h-4 w-4" />
                                                ) : (
                                                    <Trash2 className="h-4 w-4" />
                                                )}
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Governance Reports" />

            <div className="flex flex-col gap-6 p-6">
                {/* Page header */}
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Governance Reports</h1>
                        <p className="text-sm text-muted-foreground">
                            Executive-ready accessibility governance reports with generated narratives, risk trends,
                            and traceable recommendations.
                        </p>
                    </div>

                    <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                        <DialogTrigger asChild>
                            <Button size="sm">
                                <Plus className="mr-1.5 h-4 w-4" />
                                Generate Report
                            </Button>
                        </DialogTrigger>
                        <DialogContent className="sm:max-w-md">
                            <DialogHeader>
                                <DialogTitle>Generate Governance Report</DialogTitle>
                            </DialogHeader>
                            <form onSubmit={handleSubmit} className="space-y-4 pt-2">
                                <div className="space-y-1.5">
                                    <Label htmlFor="report_scope">Scope</Label>
                                    <Select
                                        value={data.report_scope}
                                        onValueChange={(v) => setData('report_scope', v as 'property' | 'agency')}
                                    >
                                        <SelectTrigger id="report_scope">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="property">Property</SelectItem>
                                            <SelectItem value="agency">Agency-wide</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {errors.report_scope && (
                                        <p className="text-xs text-destructive">{errors.report_scope}</p>
                                    )}
                                </div>

                                {data.report_scope === 'property' && (
                                    <div className="space-y-1.5">
                                        <Label htmlFor="property_id">Property</Label>
                                        <Select
                                            value={data.property_id}
                                            onValueChange={(v) => setData('property_id', v)}
                                        >
                                            <SelectTrigger id="property_id">
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
                                )}

                                <div className="grid grid-cols-2 gap-3">
                                    <div className="space-y-1.5">
                                        <Label htmlFor="period_from">From</Label>
                                        <Input
                                            id="period_from"
                                            type="date"
                                            value={data.period_from}
                                            onChange={(e) => setData('period_from', e.target.value)}
                                        />
                                        {errors.period_from && (
                                            <p className="text-xs text-destructive">{errors.period_from}</p>
                                        )}
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label htmlFor="period_to">To</Label>
                                        <Input
                                            id="period_to"
                                            type="date"
                                            value={data.period_to}
                                            onChange={(e) => setData('period_to', e.target.value)}
                                        />
                                        {errors.period_to && (
                                            <p className="text-xs text-destructive">{errors.period_to}</p>
                                        )}
                                    </div>
                                </div>

                                <DialogFooter>
                                    <Button type="submit" disabled={processing} className="w-full">
                                        {processing ? (
                                            <Spinner className="mr-1.5 h-4 w-4" />
                                        ) : (
                                            <ScrollText className="mr-1.5 h-4 w-4" />
                                        )}
                                        Generate Report
                                    </Button>
                                </DialogFooter>
                            </form>
                        </DialogContent>
                    </Dialog>
                </div>

                {/* Property reports */}
                <section>
                    <h2 className="mb-3 text-sm font-semibold text-muted-foreground uppercase tracking-wide">
                        Property Reports
                    </h2>
                    <ReportTable rows={propertyReports} />
                </section>

                {/* Agency reports */}
                <section>
                    <h2 className="mb-3 text-sm font-semibold text-muted-foreground uppercase tracking-wide">
                        Agency-Wide Reports
                    </h2>
                    <ReportTable rows={agencyReports} />
                </section>
            </div>
        </AppLayout>
    );
}
