import { Head, Link, useForm } from '@inertiajs/react';
import * as IssueController from '@/actions/App/Http/Controllers/IssueController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Property = { id: number; name: string; base_url: string };
type Organization = { id: number; name: string };
type Finding = {
    id: number;
    rule_key: string;
    severity: string;
    element_identifier: string | null;
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
    occurrence_count: number;
    risk_weight: number;
    first_detected_at: string;
    last_detected_at: string;
    resolved_at: string | null;
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

export default function Show({ issue }: { issue: Issue }) {
    const { data, setData, patch, processing } = useForm({ status: issue.status });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Issues', href: IssueController.index().url },
        { title: issue.rule_key, href: IssueController.show(issue.id).url },
    ];

    function handleStatusChange(status: string) {
        setData('status', status);
        patch(IssueController.update(issue.id).url);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={issue.rule_key} />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex items-start justify-between gap-4">
                    <div className="space-y-1">
                        <h1 className="font-mono text-xl font-semibold">{issue.rule_key}</h1>
                        <a
                            href={issue.page_url}
                            target="_blank"
                            rel="noreferrer"
                            className="text-sm text-muted-foreground hover:underline"
                        >
                            {issue.page_url}
                        </a>
                    </div>

                    <div className="flex items-center gap-2 shrink-0">
                        <Badge variant={severityVariant[issue.severity] ?? 'outline'} className="capitalize">
                            {issue.severity}
                        </Badge>

                        <Select value={data.status} onValueChange={handleStatusChange} disabled={processing}>
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
                    </div>
                </div>

                {/* Meta */}
                <dl className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <StatCard label="Property" value={issue.property?.name ?? '—'} />
                    <StatCard label="Organization" value={issue.organization?.name ?? '—'} />
                    <StatCard label="Occurrences" value={String(issue.occurrence_count)} />
                    <StatCard label="Risk weight" value={String(issue.risk_weight)} />
                </dl>

                <dl className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                    <StatCard label="First detected" value={new Date(issue.first_detected_at).toLocaleDateString()} />
                    <StatCard label="Last detected" value={new Date(issue.last_detected_at).toLocaleDateString()} />
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
                                    <th className="px-4 py-3 text-left font-medium">Severity</th>
                                    <th className="px-4 py-3 text-left font-medium">Detected</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {issue.findings.length === 0 ? (
                                    <tr>
                                        <td colSpan={4} className="px-4 py-8 text-center text-sm text-muted-foreground">
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
