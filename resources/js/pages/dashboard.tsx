import { Head, WhenVisible, usePage } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import { useState } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
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

type ActivityEntry = {
    id: number;
    event: string;
    event_label: string;
    event_category: string;
    actor_type: 'user' | 'api_key' | 'system';
    actor_label: string;
    subject_type: string | null;
    subject_id: number | null;
    subject_label: string | null;
    metadata: Record<string, unknown> | null;
    ip_address: string | null;
    created_at: string;
};

const CATEGORY_COLOURS: Record<string, string> = {
    authentication: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    team: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
    api: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
    scan: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    issue: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    audit: 'bg-teal-100 text-teal-800 dark:bg-teal-900 dark:text-teal-200',
    settings: 'bg-slate-100 text-slate-800 dark:bg-slate-900 dark:text-slate-200',
};

function relativeTime(iso: string): string {
    const diff = Date.now() - new Date(iso).getTime();
    const mins = Math.floor(diff / 60_000);
    if (mins < 1) return 'just now';
    if (mins < 60) return `${mins}m ago`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return `${hrs}h ago`;
    return `${Math.floor(hrs / 24)}d ago`;
}

function ActivityFeed({ feed }: { feed: ActivityEntry[] | null }) {
    if (!feed || feed.length === 0) {
        return <p className="py-8 text-center text-sm text-muted-foreground">No activity recorded yet.</p>;
    }

    return (
        <ul className="divide-y divide-border">
            {feed.map((entry) => (
                <li key={entry.id} className="flex items-start gap-3 py-3">
                    <span
                        className={`mt-0.5 inline-flex shrink-0 items-center rounded px-2 py-0.5 text-xs font-medium ${CATEGORY_COLOURS[entry.event_category] ?? 'bg-muted text-muted-foreground'}`}
                    >
                        {entry.event_category}
                    </span>
                    <div className="min-w-0 flex-1">
                        <p className="text-sm font-medium leading-snug">
                            <span className="text-foreground">{entry.actor_label}</span>
                            {' — '}
                            <span className="text-muted-foreground">{entry.event_label}</span>
                            {entry.subject_label && (
                                <span className="text-muted-foreground">
                                    {': '}
                                    <span className="font-medium text-foreground">{entry.subject_label}</span>
                                </span>
                            )}
                        </p>
                        <p className="mt-0.5 text-xs text-muted-foreground">
                            {relativeTime(entry.created_at)}
                            {entry.ip_address && (
                                <span className="ml-2 font-mono opacity-60">{entry.ip_address}</span>
                            )}
                        </p>
                    </div>
                </li>
            ))}
        </ul>
    );
}

export default function Dashboard() {
    const { auth, defaultPropertyId, latestAudits, ragIndexed, activityFeed } = usePage().props as {
        auth: Auth;
        defaultPropertyId: number | null;
        latestAudits: AuditSummary[] | null;
        ragIndexed: boolean;
        activityFeed: ActivityEntry[] | null;
    };
    const [ragDismissed, setRagDismissed] = useState(false);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded p-4">
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
                <Tabs defaultValue="overview">
                    <TabsList>
                        <TabsTrigger value="overview">Overview</TabsTrigger>
                        <TabsTrigger value="activity">Activity</TabsTrigger>
                    </TabsList>
                    <TabsContent value="overview" className="mt-4 flex flex-col gap-4">
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
                        <div className="relative min-h-screen flex-1 overflow-hidden rounded md:min-h-min">
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
                        <div className="relative min-h-screen flex-1 overflow-hidden rounded md:min-h-min">
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
                        <div className="relative min-h-screen flex-1 overflow-hidden rounded md:min-h-min">
                            <Card className="col-span-full">
                                <CardHeader>
                                    <CardTitle>Accessibility Risk Landscape</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <AccessibilityRiskLandscapeBarChart siteId={defaultPropertyId} />
                                </CardContent>
                            </Card>
                        </div>
                        <div className="relative min-h-screen flex-1 overflow-hidden rounded md:min-h-min">
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
                                                    <div key={i} className="h-24 animate-pulse rounded border bg-muted" />
                                                ))}
                                            </div>
                                        }
                                    >
                                        <AuditSummaryCards audits={latestAudits ?? []} />
                                    </WhenVisible>
                                </CardContent>
                            </Card>
                        </div>
                    </TabsContent>
                    <TabsContent value="activity" className="mt-4">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between">
                                <CardTitle>Activity Feed</CardTitle>
                                <Link
                                    href="/settings/activity-log/export"
                                    className="text-sm text-primary hover:underline"
                                >
                                    Export CSV
                                </Link>
                            </CardHeader>
                            <CardContent>
                                <WhenVisible
                                    data="activityFeed"
                                    fallback={
                                        <div className="flex flex-col gap-3 py-2">
                                            {[0, 1, 2, 3, 4].map((i) => (
                                                <div key={i} className="h-10 animate-pulse rounded bg-muted" />
                                            ))}
                                        </div>
                                    }
                                >
                                    <ActivityFeed feed={activityFeed} />
                                </WhenVisible>
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </div>
        </AppLayout>
    );
}

