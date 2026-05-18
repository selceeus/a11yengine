import { Head, Link, router } from '@inertiajs/react';
import * as PropertyController from '@/actions/App/Http/Controllers/PropertyController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
    industry: string | null;
    industry_label: string | null;
    legal_risk_level: 'high' | 'medium' | 'low' | null;
    status: string;
    organization: Organization | null;
};

function riskVariant(risk: 'high' | 'medium' | 'low' | null): 'destructive' | 'default' | 'secondary' | 'outline' {
    switch (risk) {
        case 'high':
            return 'destructive';
        case 'medium':
            return 'default';
        case 'low':
            return 'secondary';
        default:
            return 'outline';
    }
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Properties', href: PropertyController.index().url },
];

export default function Index({ properties, filters = {} }: { properties: Property[]; filters?: { search?: string } }) {
    function search(value: string) {
        router.get(PropertyController.index().url, value ? { search: value } : {}, { preserveState: true, replace: true });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Properties" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">Properties</h1>
                    <Button asChild>
                        <Link href={PropertyController.create().url}>Add property</Link>
                    </Button>
                </div>

                <Input
                    type="search"
                    className="w-64"
                    placeholder="Search properties…"
                    defaultValue={filters.search ?? ''}
                    onChange={(e) => search(e.target.value)}
                    aria-label="Search properties"
                />

                <div className="rounded border">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-muted/100">
                            <tr className="text-sm text-muted-foreground">
                                <th className="px-4 py-3 text-left font-semibold">Name</th>
                                <th className="px-4 py-3 text-left font-semibold">URL</th>
                                <th className="px-4 py-3 text-left font-semibold">Organization</th>
                                <th className="px-4 py-3 text-left font-semibold">Industry</th>
                                <th className="px-4 py-3 text-left font-semibold">Legal Risk</th>
                                <th className="px-4 py-3 text-left font-semibold">Status</th>
                                <th className="px-4 py-3"><span className="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {properties.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="px-4 py-10 text-center text-semibold text-md text-muted-foreground"
                                    >
                                        No properties yet.{' '}
                                        <Link
                                            href={PropertyController.create().url}
                                            className="text-primary hover:underline"
                                        >
                                            Add one
                                        </Link>
                                        .
                                    </td>
                                </tr>
                            ) : (
                                properties.map((property) => (
                                    <tr
                                        key={property.id}
                                        className="transition-colors hover:bg-muted/30"
                                    >
                                        <td className="px-4 py-3 font-semibold">{property.name}</td>
                                        <td className="px-4 py-3 font-mono text-xs text-muted-foreground">
                                            <a
                                                href={property.base_url}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="hover:underline"
                                            >
                                                {property.base_url}
                                            </a>
                                        </td>
                                        <td className="px-4 py-3 text-xs text-muted-foreground">
                                            {property.organization?.name ?? '—'}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {property.industry_label ?? '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {property.legal_risk_level ? (
                                                <Badge variant={riskVariant(property.legal_risk_level)} className="capitalize">
                                                    {property.legal_risk_level}
                                                </Badge>
                                            ) : (
                                                <span className="text-muted-foreground">—</span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 capitalize text-muted-foreground">
                                            {property.status}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <Link
                                                href={PropertyController.show(property.id).url}
                                                className="text-md font-semibold text-primary hover:underline"
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
            </div>
        </AppLayout>
    );
}
