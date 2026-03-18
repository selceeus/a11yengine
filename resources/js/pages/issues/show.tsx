import { Head, Link, router } from '@inertiajs/react';
import * as IssueController from '@/actions/App/Http/Controllers/IssueController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import IssueAssigner, { type AssignableUser } from '@/components/IssueAssigner';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Property = { id: number; name: string; base_url: string };
type Organization = { id: number; name: string };
type Finding = {
    id: number;
    rule_key: string;
    severity: string;
    wcag_criteria: string | null;
    description: string | null;
    tags: string[] | null;
    help_url: string | null;
    element_identifier: string | null;
    element_html: string | null;
    page_url: string;
    message: string | null;
    detected_at: string;
};

type Issue = {
    id: number;
    rule_key: string;
    page_url: string;
    severity: string;
    status: string;
    wcag_category: string | null;
    wcag_criteria: string | null;
    description: string | null;
    tags: string[] | null;
    help_url: string | null;
    occurrence_count: number;
    risk_weight: number;
    first_detected_at: string;
    last_detected_at: string;
    resolved_at: string | null;
    assigned_user_id: number | null;
    assigned_user: { id: number; name: string; email: string } | null;
    property: Property | null;
    organization: Organization | null;
    findings: Finding[];
};

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

function StatCard({ label, value, capitalize }: { label: string; value: string; capitalize?: boolean }) {
    return (
        <div className="rounded-lg border bg-card p-4">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className={`mt-1 font-medium${capitalize ? ' capitalize' : ''}`}>{value}</dd>
        </div>
    );
}

export default function Show({ issue, assignableUsers }: { issue: Issue; assignableUsers: AssignableUser[] }) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Issues', href: IssueController.index().url },
        { title: issue.rule_key, href: IssueController.show(issue.id).url },
    ];

    function handleStatusChange(status: string) {
        router.patch(IssueController.update(issue.id).url, { status });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={issue.rule_key} />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex items-start justify-between gap-4">
                    <div className="space-y-1">
                        <h1 className="font-mono text-2xl font-semibold pb-4">{issue.rule_key}</h1>
                        {issue.description && (
                            <p className="text-md text-foreground">{issue.description}</p>
                        )}
                        {issue.tags && issue.tags.length > 0 && (
                            <div className="flex flex-wrap gap-2 pt-4 pb-4">
                            <h5>Tags:</h5>
                                {issue.tags.map((tag) => (
                                    <Badge key={tag} variant="outline" className="text-xs">
                                        {tag}
                                    </Badge>
                                ))}
                            </div>
                        )}
                        {issue.help_url && (
                            <a
                                href={issue.help_url}
                                target="_blank"
                                rel="noreferrer"
                                className="text-sm text-primary hover:underline"
                            >
                                Learn more about this rule ↗
                            </a>
                        )}
                    </div>

                    <div className="flex items-center gap-2 shrink-0">
                        <Badge variant={severityVariant[issue.severity] ?? 'outline'} className="capitalize">
                            {issue.severity}
                        </Badge>

                        <Select value={issue.status} onValueChange={handleStatusChange}>
                            <SelectTrigger className="w-40">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="open">Open</SelectItem>
                                <SelectItem value="in_progress">In progress</SelectItem>
                                <SelectItem value="resolved">Resolved</SelectItem>
                                <SelectItem value="accepted_risk">Accepted risk</SelectItem>
                            </SelectContent>
                        </Select>

                        <IssueAssigner issue={issue} users={assignableUsers} canAssign={true} />
                    </div>
                </div>

                {/* Meta */}
                <dl className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <StatCard label="Property" value={issue.property?.name ?? '—'} />
                    <StatCard label="Organization" value={issue.organization?.name ?? '—'} />
                    <StatCard label="Occurrences" value={String(issue.occurrence_count)} />
                    <StatCard label="Risk weight" value={String(issue.risk_weight)} />
                </dl>

                {(issue.wcag_criteria || issue.wcag_category) && (
                    <dl className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        {issue.wcag_criteria && (
                            <StatCard label="WCAG criterion" value={issue.wcag_criteria} />
                        )}
                        {issue.wcag_category && (
                            <StatCard label="WCAG principle" value={issue.wcag_category.replace('-', ' ')} capitalize />
                        )}
                    </dl>
                )}

                <dl className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                    <StatCard label="First detected" value={new Date(issue.first_detected_at).toLocaleDateString()} />
                    <StatCard label="Last detected" value={new Date(issue.last_detected_at).toLocaleDateString()} />
                    <StatCard
                        label="Days open"
                        value={`${Math.floor(
                            (new Date(issue.resolved_at ?? Date.now()).getTime() - new Date(issue.first_detected_at).getTime()) /
                                (1000 * 60 * 60 * 24),
                        )} days`}
                    />
                    {issue.resolved_at && (
                        <StatCard label="Resolved" value={new Date(issue.resolved_at).toLocaleDateString()} />
                    )}
                </dl>

                {/* Findings */}
                <div>
                    <h2 className="mb-3 font-medium">Recent findings</h2>
                    <div className="rounded-xl border">
                        <table className="w-full text-sm">
                            <thead className="border-b bg-muted/50">
                                <tr className="text-xs text-muted-foreground">
                                    <th className="px-4 py-3 text-left font-medium">Page</th>
                                    <th className="px-4 py-3 text-left font-medium">Element</th>
                                    <th className="px-4 py-3 text-left font-medium">HTML</th>
                                    <th className="px-4 py-3 text-left font-medium">WCAG</th>
                                    <th className="px-4 py-3 text-left font-medium">Severity</th>
                                    <th className="px-4 py-3 text-left font-medium">Detected</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {issue.findings.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="px-4 py-8 text-center text-sm text-muted-foreground">
                                            No findings.
                                        </td>
                                    </tr>
                                ) : (
                                    issue.findings.map((finding) => (
                                        <tr key={finding.id} className="transition-colors hover:bg-muted/30">
                                            <td className="px-4 py-3 font-mono text-xs text-muted-foreground">
                                                <a href={finding.page_url} target="_blank" rel="noreferrer" className="hover:underline">
                                                    {finding.page_url}
                                                </a>
                                            </td>
                                            <td className="px-4 py-3 font-mono text-xs text-muted-foreground">
                                                {finding.element_identifier ?? '—'}
                                            </td>
                                            <td className="max-w-xs px-4 py-3">
                                                {finding.element_html ? (
                                                    <code className="block truncate font-mono text-xs text-muted-foreground" title={finding.element_html}>
                                                        {finding.element_html}
                                                    </code>
                                                ) : '—'}
                                            </td>
                                            <td className="px-4 py-3 text-xs text-muted-foreground">
                                                {finding.wcag_criteria
                                                    ? <span title={finding.description ?? undefined}>{finding.wcag_criteria}</span>
                                                    : '—'}
                                            </td>
                                            <td className="px-4 py-3">
                                                <Badge variant={severityVariant[finding.severity] ?? 'outline'} className="capitalize">
                                                    {finding.severity}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground">
                                                {new Date(finding.detected_at).toLocaleDateString()}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div>
                    <Link href={IssueController.index().url} className="text-sm text-muted-foreground hover:text-foreground">
                        ← Back to issues
                    </Link>
                </div>
            </div>
        </AppLayout>
    );
}
