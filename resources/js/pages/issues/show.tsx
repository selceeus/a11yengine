import { useEffect, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import * as IssueController from '@/actions/App/Http/Controllers/IssueController';
import { AlertCircle, BookOpen, Bot, Clock, History, RefreshCw, Scale } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import IssueAssigner, { type AssignableUser } from '@/components/IssueAssigner';
import IssueActivityLog from '@/components/IssueActivityLog';
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

type AiSuggestions = {
    explanation: string;
    wcag_reference: string;
    wcag_level: string;
    user_impact: string;
    severity_rating: string;
    code_fix: string | null;
    aria_fix: string | null;
    remediation_steps: string[];
    testing_guidance: string;
    estimated_effort: 'low' | 'medium' | 'high';
    resources: { title: string; url: string }[];
    legal_precedents: {
        case_name: string;
        year: number | null;
        outcome: string;
        industry_relevance: string;
        summary: string;
    }[];
    legal_risk_rating: 'high' | 'medium' | 'low';
    wcag_grounding: string;
    similar_resolutions: {
        rule_key: string;
        approach: string;
        resolved_count: number;
    }[];
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
    due_date: string | null;
    assigned_user_id: number | null;
    assigned_user: { id: number; name: string; email: string } | null;
    property: Property | null;
    organization: Organization | null;
    findings: Finding[];
    activities: Activity[];
    ai_remediation_status: 'pending' | 'processing' | 'completed' | 'failed' | null;
    ai_suggestions: AiSuggestions | null;
};

type Activity = {
    id: number;
    type: 'comment' | 'status_change' | 'assignment' | 'due_date_change' | 'bulk_action';
    body: string | null;
    metadata: Record<string, string | number | null> | null;
    created_at: string;
    user: { id: number; name: string } | null;
};

type TeamMember = { id: number; name: string };

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

export default function Show({ issue, assignableUsers, teamMembers }: { issue: Issue; assignableUsers: AssignableUser[]; teamMembers: TeamMember[] }) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Issues', href: IssueController.index().url },
        { title: issue.rule_key, href: IssueController.show(issue.id).url },
    ];

    const [isEditingDueDate, setIsEditingDueDate] = useState(false);
    const [dueDateInput, setDueDateInput] = useState(issue.due_date ?? '');

    const isOverdue =
        issue.due_date !== null &&
        !['resolved', 'ignored', 'false_positive'].includes(issue.status) &&
        new Date(issue.due_date) < new Date();

    function handleStatusChange(status: string) {
        router.patch(IssueController.update(issue.id).url, { status });
    }

    function saveDueDate() {
        router.patch(IssueController.update(issue.id).url, { due_date: dueDateInput || null }, {
            preserveScroll: true,
            onSuccess: () => setIsEditingDueDate(false),
        });
    }

    const isProcessing = issue.ai_remediation_status === 'pending' || issue.ai_remediation_status === 'processing';

    useEffect(() => {
        if (!isProcessing) return;
        const timer = setInterval(() => router.reload({ only: ['issue'] }), 5000);
        return () => clearInterval(timer);
    }, [isProcessing]);

    function generateRemediation() {
        router.post(IssueController.generateRemediation(issue.id).url);
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
                                <SelectItem value="ignored">Ignored</SelectItem>
                                <SelectItem value="false_positive">False positive</SelectItem>
                            </SelectContent>
                        </Select>

                        <IssueAssigner issue={issue} users={assignableUsers} canAssign={true} />

                        <IssueActivityLog issue={issue} activities={issue.activities} teamMembers={teamMembers} />
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
                    <div className="rounded-lg border bg-card p-4">
                        <dt className="text-xs text-muted-foreground">Due date</dt>
                        <dd className="mt-1 font-medium">
                            {isEditingDueDate ? (
                                <div className="flex items-center gap-1">
                                    <input
                                        type="date"
                                        value={dueDateInput}
                                        onChange={(e) => setDueDateInput(e.target.value)}
                                        className="rounded border px-1.5 py-0.5 text-sm"
                                        autoFocus
                                    />
                                    <button onClick={saveDueDate} className="text-xs text-primary hover:underline">Save</button>
                                    <button onClick={() => setIsEditingDueDate(false)} className="text-xs text-muted-foreground hover:underline">Cancel</button>
                                </div>
                            ) : (
                                <button
                                    onClick={() => { setDueDateInput(issue.due_date ?? ''); setIsEditingDueDate(true); }}
                                    className="flex items-center gap-1.5 hover:underline"
                                >
                                    {issue.due_date ? (
                                        <>
                                            {isOverdue && <Clock className="h-3.5 w-3.5 text-destructive" />}
                                            <span className={isOverdue ? 'text-destructive' : ''}>
                                                {new Date(issue.due_date).toLocaleDateString()}
                                                {isOverdue && ' (Overdue)'}
                                            </span>
                                        </>
                                    ) : (
                                        <span className="text-muted-foreground">Set due date…</span>
                                    )}
                                </button>
                            )}
                        </dd>
                    </div>
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

                {/* AI Remediation Guide */}
                <div>
                    <h2 className="mb-3 font-medium">AI Remediation Guide</h2>

                    {/* Not yet generated */}
                    {!issue.ai_remediation_status && (
                        <div className="flex flex-col items-center gap-4 rounded-xl border border-dashed bg-muted/30 p-10 text-center">
                            <Bot className="h-8 w-8 text-muted-foreground" />
                            <div>
                                <p className="font-medium">No AI remediation guide yet</p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Generate an AI-powered fix explanation, code snippet, and step-by-step remediation guide for this issue.
                                </p>
                            </div>
                            <Button onClick={generateRemediation} size="sm">
                                <Bot className="mr-2 h-4 w-4" />
                                Generate AI Remediation
                            </Button>
                        </div>
                    )}

                    {/* Pending / Processing */}
                    {isProcessing && (
                        <div className="flex flex-col items-center gap-4 rounded-xl border bg-card py-12 text-center">
                            <Spinner className="h-7 w-7 text-primary" />
                            <p className="font-medium">Generating AI remediation guide…</p>
                            <p className="text-sm text-muted-foreground">This may take up to a minute.</p>
                        </div>
                    )}

                    {/* Failed */}
                    {issue.ai_remediation_status === 'failed' && (
                        <div className="flex items-start gap-4 rounded-xl border border-destructive/30 bg-destructive/5 p-6">
                            <AlertCircle className="mt-0.5 h-5 w-5 shrink-0 text-destructive" />
                            <div className="flex-1">
                                <p className="font-medium text-destructive">Generation failed</p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    The AI was unable to generate a remediation guide. Check your AI provider configuration and try again.
                                </p>
                            </div>
                            <Button variant="outline" size="sm" onClick={generateRemediation}>
                                <RefreshCw className="mr-2 h-3.5 w-3.5" />
                                Retry
                            </Button>
                        </div>
                    )}

                    {/* Completed */}
                    {issue.ai_remediation_status === 'completed' && issue.ai_suggestions && (
                        <div className="flex flex-col gap-4">
                            {/* Explanation + WCAG */}
                            <div className="rounded-xl border bg-card p-6">
                                <div className="mb-4 flex items-center gap-2">
                                    <Badge variant="outline" className="font-mono text-xs">
                                        WCAG {issue.ai_suggestions.wcag_reference}
                                    </Badge>
                                    <Badge
                                        variant={
                                            issue.ai_suggestions.wcag_level === 'A'
                                                ? 'secondary'
                                                : issue.ai_suggestions.wcag_level === 'AA'
                                                  ? 'default'
                                                  : 'outline'
                                        }
                                    >
                                        Level {issue.ai_suggestions.wcag_level}
                                    </Badge>
                                    {issue.ai_suggestions.legal_risk_rating && (
                                        <Badge
                                            variant={
                                                issue.ai_suggestions.legal_risk_rating === 'high'
                                                    ? 'destructive'
                                                    : issue.ai_suggestions.legal_risk_rating === 'medium'
                                                      ? 'default'
                                                      : 'secondary'
                                            }
                                            className="capitalize"
                                        >
                                            <Scale className="mr-1 h-3 w-3" />
                                            {issue.ai_suggestions.legal_risk_rating} legal risk
                                        </Badge>
                                    )}
                                </div>
                                <h3 className="mb-2 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                                    Why this is a problem
                                </h3>
                                <p className="text-sm leading-relaxed">{issue.ai_suggestions.explanation}</p>
                                {issue.ai_suggestions.wcag_grounding && (
                                    <blockquote className="mt-4 flex items-start gap-2 rounded-lg bg-muted/50 px-4 py-3">
                                        <BookOpen className="mt-0.5 h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                                        <p className="text-xs leading-relaxed text-muted-foreground italic">
                                            {issue.ai_suggestions.wcag_grounding}
                                        </p>
                                    </blockquote>
                                )}
                            </div>

                            {/* User Impact */}
                            <div className="rounded-xl border bg-card p-6">
                                <h3 className="mb-2 text-sm font-semibold uppercase tracking-wide text-muted-foreground">User Impact</h3>
                                <p className="text-sm leading-relaxed">{issue.ai_suggestions.user_impact}</p>
                                <div className="mt-3 flex items-center gap-2">
                                    <span className="text-xs text-muted-foreground">Effort to fix:</span>
                                    <Badge
                                        variant={
                                            issue.ai_suggestions.estimated_effort === 'low'
                                                ? 'secondary'
                                                : issue.ai_suggestions.estimated_effort === 'high'
                                                  ? 'destructive'
                                                  : 'default'
                                        }
                                        className="capitalize"
                                    >
                                        {issue.ai_suggestions.estimated_effort}
                                    </Badge>
                                </div>
                            </div>

                            {/* Code fix */}
                            {issue.ai_suggestions.code_fix && (
                                <div className="rounded-xl border bg-card p-6">
                                    <h3 className="mb-3 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                                        Suggested Code Fix
                                    </h3>
                                    <pre className="overflow-x-auto rounded-lg bg-muted px-4 py-3 text-xs leading-relaxed">
                                        <code>{issue.ai_suggestions.code_fix}</code>
                                    </pre>
                                </div>
                            )}

                            {/* ARIA fix */}
                            {issue.ai_suggestions.aria_fix && (
                                <div className="rounded-xl border bg-card p-6">
                                    <h3 className="mb-3 text-sm font-semibold uppercase tracking-wide text-muted-foreground">ARIA Fix</h3>
                                    <pre className="overflow-x-auto rounded-lg bg-muted px-4 py-3 text-xs leading-relaxed">
                                        <code>{issue.ai_suggestions.aria_fix}</code>
                                    </pre>
                                </div>
                            )}

                            {/* Remediation Steps */}
                            {issue.ai_suggestions.remediation_steps.length > 0 && (
                                <div className="rounded-xl border bg-card p-6">
                                    <h3 className="mb-3 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                                        Remediation Steps
                                    </h3>
                                    <ol className="list-decimal space-y-2 pl-5 text-sm leading-relaxed">
                                        {issue.ai_suggestions.remediation_steps.map((step, i) => (
                                            <li key={i}>{step}</li>
                                        ))}
                                    </ol>
                                </div>
                            )}

                            {/* Testing Guidance */}
                            <div className="rounded-xl border bg-card p-6">
                                <h3 className="mb-2 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                                    Testing Guidance
                                </h3>
                                <p className="text-sm leading-relaxed">{issue.ai_suggestions.testing_guidance}</p>
                            </div>

                            {/* Legal Precedents */}
                            {issue.ai_suggestions.legal_precedents.length > 0 && (
                                <div className="rounded-xl border bg-card p-6">
                                    <div className="mb-4 flex items-center gap-2">
                                        <Scale className="h-4 w-4 text-muted-foreground" />
                                        <h3 className="text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                                            Related ADA Precedents
                                        </h3>
                                    </div>
                                    <div className="space-y-4">
                                        {issue.ai_suggestions.legal_precedents.map((precedent, i) => (
                                            <div key={i} className="rounded-lg border bg-muted/30 p-4">
                                                <div className="mb-1 flex flex-wrap items-center gap-2">
                                                    <span className="text-sm font-medium">{precedent.case_name}</span>
                                                    {precedent.year && (
                                                        <span className="text-xs text-muted-foreground">({precedent.year})</span>
                                                    )}
                                                    <Badge
                                                        variant={
                                                            precedent.outcome === 'plaintiff_won'
                                                                ? 'destructive'
                                                                : precedent.outcome === 'defendant_won'
                                                                  ? 'secondary'
                                                                  : 'outline'
                                                        }
                                                        className="text-xs capitalize"
                                                    >
                                                        {precedent.outcome.replace('_', ' ')}
                                                    </Badge>
                                                </div>
                                                <p className="text-xs text-muted-foreground">{precedent.industry_relevance}</p>
                                                <p className="mt-1 text-xs leading-relaxed">{precedent.summary}</p>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {/* Similar Resolutions */}
                            {issue.ai_suggestions.similar_resolutions.length > 0 && (
                                <div className="rounded-xl border bg-card p-6">
                                    <div className="mb-3 flex items-center gap-2">
                                        <History className="h-4 w-4 text-muted-foreground" />
                                        <h3 className="text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                                            Similar Resolved Issues
                                        </h3>
                                    </div>
                                    <ul className="space-y-3">
                                        {issue.ai_suggestions.similar_resolutions.map((res, i) => (
                                            <li key={i} className="flex items-start gap-3">
                                                <code className="mt-0.5 shrink-0 rounded bg-muted px-1.5 py-0.5 font-mono text-xs">
                                                    {res.rule_key}
                                                </code>
                                                <div className="flex-1">
                                                    <p className="text-xs leading-relaxed">{res.approach}</p>
                                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                                        {res.resolved_count} issue{res.resolved_count !== 1 ? 's' : ''} resolved
                                                    </p>
                                                </div>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}

                            {/* Resources */}
                            {issue.ai_suggestions.resources.length > 0 && (
                                <div className="rounded-xl border bg-card p-6">
                                    <h3 className="mb-3 text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                                        Further Reading
                                    </h3>
                                    <ul className="space-y-2">
                                        {issue.ai_suggestions.resources.map((r, i) => (
                                            <li key={i}>
                                                <a
                                                    href={r.url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="text-sm text-primary hover:underline"
                                                >
                                                    {r.title} ↗
                                                </a>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}

                            {/* Regenerate */}
                            <div className="flex justify-end">
                                <Button variant="outline" size="sm" onClick={generateRemediation}>
                                    <RefreshCw className="mr-2 h-3.5 w-3.5" />
                                    Regenerate
                                </Button>
                            </div>
                        </div>
                    )}
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
