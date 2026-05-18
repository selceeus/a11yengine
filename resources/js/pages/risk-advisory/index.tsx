import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { ShieldAlert } from 'lucide-react';
import * as PropertyController from '@/actions/App/Http/Controllers/PropertyController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { PriorityRiskMap, type PriorityItem } from '@/components/charts/PriorityRiskMap';
import { RiskPriorityPanel } from '@/components/RiskPriorityPanel';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type AdvisorySummary = {
    id: number;
    status: 'pending' | 'processing' | 'completed' | 'failed';
    total_recommendations: number | null;
    issues_analyzed: number | null;
    generated_at: string | null;
    error_message: string | null;
};

type PropertyRow = {
    id: number;
    name: string;
    base_url: string;
    latestAdvisory: AdvisorySummary | null;
};

type PageProps = {
    properties: PropertyRow[];
    selectedPropertyId?: number | null;
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Risk Advisory', href: '/risk-advisory' }];

function statusVariant(status: AdvisorySummary['status']): 'default' | 'secondary' | 'destructive' | 'outline' {
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

export default function Index({ properties, selectedPropertyId }: PageProps) {
    const [generating, setGenerating] = useState<Record<number, boolean>>({});
    const [expandedPropertyId, setExpandedPropertyId] = useState<number | null>(selectedPropertyId ?? null);
    const [livePriorities, setLivePriorities] = useState<PriorityItem[]>([]);

    async function handleGenerate(property: PropertyRow) {
        setGenerating((prev) => ({ ...prev, [property.id]: true }));

        try {
            await fetch(`/api/properties/${property.id}/risk-advisory/generate`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
            });

            router.visit(PropertyController.show(property.id).url + '#ai-risk-advisory');
        } catch {
            setGenerating((prev) => ({ ...prev, [property.id]: false }));
        }
    }

    function handleSelectProperty(property: PropertyRow) {
        if (expandedPropertyId === property.id) {
            setExpandedPropertyId(null);
            setLivePriorities([]);
        } else {
            setExpandedPropertyId(property.id);
            setLivePriorities([]);
        }
    }

    const expandedProperty = properties.find((p) => p.id === expandedPropertyId) ?? null;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Risk Advisory" />

            <div className="flex flex-col gap-6 p-6">
                <div>
                    <h1 className="text-2xl font-semibold">Risk Advisory</h1>
                    <p className="text-sm text-muted-foreground">
                        Ranked accessibility fixes ordered by risk-reduction potential and user impact, per property.
                    </p>
                </div>

                <div className="rounded border">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-muted/50">
                            <tr className="text-xs text-muted-foreground">
                                <th className="px-4 py-3 text-left font-medium">Property</th>
                                <th className="px-4 py-3 text-left font-medium">Status</th>
                                <th className="px-4 py-3 text-right font-medium">Recommendations</th>
                                <th className="px-4 py-3 text-right font-medium">Issues Analysed</th>
                                <th className="px-4 py-3 text-left font-medium">Generated</th>
                                <th className="px-4 py-3"><span className="sr-only">Actions</span></th>
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
                                    const advisory = property.latestAdvisory;
                                    const isInProgress =
                                        advisory?.status === 'pending' || advisory?.status === 'processing';
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
                                                {advisory ? (
                                                    <Badge variant={statusVariant(advisory.status)} className="capitalize">
                                                        {advisory.status}
                                                    </Badge>
                                                ) : (
                                                    <span className="text-muted-foreground">—</span>
                                                )}
                                            </td>

                                            <td className="px-4 py-3 text-right tabular-nums text-muted-foreground">
                                                {advisory?.total_recommendations ?? '—'}
                                            </td>

                                            <td className="px-4 py-3 text-right tabular-nums text-muted-foreground">
                                                {advisory?.issues_analyzed ?? '—'}
                                            </td>

                                            <td className="px-4 py-3 text-muted-foreground">
                                                {advisory?.generated_at
                                                    ? new Date(advisory.generated_at).toLocaleDateString()
                                                    : '—'}
                                            </td>

                                            <td className="px-4 py-3" onClick={(e) => e.stopPropagation()}>
                                                <div className="flex items-center justify-end gap-3">
                                                    {advisory ? (
                                                        <>
                                                            <Link
                                                                href={`/risk-advisory/${advisory.id}`}
                                                                className="text-sm text-primary hover:underline"
                                                            >
                                                                Details
                                                            </Link>
                                                            <Link
                                                                href={
                                                                    PropertyController.show(property.id).url +
                                                                    '#ai-risk-advisory'
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
                                                            <ShieldAlert className="mr-1.5 h-3.5 w-3.5" />
                                                        )}
                                                        {advisory ? 'Regenerate' : 'Generate'}
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
                    <div className="grid gap-6 lg:grid-cols-2">
                        <div className="rounded border p-5">
                            <h2 className="mb-4 text-sm font-semibold">
                                Risk Priorities &mdash; {expandedProperty.name}
                            </h2>
                            <RiskPriorityPanel
                                propertyId={expandedProperty.id}
                            />
                        </div>
                        <div className="rounded border p-5">
                            <h2 className="mb-4 text-sm font-semibold">Priority Risk Map</h2>
                            <PriorityRiskMap
                                siteId={expandedProperty.id}
                                priorityItems={livePriorities}
                            />
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
