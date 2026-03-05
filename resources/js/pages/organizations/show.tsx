import { Head, Link, useForm } from '@inertiajs/react';
import * as OrganizationController from '@/actions/App/Http/Controllers/OrganizationController';
import * as PropertyController from '@/actions/App/Http/Controllers/PropertyController';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Property = {
    id: number;
    name: string;
    base_url: string;
    status: string;
};

type Organization = {
    id: number;
    name: string;
    domain: string | null;
    status: string;
    properties: Property[];
};

function StatCard({ label, value, capitalize }: { label: string; value: string; capitalize?: boolean }) {
    return (
        <div className="rounded-lg border bg-card p-4">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className={`mt-1 font-medium${capitalize ? ' capitalize' : ''}`}>{value}</dd>
        </div>
    );
}

export default function Show({ organization }: { organization: Organization }) {
    const { delete: destroy, processing } = useForm();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Organizations', href: OrganizationController.index().url },
        { title: organization.name, href: OrganizationController.show(organization.id).url },
    ];

    function handleDelete() {
        if (!confirm(`Delete "${organization.name}"? This cannot be undone.`)) return;
        destroy(OrganizationController.destroy(organization.id).url);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={organization.name} />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex items-start justify-between">
                    <div className="space-y-1">
                        <h1 className="text-xl font-semibold">{organization.name}</h1>
                        {organization.domain && (
                            <p className="text-sm text-muted-foreground">{organization.domain}</p>
                        )}
                    </div>

                    <div className="flex items-center gap-2">
                        <Button variant="outline" asChild>
                            <Link href={OrganizationController.edit(organization.id).url}>Edit</Link>
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
                    <StatCard label="Status" value={organization.status} capitalize />
                    <StatCard label="Properties" value={String(organization.properties.length)} />
                </dl>

                {/* Properties table */}
                <div>
                    <div className="mb-3 flex items-center justify-between">
                        <h2 className="font-medium">Properties</h2>
                        <Button variant="outline" size="sm" asChild>
                            <Link href={PropertyController.create().url}>Add property</Link>
                        </Button>
                    </div>

                    <div className="rounded-xl border">
                        <table className="w-full text-sm">
                            <thead className="border-b bg-muted/50">
                                <tr className="text-xs text-muted-foreground">
                                    <th className="px-4 py-3 text-left font-medium">Name</th>
                                    <th className="px-4 py-3 text-left font-medium">URL</th>
                                    <th className="px-4 py-3 text-left font-medium">Status</th>
                                    <th className="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {organization.properties.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={4}
                                            className="px-4 py-8 text-center text-sm text-muted-foreground"
                                        >
                                            No properties yet.
                                        </td>
                                    </tr>
                                ) : (
                                    organization.properties.map((property) => (
                                        <tr
                                            key={property.id}
                                            className="transition-colors hover:bg-muted/30"
                                        >
                                            <td className="px-4 py-3 font-medium">{property.name}</td>
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
                                            <td className="px-4 py-3 capitalize text-muted-foreground">
                                                {property.status}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <Link
                                                    href={PropertyController.show(property.id).url}
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
                </div>
            </div>
        </AppLayout>
    );
}
