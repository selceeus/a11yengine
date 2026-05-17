import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Settings', href: '/settings/profile' },
    { title: 'Activity Log', href: '/settings/activity-log' },
];

type LogEntry = {
    id: number;
    created_at: string;
    event: string;
    event_label: string;
    event_category: string;
    actor_type: string | null;
    actor_label: string | null;
    subject_type: string | null;
    subject_label: string | null;
    ip_address: string | null;
    metadata: Record<string, unknown> | null;
};

type PaginatedLogs = {
    data: LogEntry[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};

interface Props {
    logs: PaginatedLogs;
    categories: string[];
    filters: {
        category: string;
        date_from: string;
        date_to: string;
    };
}

const categoryVariantMap: Record<string, string> = {
    authentication: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
    team: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
    api: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
    scan: 'bg-teal-100 text-teal-800 dark:bg-teal-900/30 dark:text-teal-300',
    issue: 'bg-rose-100 text-rose-800 dark:bg-rose-900/30 dark:text-rose-300',
    audit: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300',
    settings: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300',
    system: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-400',
};

const UNSET = '__all__';

export default function ActivityLogIndex({ logs, categories, filters }: Props) {
    const [category, setCategory] = useState(filters.category || UNSET);
    const [dateFrom, setDateFrom] = useState(filters.date_from);
    const [dateTo, setDateTo] = useState(filters.date_to);

    const hasFilters = filters.category || filters.date_from || filters.date_to;

    function applyFilters(e: React.FormEvent) {
        e.preventDefault();
        router.get(
            '/settings/activity-log',
            {
                ...(category !== UNSET && category ? { category } : {}),
                ...(dateFrom ? { date_from: dateFrom } : {}),
                ...(dateTo ? { date_to: dateTo } : {}),
            },
            { preserveState: true, replace: true },
        );
    }

    function clearFilters() {
        setCategory(UNSET);
        setDateFrom('');
        setDateTo('');
        router.get('/settings/activity-log', {}, { replace: true });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Activity Log" />

            <div className="space-y-6 p-6">
                <div className="flex items-start justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">Activity Log</h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Audit trail of all activity in your account over the past year.
                        </p>
                    </div>
                    <Button asChild variant="outline" size="sm">
                        <a href="/settings/activity-log/export">Export CSV</a>
                    </Button>
                </div>

                {/* Filters */}
                <form onSubmit={applyFilters} className="flex flex-wrap items-end gap-4 rounded border p-4">
                    <div className="space-y-1.5">
                        <Label htmlFor="category-filter">Category</Label>
                        <Select value={category} onValueChange={setCategory}>
                            <SelectTrigger id="category-filter" className="w-44">
                                <SelectValue placeholder="All categories" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={UNSET}>All categories</SelectItem>
                                {categories.map((cat) => (
                                    <SelectItem key={cat} value={cat}>
                                        {cat.charAt(0).toUpperCase() + cat.slice(1)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="date-from">From</Label>
                        <Input
                            id="date-from"
                            type="date"
                            value={dateFrom}
                            onChange={(e) => setDateFrom(e.target.value)}
                            className="w-36"
                        />
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="date-to">To</Label>
                        <Input
                            id="date-to"
                            type="date"
                            value={dateTo}
                            onChange={(e) => setDateTo(e.target.value)}
                            className="w-36"
                        />
                    </div>

                    <div className="flex gap-2">
                        <Button type="submit" size="sm">
                            Filter
                        </Button>
                        {hasFilters && (
                            <Button type="button" variant="outline" size="sm" onClick={clearFilters}>
                                Clear
                            </Button>
                        )}
                    </div>
                </form>

                {/* Summary */}
                {logs.total > 0 && (
                    <p className="text-muted-foreground text-sm">
                        Showing {(logs.current_page - 1) * logs.per_page + 1}–
                        {Math.min(logs.current_page * logs.per_page, logs.total)} of {logs.total} entries
                    </p>
                )}

                {logs.data.length === 0 ? (
                    <p className="text-muted-foreground text-sm">No activity log entries found.</p>
                ) : (
                    <>
                        <div className="rounded border">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b">
                                        <th className="px-4 py-3 text-left font-medium">Date &amp; Time</th>
                                        <th className="px-4 py-3 text-left font-medium">Event</th>
                                        <th className="px-4 py-3 text-left font-medium">Actor</th>
                                        <th className="px-4 py-3 text-left font-medium">Subject</th>
                                        <th className="px-4 py-3 text-left font-medium">IP Address</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {logs.data.map((entry) => (
                                        <tr key={entry.id}>
                                            <td className="text-muted-foreground whitespace-nowrap px-4 py-3">
                                                {new Date(entry.created_at).toLocaleString(undefined, {
                                                    year: 'numeric',
                                                    month: 'short',
                                                    day: 'numeric',
                                                    hour: '2-digit',
                                                    minute: '2-digit',
                                                })}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex flex-col gap-1">
                                                    <span>{entry.event_label}</span>
                                                    <Badge
                                                        variant="outline"
                                                        className={`w-fit border-0 text-xs ${categoryVariantMap[entry.event_category] ?? ''}`}
                                                    >
                                                        {entry.event_category}
                                                    </Badge>
                                                </div>
                                            </td>
                                            <td className="text-muted-foreground px-4 py-3">
                                                {entry.actor_label ?? '—'}
                                            </td>
                                            <td className="text-muted-foreground px-4 py-3">
                                                {entry.subject_label ?? '—'}
                                            </td>
                                            <td className="text-muted-foreground px-4 py-3 font-mono text-xs">
                                                {entry.ip_address ?? '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination */}
                        {logs.last_page > 1 && (
                            <div className="flex justify-end gap-1">
                                {logs.links.map((link, i) =>
                                    link.url ? (
                                        <Button
                                            key={i}
                                            size="sm"
                                            variant={link.active ? 'default' : 'outline'}
                                            onClick={() => router.get(link.url!)}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ) : (
                                        <Button
                                            key={i}
                                            size="sm"
                                            variant="outline"
                                            disabled
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ),
                                )}
                            </div>
                        )}
                    </>
                )}
            </div>
        </AppLayout>
    );
}
