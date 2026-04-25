import { Head, Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Settings', href: '/settings/profile' },
    { title: 'SOC2 Evidence', href: '/settings/soc2-evidence' },
];

type Stats = {
    user_count: number;
    two_factor_count: number;
    two_factor_adoption_pct: number;
    api_key_count: number;
    active_api_key_count: number;
};

type LastReview = {
    period: string;
    completed_at: string;
    completed_by: string | null;
} | null;

type PendingReview = {
    id: number;
    period: string;
    due_at: string;
} | null;

export default function Soc2Evidence({
    stats,
    lastReview,
    pendingReview,
}: {
    stats: Stats;
    lastReview: LastReview;
    pendingReview: PendingReview;
}) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="SOC2 Evidence" />

            <div className="space-y-6 p-6">
                <div>
                    <h1 className="text-2xl font-semibold">SOC2 Evidence Package</h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Download audit evidence for SOC2 CC6/CC7/CC8 controls. Share these exports with your auditor.
                    </p>
                </div>

                {/* Stats overview */}
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <Card>
                        <CardHeader className="pb-1">
                            <CardDescription>Team Members</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <p className="text-3xl font-bold">{stats.user_count}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-1">
                            <CardDescription>2FA Adoption</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <p className="text-3xl font-bold">{stats.two_factor_adoption_pct}%</p>
                            <p className="text-muted-foreground text-xs">
                                {stats.two_factor_count} of {stats.user_count} users
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-1">
                            <CardDescription>API Keys</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <p className="text-3xl font-bold">{stats.active_api_key_count}</p>
                            <p className="text-muted-foreground text-xs">active of {stats.api_key_count} total</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-1">
                            <CardDescription>Last Access Review</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {lastReview ? (
                                <>
                                    <p className="text-lg font-semibold">{lastReview.period}</p>
                                    <p className="text-muted-foreground text-xs">
                                        {new Date(lastReview.completed_at).toLocaleDateString()}
                                    </p>
                                </>
                            ) : (
                                <p className="text-muted-foreground text-sm">None yet</p>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {pendingReview && (
                    <Card className="border-amber-300 bg-amber-50 dark:border-amber-700 dark:bg-amber-950">
                        <CardHeader>
                            <CardTitle className="text-base">Access Review Due</CardTitle>
                            <CardDescription>
                                A SOC2 access review for <strong>{pendingReview.period}</strong> is pending. Due{' '}
                                {new Date(pendingReview.due_at).toLocaleDateString()}.
                            </CardDescription>
                        </CardHeader>
                        <CardFooter>
                            <Button asChild size="sm">
                                <Link href={`/settings/access-reviews/${pendingReview.id}`}>Start Review</Link>
                            </Button>
                        </CardFooter>
                    </Card>
                )}

                {/* Evidence downloads */}
                <div className="grid gap-4 sm:grid-cols-2">
                    <EvidenceCard
                        title="Activity Log"
                        description="All security-relevant events (logins, API key usage, issue changes, scans) for the last 365 days. Covers SOC2 CC6, CC7, CC8."
                        downloadHref="/settings/activity-log/export"
                        filename="activity-log.csv"
                    />
                    <EvidenceCard
                        title="User Roles & Access"
                        description="All agency users with their assigned roles, scope, 2FA status, and last login date."
                        downloadHref="/settings/soc2-evidence/export/user-roles"
                        filename="user-roles.csv"
                    />
                    <EvidenceCard
                        title="API Key Inventory"
                        description="All API keys with scopes, creation date, last-used date, expiry, and revocation status."
                        downloadHref="/settings/soc2-evidence/export/api-keys"
                        filename="api-keys.csv"
                    />
                    <EvidenceCard
                        title="Access Review History"
                        description="Record of all completed quarterly access reviews including who completed them and when."
                        downloadHref="/settings/soc2-evidence/export/access-reviews"
                        filename="access-reviews.csv"
                    />
                </div>
            </div>
        </AppLayout>
    );
}

function EvidenceCard({
    title,
    description,
    downloadHref,
    filename,
}: {
    title: string;
    description: string;
    downloadHref: string;
    filename: string;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">{title}</CardTitle>
                <CardDescription>{description}</CardDescription>
            </CardHeader>
            <CardFooter>
                <Button asChild variant="outline" size="sm">
                    <a href={downloadHref} download={filename}>
                        Download CSV
                    </a>
                </Button>
            </CardFooter>
        </Card>
    );
}
