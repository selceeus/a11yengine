import { Head, Link } from '@inertiajs/react';
import * as PropertyController from '@/actions/App/Http/Controllers/PropertyController';
import { Button } from '@/components/ui/button';
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
    status: string;
    organization: Organization | null;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Properties', href: PropertyController.index().url },
];

export default function Index({ properties }: { properties: Property[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Properties" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Properties</h1>
                    <Button asChild>
                        <Link href={PropertyController.create().url}>Add property</Link>
                    </Button>
                </div>

                <div className="rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-muted/50">
                            <tr className="text-xs text-muted-foreground">
                                <th className="px-4 py-3 text-left font-medium">Name</th>
                                <th className="px-4 py-3 text-left font-medium">URL</th>
                                <th className="px-4 py-3 text-left font-medium">Organization</th>
                                <th className="px-4 py-3 text-left font-medium">Status</th>
                                <th className="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {properties.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-10 text-center text-sm text-muted-foreground"
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
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {property.organization?.name ?? '—'}
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
        </AppLayout>
    );
}
