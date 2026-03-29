import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import * as IssueController from '@/actions/App/Http/Controllers/IssueController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
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
    wcag_criteria: string | null;
    last_detected_at: string;
    due_date: string | null;
    property: Property | null;
    organization: Organization | null;
    assigned_user: { id: number; name: string } | null;
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
    wcag_category?: string;
    date_from?: string;
    date_to?: string;
    assigned_user_id?: string;
    search?: string;
};

// in the component props:
export default function Index({
    issues,
    filters,
    properties,
    teamMembers,
}: {
    issues: PaginatedIssues;
    filters: Filters;
    properties: Property[];
    teamMembers: { id: number; name: string }[];
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

type UserIssue = {
    id: number;
    rule_key: string;
    severity: string;
    status: string;
    occurrence_count: number;
    last_detected_at: string;
    property: { id: number; name: string } | null;
};

type ModalState =
    | { open: false }
    | { open: true; userName: string; issues: UserIssue[]; loading: false }
    | { open: true; userName: string; issues: []; loading: true };

const [modal, setModal] = useState<ModalState>({ open: false });
const [selectedIds, setSelectedIds] = useState<number[]>([]);
const [bulkAction, setBulkAction] = useState<string>('');
const [bulkStatus, setBulkStatus] = useState<string>('');
const [bulkUserId, setBulkUserId] = useState<string>('');
const [bulkDueDate, setBulkDueDate] = useState<string>('');
const [bulkLoading, setBulkLoading] = useState(false);

const allPageIds = issues.data.map((i) => i.id);
const allSelected = allPageIds.length > 0 && allPageIds.every((id) => selectedIds.includes(id));
const someSelected = selectedIds.length > 0;

function toggleAll() {
    setSelectedIds(allSelected ? [] : allPageIds);
}

function toggleOne(id: number) {
    setSelectedIds((prev) => prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]);
}

async function executeBulkAction() {
    if (!selectedIds.length) return;
    const body: Record<string, unknown> = { ids: selectedIds, action: bulkAction };
    if (bulkAction === 'status_change') body.status = bulkStatus;
    if (bulkAction === 'assign') body.user_id = (bulkUserId && bulkUserId !== '__unassigned') ? Number(bulkUserId) : null;
    if (bulkAction === 'set_due_date') body.due_date = bulkDueDate || null;
    setBulkLoading(true);
    try {
        const response = await fetch('/api/issues/bulk', {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': getCsrfToken() },
            body: JSON.stringify(body),
        });
        if (!response.ok) {
            console.error('Bulk action failed', response.status, await response.text());
            return;
        }
        setSelectedIds([]);
        setBulkAction('');
        setBulkStatus('');
        setBulkUserId('');
        router.reload();
    } finally {
        setBulkLoading(false);
    }
}

function getCsrfToken(): string {
    return decodeURIComponent(document.cookie.split('; ').find((r) => r.startsWith('XSRF-TOKEN='))?.split('=')[1] ?? '');
}

async function openUserModal(userId: number, userName: string) {
    setModal({ open: true, userName, issues: [], loading: true });

    const response = await fetch(`/api/users/${userId}/issues`, {
        credentials: 'include',
        headers: { Accept: 'application/json' },
    });

    if (!response.ok) {
        setModal({ open: false });
        return;
    }

    const data = (await response.json()) as { issues: UserIssue[] };
    setModal({ open: true, userName, issues: data.issues, loading: false });
}

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
                    {someSelected && (
                        <span className="text-sm text-muted-foreground">{selectedIds.length} selected</span>
                    )}
                </div>

                {/* Property tabs */}
                <Tabs
                    value={filters.property_id ?? 'all'}
                    onValueChange={(v) => filter({ property_id: v === 'all' ? '' : v }, filters)}
                >
                    <TabsList>
                        <TabsTrigger value="all">All properties</TabsTrigger>
                        {properties.map((p) => (
                            <TabsTrigger key={p.id} value={String(p.id)}>
                                {p.name}
                            </TabsTrigger>
                        ))}
                    </TabsList>
                </Tabs>

                {/* Filters */}
                <div className="flex flex-wrap gap-3">
                    <Input
                        type="search"
                        className="w-56"
                        placeholder="Search issues…"
                        value={filters.search ?? ''}
                        onChange={(e) => filter({ search: e.target.value }, filters)}
                        aria-label="Search issues"
                    />
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

                    <Select
                        value={filters.wcag_category ?? 'all'}
                        onValueChange={(v) => filter({ wcag_category: v === 'all' ? '' : v }, filters)}
                    >
                        <SelectTrigger className="w-48">
                            <SelectValue placeholder="All WCAG principles" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All WCAG principles</SelectItem>
                            <SelectItem value="perceivable">Perceivable</SelectItem>
                            <SelectItem value="operable">Operable</SelectItem>
                            <SelectItem value="understandable">Understandable</SelectItem>
                            <SelectItem value="robust">Robust</SelectItem>
                            <SelectItem value="best-practice">Best practice</SelectItem>
                        </SelectContent>
                    </Select>

                    <Select
                        value={filters.assigned_user_id ?? 'all'}
                        onValueChange={(v) => filter({ assigned_user_id: v === 'all' ? '' : v }, filters)}
                    >
                        <SelectTrigger className="w-44">
                            <SelectValue placeholder="All assignees" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All assignees</SelectItem>
                            {teamMembers.map((m) => (
                                <SelectItem key={m.id} value={String(m.id)}>{m.name}</SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Input
                        type="date"
                        className="w-40"
                        value={filters.date_from ?? ''}
                        onChange={(e) => filter({ date_from: e.target.value }, filters)}
                        aria-label="Detected from"
                    />
                    <Input
                        type="date"
                        className="w-40"
                        value={filters.date_to ?? ''}
                        onChange={(e) => filter({ date_to: e.target.value }, filters)}
                        aria-label="Detected to"
                    />
                </div>

                {/* Table */}
                <div className="rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-muted/50">
                            <tr className="text-xs text-muted-foreground">
                                <th className="px-4 py-3 text-center font-medium">
                                    <Checkbox checked={allSelected} onCheckedChange={toggleAll} aria-label="Select all" />
                                </th>
                                <th className="px-4 py-3 text-center font-medium">Severity</th>
                                <th className="px-4 py-3 text-center font-medium">Rule</th>
                                <th className="px-4 py-3 text-center font-medium">WCAG</th>
                                <th className="px-4 py-3 text-center font-medium">Occurrences</th>
                                <th className="px-4 py-3 text-center font-medium">Risk weight</th>
                                <th className="px-4 py-3 text-center font-medium">Last detected</th>
                                <th className="px-4 py-3 text-center font-medium">Due</th>
                                {!filters.property_id && <th className="px-4 py-3 text-center font-medium">Property</th>}
                                <th className="px-4 py-3 text-center font-medium">Status</th>
                                <th className="px-4 py-3 text-center font-medium">Assigned to</th>
                                <th className="px-4 py-3"><span className="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {issues.data.length === 0 ? (
                                <tr>
                                    <td colSpan={filters.property_id ? 11 : 12} className="px-4 py-10 text-center text-sm text-muted-foreground">
                                        No issues found.
                                    </td>
                                </tr>
                            ) : (
                                issues.data.map((issue) => {
                                    const isOverdue =
                                        issue.due_date !== null &&
                                        !['resolved', 'ignored', 'false_positive'].includes(issue.status) &&
                                        new Date(issue.due_date) < new Date();
                                    return (
                                        <tr key={issue.id} className={`transition-colors hover:bg-muted/30 ${selectedIds.includes(issue.id) ? 'bg-muted/20' : ''}`}>
                                            <td className="px-4 py-3 text-center">
                                                <Checkbox
                                                    checked={selectedIds.includes(issue.id)}
                                                    onCheckedChange={() => toggleOne(issue.id)}
                                                    aria-label={`Select issue ${issue.rule_key}`}
                                                />
                                            </td>
                                            <td className="px-4 py-3 text-center">
                                                <Badge variant={severityVariant[issue.severity] ?? 'outline'} className="capitalize">
                                                    {issue.severity}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-3 text-center font-mono text-xs">{issue.rule_key}</td>
                                            <td className="px-4 py-3 text-center text-muted-foreground capitalize">
                                                {issue.wcag_criteria
                                                    ? <span title={issue.wcag_category?.replace('-', ' ') ?? undefined}>{issue.wcag_criteria}</span>
                                                    : (issue.wcag_category?.replace('-', ' ') ?? '—')}
                                            </td>
                                            <td className="px-4 py-3 text-center text-muted-foreground">{issue.occurrence_count}</td>
                                            <td className="px-4 py-3 text-center tabular-nums text-muted-foreground">{issue.risk_weight ?? '—'}</td>
                                            <td className="px-4 py-3 text-center text-muted-foreground">
                                                {new Date(issue.last_detected_at).toLocaleDateString()}
                                            </td>
                                            <td className="px-4 py-3 text-center text-muted-foreground">
                                                {issue.due_date ? (
                                                    <span className={isOverdue ? 'text-destructive font-medium' : ''}>
                                                        {isOverdue && '⚠ '}{new Date(issue.due_date).toLocaleDateString()}
                                                    </span>
                                                ) : '—'}
                                            </td>
                                            {!filters.property_id && <td className="px-4 py-3 text-center text-muted-foreground">{issue.property?.name ?? '—'}</td>}
                                            <td className="px-4 py-3 text-center text-muted-foreground">
                                                {statusLabels[issue.status] ?? issue.status}
                                            </td>
                                            <td className="px-4 py-3 text-center text-muted-foreground">
                                                {issue.assigned_user ? (
                                                    <button
                                                        onClick={() => openUserModal(issue.assigned_user!.id, issue.assigned_user!.name)}
                                                        className="text-primary hover:underline cursor-pointer"
                                                    >
                                                        {issue.assigned_user.name}
                                                    </button>
                                                ) : '—'}
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
                                    );
                                })
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

            {/* Bulk action toolbar */}
            {someSelected && (
                <div className="fixed bottom-6 left-1/2 z-50 flex -translate-x-1/2 items-center gap-3 rounded-xl border bg-background px-5 py-3 shadow-xl">
                    <span className="text-sm font-medium">{selectedIds.length} selected</span>
                    <div className="h-4 w-px bg-border" />

                    {/* Action selector */}
                    <Select value={bulkAction} onValueChange={setBulkAction}>
                        <SelectTrigger className="w-40">
                            <SelectValue placeholder="Choose action…" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="status_change">Change status</SelectItem>
                            <SelectItem value="assign">Assign to</SelectItem>
                            <SelectItem value="ignore">Ignore</SelectItem>
                            <SelectItem value="set_due_date">Set due date</SelectItem>
                            <SelectItem value="delete">Archive</SelectItem>
                        </SelectContent>
                    </Select>

                    {bulkAction === 'status_change' && (
                        <Select value={bulkStatus} onValueChange={setBulkStatus}>
                            <SelectTrigger className="w-36">
                                <SelectValue placeholder="New status…" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="open">Open</SelectItem>
                                <SelectItem value="in_progress">In progress</SelectItem>
                                <SelectItem value="resolved">Resolved</SelectItem>
                                <SelectItem value="ignored">Ignored</SelectItem>
                                <SelectItem value="false_positive">False positive</SelectItem>
                            </SelectContent>
                        </Select>
                    )}

                    {bulkAction === 'assign' && (
                        <Select value={bulkUserId} onValueChange={setBulkUserId}>
                            <SelectTrigger className="w-40">
                                <SelectValue placeholder="Assign to…" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="__unassigned">Unassigned</SelectItem>
                                {teamMembers.map((m) => (
                                    <SelectItem key={m.id} value={String(m.id)}>{m.name}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    )}

                    {bulkAction === 'set_due_date' && (
                        <Input
                            type="date"
                            className="w-40"
                            value={bulkDueDate}
                            onChange={(e) => setBulkDueDate(e.target.value)}
                        />
                    )}

                    <Button
                        size="sm"
                        disabled={
                            bulkLoading ||
                            !bulkAction ||
                            (bulkAction === 'status_change' && !bulkStatus)
                        }
                        onClick={executeBulkAction}
                        variant={bulkAction === 'delete' ? 'destructive' : 'default'}
                    >
                        {bulkLoading ? <Spinner className="h-4 w-4" /> : 'Apply'}
                    </Button>

                    <Button variant="ghost" size="sm" onClick={() => setSelectedIds([])}>
                        Cancel
                    </Button>
                </div>
            )}

            <Dialog open={modal.open} onOpenChange={(open) => { if (!open) setModal({ open: false }); }}>
                <DialogContent className="max-w-5xl">
                    <DialogHeader>
                        <DialogTitle>
                            Issues assigned to {modal.open ? modal.userName : ''}
                        </DialogTitle>
                    </DialogHeader>

                    {modal.open && modal.loading && (
                        <div className="flex items-center justify-center py-10">
                            <Spinner className="h-6 w-6" />
                        </div>
                    )}

                    {modal.open && !modal.loading && (
                        modal.issues.length === 0 ? (
                            <p className="py-8 text-center text-sm text-muted-foreground">No issues assigned.</p>
                        ) : (
                            <div className="rounded-lg border overflow-auto max-h-[60vh]">
                                <table className="w-full text-sm">
                                    <thead className="border-b bg-muted/50 sticky top-0">
                                        <tr className="text-xs text-muted-foreground">
                                            <th className="px-4 py-3 text-left font-medium">Severity</th>
                                            <th className="px-4 py-3 text-left font-medium">Rule</th>
                                            <th className="px-4 py-3 text-left font-medium">Occurrences</th>
                                            <th className="px-4 py-3 text-left font-medium">Last detected</th>
                                            <th className="px-4 py-3 text-left font-medium">Status</th>
                                            <th className="px-4 py-3 text-left font-medium">Property</th>
                                            <th className="px-4 py-3"><span className="sr-only">Actions</span></th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {modal.issues.map((issue) => (
                                            <tr key={issue.id} className="hover:bg-muted/30">
                                                <td className="px-4 py-3">
                                                    <Badge variant={severityVariant[issue.severity] ?? 'outline'} className="capitalize">
                                                        {issue.severity}
                                                    </Badge>
                                                </td>
                                                <td className="px-4 py-3 font-mono text-xs">{issue.rule_key}</td>

                                                <td className="px-4 py-3 text-muted-foreground">{issue.occurrence_count}</td>
                                                <td className="px-4 py-3 text-muted-foreground">
                                                    {new Date(issue.last_detected_at).toLocaleDateString()}
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground">{statusLabels[issue.status] ?? issue.status}</td>
                                                <td className="px-4 py-3 text-muted-foreground">{issue.property?.name ?? '—'}</td>
                                                <td className="px-4 py-3 text-right">
                                                    <Link
                                                        href={IssueController.show(issue.id).url}
                                                        className="text-sm text-primary hover:underline"
                                                    >
                                                        View
                                                    </Link>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )
                    )}
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}

