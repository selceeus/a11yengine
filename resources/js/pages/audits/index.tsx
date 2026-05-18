import { useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Bot, Trash2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Property = { id: number; name: string };

type Audit = {
    id: number;
    title: string;
    status: 'pending' | 'processing' | 'completed' | 'failed';
    overall_score: number | null;
    generated_at: string | null;
    created_at: string;
    property: Property | null;
};

type PaginatedAudits = {
    data: Audit[];
    current_page: number;
    last_page: number;
    next_page_url: string | null;
    prev_page_url: string | null;
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Audits', href: '/audits' }];

function statusVariant(status: Audit['status']): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (status) {
        case 'completed':
            return 'default';
        case 'processing':
            return 'secondary';
        case 'failed':
            return 'destructive';
        default:
            return 'outline';
    }
}

export default function Index({ audits, properties }: { audits: PaginatedAudits; properties: Property[] }) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({ property_id: '', title: '' });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post('/audits', {
            onSuccess: () => {
                setDialogOpen(false);
                reset();
            },
        });
    }

    function deleteAudit(audit: Audit) {
        if (!confirm(`Delete "${audit.title}"? This cannot be undone.`)) {
            return;
        }
        router.delete(`/audits/${audit.id}`);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Audits" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">Accessibility Audits</h1>
                        <p className="text-sm text-muted-foreground">Automated accessibility analysis for your properties.</p>
                    </div>
                    <Button onClick={() => setDialogOpen(true)}>
                        <Bot className="mr-2 h-4 w-4" />
                        Generate audit
                    </Button>
                </div>

                <div className="rounded border">
                    <table className="w-full text-sm data-table">
                        <thead className="border-b bg-muted/50">
                            <tr className="text-xs text-muted-foreground">
                                <th className="px-4 py-3 text-left font-medium">Title</th>
                                <th className="px-4 py-3 text-left font-medium">Property</th>
                                <th className="px-4 py-3 text-left font-medium">Status</th>
                                <th className="px-4 py-3 text-right font-medium">Score</th>
                                <th className="px-4 py-3 text-left font-medium">Generated</th>
                                <th className="px-4 py-3"><span className="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {audits.data.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="px-4 py-10 text-center text-sm text-muted-foreground">
                                        No audits yet. Click "Generate audit" to create one.
                                    </td>
                                </tr>
                            ) : (
                                audits.data.map((audit) => (
                                    <tr key={audit.id} className="transition-colors hover:bg-muted/30">
                                        <td className="px-4 py-3 font-medium">{audit.title}</td>
                                        <td className="px-4 py-3 text-muted-foreground">{audit.property?.name ?? '—'}</td>
                                        <td className="px-4 py-3">
                                            <Badge variant={statusVariant(audit.status)}>{audit.status}</Badge>
                                        </td>
                                        <td className="px-4 py-3 text-right tabular-nums">
                                            {audit.overall_score !== null ? `${audit.overall_score}/100` : '—'}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {audit.generated_at ? new Date(audit.generated_at).toLocaleString() : '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center justify-end gap-3">
                                                <Link href={`/audits/${audit.id}`} className="text-sm text-primary hover:underline">
                                                    View
                                                </Link>
                                                <button
                                                    onClick={() => deleteAudit(audit)}
                                                    className="text-muted-foreground hover:text-destructive cursor-pointer"
                                                    aria-label="Delete audit"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {(audits.prev_page_url || audits.next_page_url) && (
                    <div className="flex items-center justify-between text-sm">
                        {audits.prev_page_url ? (
                            <Link href={audits.prev_page_url} className="text-primary hover:underline">
                                Previous
                            </Link>
                        ) : <span />}
                        <span className="text-muted-foreground">
                            Page {audits.current_page} of {audits.last_page}
                        </span>
                        {audits.next_page_url ? (
                            <Link href={audits.next_page_url} className="text-primary hover:underline">
                                Next
                            </Link>
                        ) : <span />}
                    </div>
                )}
            </div>

            {/* Generate Audit Dialog */}
            <Dialog open={dialogOpen} onOpenChange={(open) => { setDialogOpen(open); if (!open) reset(); }}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Generate Audit</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={submit} className="flex flex-col gap-4">
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="property_id">Property <span className="text-destructive">*</span></Label>
                            <Select value={data.property_id} onValueChange={(v) => setData('property_id', v)}>
                                <SelectTrigger id="property_id cursor-pointer">
                                    <SelectValue placeholder="Select a property…" />
                                </SelectTrigger>
                                <SelectContent>
                                    {properties.map((p) => (
                                        <SelectItem className="cursor-pointer" key={p.id} value={String(p.id)}>
                                            {p.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.property_id && <p className="text-xs text-destructive">{errors.property_id}</p>}
                        </div>

                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="title">Title <span className="text-muted-foreground text-xs">(optional)</span></Label>
                            <Input
                                id="title"
                                value={data.title}
                                onChange={(e) => setData('title', e.target.value)}
                                placeholder="Auto-generated if left blank"
                            />
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => { setDialogOpen(false); reset(); }}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={processing || !data.property_id}>
                                {processing ? 'Generating…' : 'Generate'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
