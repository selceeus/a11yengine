import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Layers } from 'lucide-react';
import GenerateIssueClustersController from '@/actions/App/Http/Controllers/Api/GenerateIssueClustersController';
import PropertyIssueClustersController from '@/actions/App/Http/Controllers/Api/PropertyIssueClustersController';
import * as PropertyController from '@/actions/App/Http/Controllers/PropertyController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type ClusterSummary = {
    id: number;
    status: 'pending' | 'processing' | 'completed' | 'failed';
    total_clusters: number | null;
    open_issues_analyzed: number | null;
    generated_at: string | null;
    error_message: string | null;
};

type PropertyRow = {
    id: number;
    name: string;
    base_url: string;
    latestCluster: ClusterSummary | null;
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Issue Clusters', href: '/issue-clusters' }];

function statusVariant(status: ClusterSummary['status']): 'default' | 'secondary' | 'destructive' | 'outline' {
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

export default function Index({ properties }: { properties: PropertyRow[] }) {
    const [generating, setGenerating] = useState<Record<number, boolean>>({});

    async function handleGenerate(property: PropertyRow) {
        setGenerating((prev) => ({ ...prev, [property.id]: true }));

        try {
            await fetch(GenerateIssueClustersController(property.id).url, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
            });

            // Navigate to property show page where the IssueClusterPanel will poll for progress
            router.visit(PropertyController.show(property.id).url + '#ai-clusters');
        } catch {
            setGenerating((prev) => ({ ...prev, [property.id]: false }));
        }
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Issue Clusters" />

            <div className="flex flex-col gap-6 p-6">
                <div>
                    <h1 className="text-xl font-semibold">AI Issue Clusters</h1>
                    <p className="text-sm text-muted-foreground">
                        AI-grouped accessibility issues by root cause and component, per property.
                    </p>
                </div>

                <div className="rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-muted/50">
                            <tr className="text-xs text-muted-foreground">
                                <th className="px-4 py-3 text-left font-medium">Property</th>
                                <th className="px-4 py-3 text-left font-medium">Status</th>
                                <th className="px-4 py-3 text-right font-medium">Clusters</th>
                                <th className="px-4 py-3 text-right font-medium">Issues Analysed</th>
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
                                    const cluster = property.latestCluster;
                                    const isInProgress = cluster?.status === 'pending' || cluster?.status === 'processing';

                                    return (
                                        <tr key={property.id} className="transition-colors hover:bg-muted/30">
                                            <td className="px-4 py-3">
                                                <Link
                                                    href={PropertyController.show(property.id).url}
                                                    className="font-medium hover:underline"
                                                >
                                                    {property.name}
                                                </Link>
                                                <p className="truncate text-xs text-muted-foreground">{property.base_url}</p>
                                            </td>

                                            <td className="px-4 py-3">
                                                {cluster ? (
                                                    <Badge variant={statusVariant(cluster.status)} className="capitalize">
                                                        {cluster.status}
                                                    </Badge>
                                                ) : (
                                                    <span className="text-muted-foreground">—</span>
                                                )}
                                            </td>

                                            <td className="px-4 py-3 text-right tabular-nums text-muted-foreground">
                                                {cluster?.total_clusters ?? '—'}
                                            </td>

                                            <td className="px-4 py-3 text-right tabular-nums text-muted-foreground">
                                                {cluster?.open_issues_analyzed ?? '—'}
                                            </td>

                                            <td className="px-4 py-3 text-muted-foreground">
                                                {cluster?.generated_at
                                                    ? new Date(cluster.generated_at).toLocaleDateString()
                                                    : '—'}
                                            </td>

                                            <td className="px-4 py-3">
                                                <div className="flex items-center justify-end gap-3">
                                                    {cluster ? (
                                                        <Link
                                                            href={
                                                                PropertyController.show(property.id).url + '#ai-clusters'
                                                            }
                                                            className="text-sm text-primary hover:underline"
                                                        >
                                                            View
                                                        </Link>
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
                                                            <Layers className="mr-1.5 h-3.5 w-3.5" />
                                                        )}
                                                        {cluster ? 'Regenerate' : 'Generate'}
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
            </div>
        </AppLayout>
    );
}
