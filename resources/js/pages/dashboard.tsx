import { Head } from '@inertiajs/react';
import { usePage } from '@inertiajs/react';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { IssueSeverityChart } from '@/components/charts/IssueSeverityChart';
import { ScanActivityChart } from '@/components/charts/ScanActivityChart';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { dashboard } from '@/routes';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

export default function Dashboard() {
    const { auth } = usePage().props;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                    <Card className="col-span-full md:col-span-2">
                        <CardHeader>
                            <CardTitle>Issues by Severity</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {auth.agencyId ? (
                                <IssueSeverityChart agencyId={auth.agencyId} />
                            ) : (
                                <p className="text-sm text-muted-foreground">No agency assigned.</p>
                            )}
                        </CardContent>
                    </Card>
                    <Card className="col-span-full md:col-span-1">
                        <CardHeader>
                            <CardTitle>Scan Activity</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {auth.agencyId ? (
                                <ScanActivityChart agencyId={auth.agencyId} />
                            ) : (
                                <p className="text-sm text-muted-foreground">No agency assigned.</p>
                            )}
                        </CardContent>
                    </Card>
                </div>
                <div className="relative min-h-[100vh] flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border">
                    <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                </div>
            </div>
        </AppLayout>
    );
}

