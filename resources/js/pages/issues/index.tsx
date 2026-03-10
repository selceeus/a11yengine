import { Head, Link, router } from '@inertiajs/react';
import * as IssueController from '@/actions/App/Http/Controllers/IssueController';
import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Property = { id: number; name: string };
type Organization = { id: number; name: string };

type Issue = {
    id: number;
    rule_key: string;
    severity: string;
    status: string;
    occurrence_count: number;
    risk_weight: number | null;
    wcag_category: string | null;
    last_detected_at: string;
    property: Property | null;
    organization: Organization | null;
};

type PaginatedIssues = {
    data: Issue[];
    current_page: number;
    last_page: number;
    next_page_url: string | null;
    prev_page_url: string | null;
};

type Filters = {
    status?: string;
    severity?: string;
    property_id?: string;
};

// in the component props:
export default function Index({
    issues,
    filters,
    properties,
}: {
    issues: PaginatedIssues;
    filters: Filters;
    properties: Property[];
}) {

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Issues', href: IssueController.index().url },
];

const severityVariant: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    critical: 'destructive',
    high: 'destructive',
    medium: 'default',
    low: 'secondary',
};

const statusLabels: Record<string, string> = {
    open: 'Open',
    in_progress: 'In progress',
    resolved: 'Resolved',
    accepted_risk: 'Accepted risk',
};

function filter(patch: Partial<Filters>, current: Filters) {
    const next = { ...current, ...patch };
    // remove falsy values
    Object.keys(next).forEach((k) => !(next as Record<string, unknown>)[k] && delete (next as Record<string, unknown>)[k]);
    router.get(IssueController.index().url, next as Record<string, string>, { preserveState: true, replace: true });
}

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Issues" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Issues</h1>
                </div>

                {/* Filters */}
                <div className="flex flex-wrap gap-3">
                    <Select
                        value={filters.property_id ?? 'all'}
                        onValueChange={(v) => filter({ property_id: v === 'all' ? '' : v }, filters)}
                    >
                        <SelectTrigger className="w-48">
                            <SelectValue placeholder="All properties" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All properties</SelectItem>
                            {properties.map((p) => (
                                <SelectItem key={p.id} value={String(p.id)}>
                                    {p.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select
                        value={filters.status ?? 'all'}
                        onValueChange={(v) => filter({ status: v === 'all' ? '' : v }, filters)}
                    >
                        <SelectTrigger className="w-40">
                            <SelectValue placeholder="All statuses" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All statuses</SelectItem>
                            <SelectItem value="open">Open</SelectItem>
                            <SelectItem value="in_progress">In progress</SelectItem>
                            <SelectItem value="resolved">Resolved</SelectItem>
                            <SelectItem value="accepted_risk">Accepted risk</SelectItem>
                        </SelectContent>
                    </Select>

                    <Select
                        value={filters.severity ?? 'all'}
                        onValueChange={(v) => filter({ severity: v === 'all' ? '' : v }, filters)}
                    >
                        <SelectTrigger className="w-36">
                            <SelectValue placeholder="All severities" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All severities</SelectItem>
                            <SelectItem value="critical">Critical</SelectItem>
                            <SelectItem value="high">High</SelectItem>
                            <SelectItem value="medium">Medium</SelectItem>
                            <SelectItem value="low">Low</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                {/* Table */}
                <div className="rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-muted/50">
                            <tr className="text-xs text-muted-foreground">
                                <th className="px-4 py-3 text-left font-medium">Rule</th>
                                <th className="px-4 py-3 text-left font-medium">Property</th>
                                <th className="px-4 py-3 text-left font-medium">Severity</th>
                                <th className="px-4 py-3 text-left font-medium">WCAG category</th>
                                <th className="px-4 py-3 text-left font-medium">Status</th>
                                <th className="px-4 py-3 text-left font-medium">Occurrences</th>
                                <th className="px-4 py-3 text-right font-medium">Risk weight</th>
                                <th className="px-4 py-3 text-left font-medium">Last detected</th>
                                <th className="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {issues.data.length === 0 ? (
                                <tr>
                                    <td colSpan={9} className="px-4 py-10 text-center text-sm text-muted-foreground">
                                        No issues found.
                                    </td>
                                </tr>
                            ) : (
                                issues.data.map((issue) => (
                                    <tr key={issue.id} className="transition-colors hover:bg-muted/30">
                                        <td className="px-4 py-3 font-mono text-xs">{issue.rule_key}</td>
                                        <td className="px-4 py-3 text-muted-foreground">{issue.property?.name ?? '—'}</td>
                                        <td className="px-4 py-3">
                                            <Badge variant={severityVariant[issue.severity] ?? 'outline'} className="capitalize">
                                                {issue.severity}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground capitalize">
                                            {issue.wcag_category?.replace('-', ' ') ?? '—'}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {statusLabels[issue.status] ?? issue.status}
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">{issue.occurrence_count}</td>
                                        <td className="px-4 py-3 text-right tabular-nums text-muted-foreground">{issue.risk_weight ?? '—'}</td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {new Date(issue.last_detected_at).toLocaleDateString()}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <Link
                                                href={IssueController.show(issue.id).url}
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

                {/* Pagination */}
                {(issues.prev_page_url || issues.next_page_url) && (
                    <div className="flex items-center justify-between text-sm text-muted-foreground">
                        <span>Page {issues.current_page} of {issues.last_page}</span>
                        <div className="flex gap-2">
                            {issues.prev_page_url && (
                                <Link href={issues.prev_page_url} className="text-primary hover:underline">Previous</Link>
                            )}
                            {issues.next_page_url && (
                                <Link href={issues.next_page_url} className="text-primary hover:underline">Next</Link>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

