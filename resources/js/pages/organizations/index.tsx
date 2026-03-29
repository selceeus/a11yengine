import { Head, Link, router } from '@inertiajs/react';
import * as OrganizationController from '@/actions/App/Http/Controllers/OrganizationController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Organization = {
    id: number;
    name: string;
    domain: string | null;
    status: string;
    properties_count: number;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Organizations', href: OrganizationController.index().url },
];

export default function Index({ organizations, filters = {} }: { organizations: Organization[]; filters?: { search?: string } }) {
    function search(value: string) {
        router.get(OrganizationController.index().url, value ? { search: value } : {}, { preserveState: true, replace: true });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Organizations" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Organizations</h1>
                    <Button asChild>
                        <Link href={OrganizationController.create().url}>Add organization</Link>
                    </Button>
                </div>

                <Input
                    type="search"
                    className="w-64"
                    placeholder="Search organizations…"
                    defaultValue={filters.search ?? ''}
                    onChange={(e) => search(e.target.value)}
                    aria-label="Search organizations"
                />

                <div className="rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-muted/50">
                            <tr className="text-xs text-muted-foreground">
                                <th className="px-4 py-3 text-left font-medium">Name</th>
                                <th className="px-4 py-3 text-left font-medium">Domain</th>
                                <th className="px-4 py-3 text-left font-medium">Status</th>
                                <th className="px-4 py-3 text-left font-medium">Properties</th>
                                <th className="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {organizations.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-10 text-center text-sm text-muted-foreground"
                                    >
                                        No organizations yet.{' '}
                                        <Link
                                            href={OrganizationController.create().url}
                                            className="text-primary hover:underline"
                                        >
                                            Add one
                                        </Link>
                                        .
                                    </td>
                                </tr>
                            ) : (
                                organizations.map((org) => (
                                    <tr
                                        key={org.id}
                                        className="transition-colors hover:bg-muted/30"
                                    >
                                        <td className="px-4 py-3 font-medium">{org.name}</td>
                                        <td className="px-4 py-3 font-mono text-xs text-muted-foreground">
                                            {org.domain ?? '—'}
                                        </td>
                                        <td className="px-4 py-3 capitalize text-muted-foreground">
                                            {org.status}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {org.properties_count}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <Link
                                                href={OrganizationController.show(org.id).url}
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
