import { Head, WhenVisible, usePage } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import { useState } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { AccessibilityRiskLandscapeBarChart } from '@/components/charts/AccessibilityRiskLandscapeBarChart';
import { IssueSeverityChart } from '@/components/charts/IssueSeverityChart';
import { OrgRiskTrendsChart } from '@/components/charts/OrgRiskTrendsChart';
import { ScanActivityChart } from '@/components/charts/ScanActivityChart';
import { TopAtRiskPropertiesBarChart } from '@/components/charts/TopAtRiskPropertiesBarChart';
import { AuditSummaryCards } from '@/components/AuditSummaryCards';
import type { AuditSummary } from '@/components/AuditSummaryCards';
import AppLayout from '@/layouts/app-layout';
import type { Auth, BreadcrumbItem } from '@/types';
import { dashboard } from '@/routes';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

export default function Dashboard() {
    const { auth, defaultPropertyId, latestAudits, ragIndexed } = usePage().props as {
        auth: Auth;
        defaultPropertyId: number | null;
        latestAudits: AuditSummary[] | null;
        ragIndexed: boolean;
    };
    const [ragDismissed, setRagDismissed] = useState(false);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                {!ragIndexed && !ragDismissed && (
                    <Alert variant="destructive" className="flex items-start justify-between gap-4">
                        <div>
                            <AlertTitle>AI Knowledge Base Not Indexed</AlertTitle>
                            <AlertDescription>
                                The WCAG knowledge base has not been indexed yet. AI-powered audit and risk features may produce lower-quality results.{' '}
                                Run <code className="rounded bg-muted px-1 py-0.5 font-mono text-xs">php artisan rag:index-wcag</code> to populate it.
                            </AlertDescription>
                        </div>
                        <button
                            type="button"
                            onClick={() => setRagDismissed(true)}
                            className="shrink-0 rounded p-1 hover:bg-destructive/20"
                            aria-label="Dismiss warning"
                        >
                            ✕
                        </button>
                    </Alert>
                )}
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
                <div className="relative min-h-[100vh] flex-1 overflow-hidden rounded-xl md:min-h-min">
                    <Card className="col-span-full">
                        <CardHeader>
                            <CardTitle>Organisation Risk Trends</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {auth.agencyId ? (
                                <OrgRiskTrendsChart agencyId={auth.agencyId} />
                            ) : (
                                <p className="text-sm text-muted-foreground">No agency assigned.</p>
                            )}
                        </CardContent>
                    </Card>
                </div>
                <div className="relative min-h-[100vh] flex-1 overflow-hidden rounded-xl md:min-h-min">
                    <Card className="col-span-full">
                        <CardHeader>
                            <CardTitle>Top At-Risk Properties</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {auth.agencyId ? (
                                <TopAtRiskPropertiesBarChart agencyId={auth.agencyId} />
                            ) : (
                                <p className="text-sm text-muted-foreground">No agency assigned.</p>
                            )}
                        </CardContent>
                    </Card>
                </div>
                <div className="relative min-h-[100vh] flex-1 overflow-hidden rounded-xl md:min-h-min">
                    <Card className="col-span-full">
                        <CardHeader>
                            <CardTitle>Accessibility Risk Landscape</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <AccessibilityRiskLandscapeBarChart siteId={defaultPropertyId} />
                        </CardContent>
                    </Card>
                </div>
                <div className="relative min-h-[100vh] flex-1 overflow-hidden rounded-xl md:min-h-min">
                    <Card className="col-span-full">
                        <CardHeader className="flex flex-row items-center justify-between">
                            <CardTitle>Latest Audit Scores</CardTitle>
                            <Link href="/audits/dashboard" className="text-sm text-primary hover:underline">
                                View all
                            </Link>
                        </CardHeader>
                        <CardContent>
                            <WhenVisible
                                data="latestAudits"
                                fallback={
                                    <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                        {[0, 1, 2].map((i) => (
                                            <div key={i} className="h-24 animate-pulse rounded-xl border bg-muted" />
                                        ))}
                                    </div>
                                }
                            >
                                <AuditSummaryCards audits={latestAudits ?? []} />
                            </WhenVisible>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}

