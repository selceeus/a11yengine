import { Head, Link, router, usePage } from '@inertiajs/react';
import { AlignJustify, ChevronDown, ChevronUp, ChevronsUpDown, Filter, List, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import * as IssueController from '@/actions/App/Http/Controllers/IssueController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

type Property = { id: number; name: string };

type Issue = {
    id: number;
    rule_key: string;
    severity: string;
    status: string;
    occurrence_count: number;
    wcag_category: string | null;
    wcag_criteria: string | null;
    last_detected_at: string;
    due_date: string | null;
    property: Property | null;
    organization: { id: number; name: string } | null;
    assigned_user: { id: number; name: string } | null;
};

type PaginatedIssues = {
    data: Issue[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
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
    sort?: string;
    direction?: string;
    per_page?: number;
};

type Summary = {
    total: number;
    critical: number;
    overdue: number;
    unassigned: number;
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

const severityVariant: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    critical: 'destructive',
    high: 'destructive',
    medium: 'default',
    low: 'secondary',
};

const statusVariant: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    open: 'outline',
    in_progress: 'default',
    resolved: 'secondary',
    ignored: 'secondary',
    false_positive: 'secondary',
    accepted_risk: 'secondary',
};

const statusLabels: Record<string, string> = {
    open: 'Open',
    in_progress: 'In progress',
    resolved: 'Resolved',
    ignored: 'Ignored',
    false_positive: 'False positive',
    accepted_risk: 'Accepted risk',
};

function SortableHeader({
    children,
    column,
    currentSort,
    currentDirection,
    onSort,
    className,
}: {
    children: React.ReactNode;
    column: string;
    currentSort: string;
    currentDirection: string;
    onSort: (col: string, dir: string) => void;
    className?: string;
}) {
    const isActive = currentSort === column;
    const nextDirection = isActive && currentDirection === 'desc' ? 'asc' : 'desc';
    const Icon = isActive ? (currentDirection === 'desc' ? ChevronDown : ChevronUp) : ChevronsUpDown;
    const label = typeof children === 'string' ? children : column;
    const ariaLabel = isActive
        ? `Sort by ${label}, currently ${currentDirection === 'asc' ? 'ascending' : 'descending'}, click to sort ${nextDirection === 'asc' ? 'ascending' : 'descending'}`
        : `Sort by ${label}`;

    return (
        <th className={cn('px-4 py-3 font-medium', className)}>
            <button
                className="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                onClick={() => onSort(column, nextDirection)}
                aria-label={ariaLabel}
            >
                {children}
                <Icon className="h-3 w-3" aria-hidden="true" />
            </button>
        </th>
    );
}

export default function Index({
    issues,
    summary,
    filters,
    properties,
    teamMembers,
}: {
    issues: PaginatedIssues;
    summary: Summary;
    filters: Filters;
    properties: Property[];
    teamMembers: { id: number; name: string }[];
}) {
    const breadcrumbs: BreadcrumbItem[] = [{ title: 'Issues', href: IssueController.index().url }];

    const [modal, setModal] = useState<ModalState>({ open: false });
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [bulkAction, setBulkAction] = useState<string>('');
    const [bulkStatus, setBulkStatus] = useState<string>('');
    const [bulkUserId, setBulkUserId] = useState<string>('');
    const [bulkDueDate, setBulkDueDate] = useState<string>('');
    const [bulkLoading, setBulkLoading] = useState(false);
    const [moreFiltersOpen, setMoreFiltersOpen] = useState(
        !!(filters.wcag_category || filters.assigned_user_id || filters.date_from || filters.date_to),
    );
    const [density, setDensity] = useState<'comfortable' | 'compact'>('comfortable');

    const allPageIds = issues.data.map((i) => i.id);
    const allSelected = allPageIds.length > 0 && allPageIds.every((id) => selectedIds.includes(id));
    const someSelected = selectedIds.length > 0;

    const activeExtraFilterCount = [
        filters.wcag_category,
        filters.assigned_user_id,
        filters.date_from,
        filters.date_to,
    ].filter(Boolean).length;

    const currentSort = filters.sort ?? 'severity';
    const currentDirection = filters.direction ?? 'desc';

    function toggleAll() {
        setSelectedIds(allSelected ? [] : allPageIds);
    }

    function toggleOne(id: number) {
        setSelectedIds((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]));
    }

    function filter(patch: Partial<Filters>, current: Filters) {
        const next = { ...current, ...patch } as Record<string, unknown>;
        Object.keys(next).forEach((k) => !next[k] && delete next[k]);
        router.get(IssueController.index().url, next as Record<string, string>, {
            preserveState: true,
            replace: true,
        });
    }

    function handleSort(col: string, dir: string) {
        filter({ sort: col, direction: dir }, filters);
    }

    async function executeBulkAction() {
        if (!selectedIds.length) return;
        const body: Record<string, unknown> = { ids: selectedIds, action: bulkAction };
        if (bulkAction === 'status_change') body.status = bulkStatus;
        if (bulkAction === 'assign') body.user_id = bulkUserId && bulkUserId !== '__unassigned' ? Number(bulkUserId) : null;
        if (bulkAction === 'set_due_date') body.due_date = bulkDueDate || null;
        setBulkLoading(true);
        try {
            const response = await fetch('/api/issues/bulk', {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
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
        return decodeURIComponent(
            document.cookie
                .split('; ')
                .find((r) => r.startsWith('XSRF-TOKEN='))
                ?.split('=')[1] ?? '',
        );
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

    const columnCount = filters.property_id ? 10 : 11;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Issues" />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">Issues</h1>
                    <div className="flex items-center gap-3">
                        {someSelected && (
                            <span className="text-sm text-muted-foreground">{selectedIds.length} selected</span>
                        )}
                        <ToggleGroup
                            type="single"
                            value={density}
                            onValueChange={(v) => v && setDensity(v as 'comfortable' | 'compact')}
                            aria-label="Table density"
                        >
                            <ToggleGroupItem value="comfortable" aria-label="Comfortable density">
                                <AlignJustify className="h-4 w-4" />
                            </ToggleGroupItem>
                            <ToggleGroupItem value="compact" aria-label="Compact density">
                                <List className="h-4 w-4" />
                            </ToggleGroupItem>
                        </ToggleGroup>
                    </div>
                </div>

                {/* Summary stats */}
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <button
                        onClick={() => filter({ property_id: filters.property_id, severity: undefined, status: undefined }, {})}
                        className="rounded border bg-card p-4 text-left transition-colors hover:bg-muted/50"
                    >
                        <p className="text-2xl font-semibold tabular-nums">{summary.total}</p>
                        <p className="text-sm text-muted-foreground">Total issues</p>
                    </button>

                    <button
                        onClick={() => filter({ ...filters, severity: 'critical' }, {})}
                        className="rounded border bg-card p-4 text-left transition-colors hover:bg-muted/50"
                    >
                        <p className="text-2xl font-semibold tabular-nums text-destructive">{summary.critical}</p>
                        <p className="text-sm text-muted-foreground">Critical</p>
                    </button>

                    <div className="rounded border bg-card p-4">
                        <p className="text-2xl font-semibold tabular-nums">{summary.overdue}</p>
                        <p className="text-sm text-muted-foreground">Overdue</p>
                    </div>

                    <div className="rounded border bg-card p-4">
                        <p className="text-2xl font-semibold tabular-nums">{summary.unassigned}</p>
                        <p className="text-sm text-muted-foreground">Unassigned</p>
                    </div>
                </div>

                {/* Primary filters */}
                <div className="flex flex-wrap items-center gap-3">
                    {/* Property picker */}
                    <Select
                        value={filters.property_id ?? 'all'}
                        onValueChange={(v) => filter({ ...filters, property_id: v === 'all' ? '' : v }, {})}
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
                        onValueChange={(v) => filter({ ...filters, status: v === 'all' ? '' : v }, {})}
                    >
                        <SelectTrigger className="w-40">
                            <SelectValue placeholder="All statuses" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All statuses</SelectItem>
                            <SelectItem value="open">Open</SelectItem>
                            <SelectItem value="in_progress">In progress</SelectItem>
                            <SelectItem value="resolved">Resolved</SelectItem>
                            <SelectItem value="ignored">Ignored</SelectItem>
                            <SelectItem value="false_positive">False positive</SelectItem>
                            <SelectItem value="accepted_risk">Accepted risk</SelectItem>
                        </SelectContent>
                    </Select>

                    <Select
                        value={filters.severity ?? 'all'}
                        onValueChange={(v) => filter({ ...filters, severity: v === 'all' ? '' : v }, {})}
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

                    <Collapsible open={moreFiltersOpen} onOpenChange={setMoreFiltersOpen}>
                        <CollapsibleTrigger asChild>
                            <Button variant="outline" size="sm" className="gap-2">
                                <Filter className="h-3.5 w-3.5" />
                                More filters
                                {activeExtraFilterCount > 0 && (
                                    <Badge className="h-4 min-w-4 px-1 text-[10px]">{activeExtraFilterCount}</Badge>
                                )}
                            </Button>
                        </CollapsibleTrigger>
                        <CollapsibleContent className="w-full">
                            <div className="mt-3 flex flex-wrap gap-3">
                                <Select
                                    value={filters.wcag_category ?? 'all'}
                                    onValueChange={(v) =>
                                        filter({ ...filters, wcag_category: v === 'all' ? '' : v }, {})
                                    }
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
                                    onValueChange={(v) =>
                                        filter({ ...filters, assigned_user_id: v === 'all' ? '' : v }, {})
                                    }
                                >
                                    <SelectTrigger className="w-44">
                                        <SelectValue placeholder="All assignees" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All assignees</SelectItem>
                                        {teamMembers.map((m) => (
                                            <SelectItem key={m.id} value={String(m.id)}>
                                                {m.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>

                                <div className="flex items-center gap-2">
                                    <label className="text-sm text-muted-foreground">From</label>
                                    <Input
                                        type="date"
                                        className="w-40"
                                        value={filters.date_from ?? ''}
                                        onChange={(e) => filter({ ...filters, date_from: e.target.value }, {})}
                                        aria-label="Detected from"
                                    />
                                </div>

                                <div className="flex items-center gap-2">
                                    <label className="text-sm text-muted-foreground">To</label>
                                    <Input
                                        type="date"
                                        className="w-40"
                                        value={filters.date_to ?? ''}
                                        onChange={(e) => filter({ ...filters, date_to: e.target.value }, {})}
                                        aria-label="Detected to"
                                    />
                                </div>
                            </div>
                        </CollapsibleContent>
                    </Collapsible>
                </div>

                {/* Table */}
                <div className="rounded border">
                    <table className="w-full text-sm" data-density={density}>
                        <thead className="border-b bg-muted/50">
                            <tr>
                                <th className="px-4 py-3 text-center font-medium">
                                    <Checkbox
                                        checked={allSelected}
                                        onCheckedChange={toggleAll}
                                        aria-label="Select all"
                                    />
                                </th>
                                <SortableHeader
                                    column="severity"
                                    currentSort={currentSort}
                                    currentDirection={currentDirection}
                                    onSort={handleSort}
                                    className="text-center"
                                >
                                    Severity
                                </SortableHeader>
                                <th className="px-4 py-3 text-left text-xs font-medium text-muted-foreground">
                                    Rule
                                </th>
                                <th className="px-4 py-3 text-center text-xs font-medium text-muted-foreground">
                                    Status
                                </th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-muted-foreground">
                                    WCAG
                                </th>
                                <SortableHeader
                                    column="occurrence_count"
                                    currentSort={currentSort}
                                    currentDirection={currentDirection}
                                    onSort={handleSort}
                                    className="text-right"
                                >
                                    Occurrences
                                </SortableHeader>
                                <SortableHeader
                                    column="last_detected_at"
                                    currentSort={currentSort}
                                    currentDirection={currentDirection}
                                    onSort={handleSort}
                                    className="text-center"
                                >
                                    Last detected
                                </SortableHeader>
                                <SortableHeader
                                    column="due_date"
                                    currentSort={currentSort}
                                    currentDirection={currentDirection}
                                    onSort={handleSort}
                                    className="text-center"
                                >
                                    Due
                                </SortableHeader>
                                {!filters.property_id && (
                                    <th className="px-4 py-3 text-left text-xs font-medium text-muted-foreground">
                                        Property
                                    </th>
                                )}
                                <th className="px-4 py-3 text-center text-xs font-medium text-muted-foreground">
                                    Assigned to
                                </th>
                                <th className="px-4 py-3">
                                    <span className="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {issues.data.length === 0 ? (
                                <tr>
                                    <td colSpan={columnCount} className="px-4 py-16 text-center">
                                        <div className="flex flex-col items-center gap-3 text-muted-foreground">
                                            <ShieldCheck className="h-10 w-10 opacity-30" />
                                            {Object.values(filters).some(
                                                (v, i) =>
                                                    !['sort', 'direction', 'per_page'].includes(
                                                        Object.keys(filters)[i],
                                                    ) && Boolean(v),
                                            ) ? (
                                                <>
                                                    <p className="font-medium">No issues match your filters</p>
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() =>
                                                            filter(
                                                                {
                                                                    sort: currentSort,
                                                                    direction: currentDirection,
                                                                    per_page: filters.per_page,
                                                                },
                                                                {},
                                                            )
                                                        }
                                                    >
                                                        Clear filters
                                                    </Button>
                                                </>
                                            ) : (
                                                <p className="font-medium">No issues have been detected yet</p>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ) : (
                                issues.data.map((issue) => {
                                    const isOverdue =
                                        issue.due_date !== null &&
                                        !['resolved', 'ignored', 'false_positive'].includes(issue.status) &&
                                        new Date(issue.due_date) < new Date();

                                    return (
                                        <tr
                                            key={issue.id}
                                            className={cn(
                                                'transition-colors hover:bg-muted/30',
                                                selectedIds.includes(issue.id) && 'bg-muted/20',
                                            )}
                                        >
                                            <td className="px-4 py-3 text-center">
                                                <Checkbox
                                                    checked={selectedIds.includes(issue.id)}
                                                    onCheckedChange={() => toggleOne(issue.id)}
                                                    aria-label={`Select issue ${issue.rule_key}`}
                                                />
                                            </td>
                                            <td className="px-4 py-3 text-center">
                                                <Badge
                                                    variant={severityVariant[issue.severity] ?? 'outline'}
                                                    className="capitalize"
                                                >
                                                    {issue.severity}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-3 font-mono text-xs">{issue.rule_key}</td>
                                            <td className="px-4 py-3 text-center">
                                                <Badge
                                                    variant={statusVariant[issue.status] ?? 'outline'}
                                                    className="capitalize"
                                                >
                                                    {statusLabels[issue.status] ?? issue.status}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground capitalize">
                                                {issue.wcag_criteria ? (
                                                    <span
                                                        title={issue.wcag_category?.replace('-', ' ') ?? undefined}
                                                    >
                                                        {issue.wcag_criteria}
                                                    </span>
                                                ) : (
                                                    (issue.wcag_category?.replace('-', ' ') ?? '—')
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-right tabular-nums text-muted-foreground">
                                                {issue.occurrence_count}
                                            </td>
                                            <td className="px-4 py-3 text-center text-muted-foreground">
                                                {new Date(issue.last_detected_at).toLocaleDateString()}
                                            </td>
                                            <td className="px-4 py-3 text-center text-muted-foreground">
                                                {issue.due_date ? (
                                                    <span
                                                        className={cn(
                                                            isOverdue && 'font-medium text-destructive',
                                                        )}
                                                    >
                                                        {isOverdue && '⚠ '}
                                                        {new Date(issue.due_date).toLocaleDateString()}
                                                    </span>
                                                ) : (
                                                    '—'
                                                )}
                                            </td>
                                            {!filters.property_id && (
                                                <td className="px-4 py-3 text-muted-foreground">
                                                    {issue.property?.name ?? '—'}
                                                </td>
                                            )}
                                            <td className="px-4 py-3 text-center text-muted-foreground">
                                                {issue.assigned_user ? (
                                                    <button
                                                        onClick={() =>
                                                            openUserModal(
                                                                issue.assigned_user!.id,
                                                                issue.assigned_user!.name,
                                                            )
                                                        }
                                                        className="cursor-pointer text-primary hover:underline"
                                                    >
                                                        {issue.assigned_user.name}
                                                    </button>
                                                ) : (
                                                    '—'
                                                )}
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
                {(issues.prev_page_url || issues.next_page_url || issues.total > 0) && (
                    <div className="flex flex-wrap items-center justify-between gap-3 text-sm text-muted-foreground">
                        <div className="flex items-center gap-3">
                            <span>
                                {issues.from !== null && issues.to !== null
                                    ? `Showing ${issues.from}–${issues.to} of ${issues.total} issues`
                                    : `${issues.total} issues`}
                            </span>
                            <Select
                                value={String(filters.per_page ?? 25)}
                                onValueChange={(v) => filter({ ...filters, per_page: Number(v) }, {})}
                            >
                                <SelectTrigger className="h-8 w-24">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="25">25 / page</SelectItem>
                                    <SelectItem value="50">50 / page</SelectItem>
                                    <SelectItem value="100">100 / page</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="flex gap-2">
                            {issues.prev_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={issues.prev_page_url}>Previous</Link>
                                </Button>
                            )}
                            {issues.next_page_url && (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={issues.next_page_url}>Next</Link>
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </div>

            {/* Bulk action toolbar */}
            {someSelected && (
                <div className="fixed bottom-6 left-1/2 z-50 flex -translate-x-1/2 items-center gap-3 rounded border bg-background px-5 py-3 shadow-xl">
                    <span className="text-sm font-medium">{selectedIds.length} selected</span>
                    <div className="h-4 w-px bg-border" />

                    <Select value={bulkAction} onValueChange={setBulkAction}>
                        <SelectTrigger className="w-40">
                            <SelectValue placeholder="Choose action…" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="status_change">Change status</SelectItem>
                            <SelectItem value="assign">Assign to</SelectItem>
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
                                    <SelectItem key={m.id} value={String(m.id)}>
                                        {m.name}
                                    </SelectItem>
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

                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => {
                            setSelectedIds([]);
                            setBulkAction('');
                        }}
                    >
                        Cancel
                    </Button>
                </div>
            )}

            {/* Assigned user issues modal */}
            <Dialog
                open={modal.open}
                onOpenChange={(open) => {
                    if (!open) setModal({ open: false });
                }}
            >
                <DialogContent className="max-w-5xl">
                    <DialogHeader>
                        <DialogTitle>Issues assigned to {modal.open ? modal.userName : ''}</DialogTitle>
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
                            <div className="max-h-[60vh] overflow-auto rounded border">
                                <table className="w-full text-sm">
                                    <thead className="border-b bg-muted/50">
                                        <tr className="text-xs text-muted-foreground">
                                            <th className="px-4 py-3 text-center font-medium">Severity</th>
                                            <th className="px-4 py-3 text-left font-medium">Rule</th>
                                            <th className="px-4 py-3 text-center font-medium">Status</th>
                                            <th className="px-4 py-3 text-right font-medium">Occurrences</th>
                                            <th className="px-4 py-3 text-center font-medium">Last detected</th>
                                            <th className="px-4 py-3 text-left font-medium">Property</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {modal.issues.map((i) => (
                                            <tr key={i.id} className="hover:bg-muted/30">
                                                <td className="px-4 py-3 text-center">
                                                    <Badge
                                                        variant={severityVariant[i.severity] ?? 'outline'}
                                                        className="capitalize"
                                                    >
                                                        {i.severity}
                                                    </Badge>
                                                </td>
                                                <td className="px-4 py-3 font-mono text-xs">{i.rule_key}</td>
                                                <td className="px-4 py-3 text-center">
                                                    <Badge
                                                        variant={statusVariant[i.status] ?? 'outline'}
                                                        className="capitalize"
                                                    >
                                                        {statusLabels[i.status] ?? i.status}
                                                    </Badge>
                                                </td>
                                                <td className="px-4 py-3 text-right tabular-nums text-muted-foreground">
                                                    {i.occurrence_count}
                                                </td>
                                                <td className="px-4 py-3 text-center text-muted-foreground">
                                                    {new Date(i.last_detected_at).toLocaleDateString()}
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground">
                                                    {i.property?.name ?? '—'}
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
