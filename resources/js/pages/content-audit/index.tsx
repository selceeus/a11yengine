import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import * as PropertyController from '@/actions/App/Http/Controllers/PropertyController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ContentAuditPanel } from '@/components/ContentAuditPanel';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type AuditSummary = {
    id: number;
    status: 'pending' | 'processing' | 'completed' | 'failed';
    total_issues: number | null;
    pages_analyzed: number | null;
    generated_at: string | null;
    error_message: string | null;
};

type PropertyRow = {
    id: number;
    name: string;
    base_url: string;
    latestAudit: AuditSummary | null;
};

type PageProps = {
    properties: PropertyRow[];
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Content Audit', href: '/content-audit' }];

function statusVariant(status: AuditSummary['status']): 'default' | 'secondary' | 'destructive' | 'outline' {
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

function getCsrfToken(): string {
    return (document.head.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
}

export default function Index({ properties }: PageProps) {
    const [generating, setGenerating] = useState<Record<number, boolean>>({});
    const [expandedPropertyId, setExpandedPropertyId] = useState<number | null>(null);

    async function handleGenerate(property: PropertyRow) {
        setGenerating((prev) => ({ ...prev, [property.id]: true }));

        try {
            await fetch(`/api/properties/${property.id}/content-audit/generate`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
            });

            router.visit(PropertyController.show(property.id).url + '#content-audit');
        } catch {
            setGenerating((prev) => ({ ...prev, [property.id]: false }));
        }
    }

    function handleSelectProperty(property: PropertyRow) {
        setExpandedPropertyId((prev) => (prev === property.id ? null : property.id));
    }

    const expandedProperty = properties.find((p) => p.id === expandedPropertyId) ?? null;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Content Audit" />

            <div className="flex flex-col gap-6 p-6">
                <div>
                    <h1 className="text-2xl font-semibold">Content Audit</h1>
                    <p className="text-sm text-muted-foreground">
                        Detect content-level accessibility issues that automated scanners miss — vague links, missing alt
                        text, unlabelled forms, and poor heading structure.
                    </p>
                </div>

                <div className="rounded border">
                    <table className="w-full text-sm data-table">
                        <thead className="border-b bg-muted/50">
                            <tr className="text-xs text-muted-foreground">
                                <th className="px-4 py-3 text-left font-medium">Property</th>
                                <th className="px-4 py-3 text-left font-medium">Status</th>
                                <th className="px-4 py-3 text-right font-medium">Issues Found</th>
                                <th className="px-4 py-3 text-right font-medium">Pages Analysed</th>
                                <th className="px-4 py-3 text-left font-medium">Generated</th>
                                <th className="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {properties.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="px-4 py-10 text-center text-sm text-muted-foreground">
                                        No properties found.
                                    </td>
                                </tr>
                            ) : (
                                properties.map((property) => {
                                    const audit = property.latestAudit;
                                    const isInProgress =
                                        audit?.status === 'pending' || audit?.status === 'processing';
                                    const isSelected = expandedPropertyId === property.id;

                                    return (
                                        <tr
                                            key={property.id}
                                            className={`cursor-pointer transition-colors hover:bg-muted/30 ${isSelected ? 'bg-muted/20' : ''}`}
                                            onClick={() => handleSelectProperty(property)}
                                        >
                                            <td className="px-4 py-3">
                                                <Link
                                                    href={PropertyController.show(property.id).url}
                                                    className="font-medium hover:underline"
                                                    onClick={(e) => e.stopPropagation()}
                                                >
                                                    {property.name}
                                                </Link>
                                                <p className="truncate text-xs text-muted-foreground">{property.base_url}</p>
                                            </td>

                                            <td className="px-4 py-3">
                                                {audit ? (
                                                    <Badge variant={statusVariant(audit.status)} className="capitalize">
                                                        {audit.status}
                                                    </Badge>
                                                ) : (
                                                    <span className="text-muted-foreground">—</span>
                                                )}
                                            </td>

                                            <td className="px-4 py-3 text-right tabular-nums text-muted-foreground">
                                                {audit?.total_issues ?? '—'}
                                            </td>

                                            <td className="px-4 py-3 text-right tabular-nums text-muted-foreground">
                                                {audit?.pages_analyzed ?? '—'}
                                            </td>

                                            <td className="px-4 py-3 text-muted-foreground">
                                                {audit?.generated_at
                                                    ? new Date(audit.generated_at).toLocaleDateString()
                                                    : '—'}
                                            </td>

                                            <td className="px-4 py-3" onClick={(e) => e.stopPropagation()}>
                                                <div className="flex items-center justify-end gap-3">
                                                    {audit ? (
                                                        <>
                                                            <Link
                                                                href={`/content-audit/${audit.id}`}
                                                                className="text-sm text-primary hover:underline"
                                                            >
                                                                Details
                                                            </Link>
                                                            <Link
                                                                href={
                                                                    PropertyController.show(property.id).url +
                                                                    '#content-audit'
                                                                }
                                                                className="text-sm text-primary hover:underline"
                                                            >
                                                                View
                                                            </Link>
                                                        </>
                                                    ) : null}

                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        disabled={generating[property.id] ?? isInProgress}
                                                        onClick={() => handleGenerate(property)}
                                                    >
                                                        {generating[property.id] || isInProgress ? (
                                                            <Spinner className="mr-1.5 h-3.5 w-3.5" />
                                                        ) : (
                                                            <FileText className="mr-1.5 h-3.5 w-3.5" />
                                                        )}
                                                        {audit ? 'Regenerate' : 'Generate'}
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })
                            )}
                        </tbody>
                    </table>
                </div>

                {expandedProperty && (
                    <div className="rounded border p-5">
                        <h2 className="mb-4 text-sm font-semibold">
                            Content Issues &mdash; {expandedProperty.name}
                        </h2>
                        <ContentAuditPanel propertyId={expandedProperty.id} />
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
