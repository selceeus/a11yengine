import { Head, Link, router } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Property = {
    id: number;
    name: string;
};

type Journey = {
    id: number;
    name: string;
    description: string | null;
    steps_count: number;
    property: Property | null;
    created_at: string;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Journeys', href: '/journeys' },
];

export default function Index({ journeys }: { journeys: Journey[] }) {
    function destroy(journey: Journey) {
        if (!confirm(`Delete "${journey.name}"? This cannot be undone.`)) {
            return;
        }
        router.delete(`/journeys/${journey.id}`);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="User Journeys" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex items-center justify-between gap-4">
                    <h1 className="text-xl font-semibold">User Journeys</h1>
                    <Button asChild size="sm">
                        <Link href="/journeys/create">New Journey</Link>
                    </Button>
                </div>

                {journeys.length === 0 ? (
                    <div className="rounded-xl border bg-card p-8 text-center text-sm text-muted-foreground">
                        No journeys yet. Create one to scan a sequence of pages.
                    </div>
                ) : (
                    <div className="rounded-xl border bg-card">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="px-4 py-3 font-medium">Name</th>
                                    <th className="px-4 py-3 font-medium">Property</th>
                                    <th className="px-4 py-3 font-medium">Steps</th>
                                    <th className="px-4 py-3 font-medium" />
                                </tr>
                            </thead>
                            <tbody>
                                {journeys.map((journey) => (
                                    <tr key={journey.id} className="border-b last:border-0">
                                        <td className="px-4 py-3 font-medium">
                                            {journey.name}
                                            {journey.description && (
                                                <p className="text-xs text-muted-foreground">{journey.description}</p>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {journey.property?.name ?? '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge variant="secondary">{journey.steps_count} steps</Badge>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center justify-end gap-2">
                                                <Button asChild variant="outline" size="sm">
                                                    <Link href={`/journeys/${journey.id}/edit`}>Edit</Link>
                                                </Button>
                                                <Button
                                                    variant="destructive"
                                                    size="sm"
                                                    onClick={() => destroy(journey)}
                                                >
                                                    Delete
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
