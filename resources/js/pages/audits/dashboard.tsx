import { Head, Link, router } from '@inertiajs/react';
import { Minus, TrendingDown, TrendingUp } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { AuditScoreTrendChart } from '@/components/charts/AuditScoreTrendChart';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Property = { id: number; name: string };

type AuditCard = {
    id: number;
    title: string;
    status: string;
    overall_score: number | null;
    score_delta: number | null;
    trend_direction: 'improving' | 'declining' | 'stable';
    generated_at: string | null;
    property: Property | null;
};

type PaginatedAudits = {
    data: AuditCard[];
    current_page: number;
    last_page: number;
    next_page_url: string | null;
    prev_page_url: string | null;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'AI Audits', href: '/audits' },
    { title: 'Audit Dashboard', href: '/audits/dashboard' },
];

function scoreColor(score: number | null) {
    if (score === null) return 'bg-muted text-muted-foreground';
    if (score >= 80) return 'bg-green-100 text-green-800';
    if (score >= 50) return 'bg-amber-100 text-amber-800';
    return 'bg-red-100 text-red-800';
}

export default function AuditsDashboard({ audits, properties }: { audits: PaginatedAudits; properties: Property[] }) {
    function filterByProperty(propertyId: string) {
        router.get(
            '/audits/dashboard',
            propertyId ? { property_id: propertyId } : {},
            { preserveState: true, replace: true },
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Audit Dashboard" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold">Audit Dashboard</h1>
                        <p className="text-sm text-muted-foreground">Score trends and history for all completed audits.</p>
                    </div>
                    <Link href="/audits">
                        <Button variant="outline" size="sm">
                            All Audits
                        </Button>
                    </Link>
                </div>

                {/* Property filter */}
                {properties.length > 0 && (
                    <div className="flex flex-wrap gap-2">
                        <button
                            onClick={() => filterByProperty('')}
                            className="rounded-full border px-3 py-1 text-xs font-medium transition-colors hover:bg-muted"
                        >
                            All properties
                        </button>
                        {properties.map((p) => (
                            <button
                                key={p.id}
                                onClick={() => filterByProperty(String(p.id))}
                                className="rounded-full border px-3 py-1 text-xs font-medium transition-colors hover:bg-muted"
                            >
                                {p.name}
                            </button>
                        ))}
                    </div>
                )}

                {/* Score trend chart — uses first audit's property as default */}
                {audits.data.length > 0 && audits.data[0].property && (
                    <div className="rounded-xl border bg-card p-6">
                        <h2 className="mb-4 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                            Score Trend — {audits.data[0].property.name}
                        </h2>
                        <AuditScoreTrendChart propertyId={audits.data[0].property.id} />
                    </div>
                )}

                {/* Audit cards */}
                {audits.data.length === 0 ? (
                    <div className="flex flex-col items-center gap-3 rounded-xl border py-16 text-center">
                        <p className="text-muted-foreground">No completed audits found.</p>
                        <Link href="/audits">
                            <Button size="sm">Run an audit</Button>
                        </Link>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {audits.data.map((audit) => (
                            <Link
                                key={audit.id}
                                href={`/audits/${audit.id}`}
                                className="block rounded-xl border bg-card p-5 transition-colors hover:bg-muted/30"
                            >
                                <div className="mb-3 flex items-start justify-between gap-2">
                                    <div className="min-w-0">
                                        <p className="line-clamp-2 font-medium leading-tight">{audit.title}</p>
                                        {audit.property && (
                                            <p className="mt-0.5 text-xs text-muted-foreground">{audit.property.name}</p>
                                        )}
                                    </div>
                                    <span className={`shrink-0 rounded-full px-3 py-1 text-sm font-bold ${scoreColor(audit.overall_score)}`}>
                                        {audit.overall_score ?? '—'}
                                    </span>
                                </div>

                                <div className="flex items-center justify-between text-xs text-muted-foreground">
                                    <div className="flex items-center gap-1">
                                        {audit.trend_direction === 'improving' && <TrendingUp className="h-3.5 w-3.5 text-green-600" />}
                                        {audit.trend_direction === 'declining' && <TrendingDown className="h-3.5 w-3.5 text-red-600" />}
                                        {audit.trend_direction === 'stable' && <Minus className="h-3.5 w-3.5 text-muted-foreground" />}
                                        {audit.score_delta !== null && (
                                            <span
                                                className={
                                                    audit.trend_direction === 'improving'
                                                        ? 'text-green-600'
                                                        : audit.trend_direction === 'declining'
                                                          ? 'text-red-600'
                                                          : 'text-muted-foreground'
                                                }
                                            >
                                                {audit.score_delta > 0 ? '+' : ''}
                                                {audit.score_delta} vs prev
                                            </span>
                                        )}
                                    </div>
                                    {audit.generated_at && <span>{new Date(audit.generated_at).toLocaleDateString()}</span>}
                                </div>
                            </Link>
                        ))}
                    </div>
                )}

                {/* Pagination */}
                {audits.last_page > 1 && (
                    <div className="flex justify-center gap-2">
                        {audits.prev_page_url && (
                            <Link href={audits.prev_page_url}>
                                <Button variant="outline" size="sm">
                                    Previous
                                </Button>
                            </Link>
                        )}
                        <span className="flex items-center px-3 text-sm text-muted-foreground">
                            Page {audits.current_page} of {audits.last_page}
                        </span>
                        {audits.next_page_url && (
                            <Link href={audits.next_page_url}>
                                <Button variant="outline" size="sm">
                                    Next
                                </Button>
                            </Link>
                        )}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
