import { useEffect, useRef, useState } from 'react';
import { Activity, AlertTriangle, ClipboardCheck, KeyRound, LogIn, RefreshCw, ScanLine, Settings2, Users } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { activityFeed as activityFeedRoute } from '@/routes/api';

// ── Types ────────────────────────────────────────────────────────────────────

export type ActivityEntry = {
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

export type ActivityFeedData = {
    data: ActivityEntry[];
    next_cursor: string | null;
};

// ── Category config ──────────────────────────────────────────────────────────

type CategoryConfig = {
    nodeClass: string;
    badgeClass: string;
    Icon: React.ComponentType<{ size?: number; className?: string; 'aria-hidden'?: boolean | 'true' | 'false' }>;
};

const CATEGORY_CONFIG: Record<string, CategoryConfig> = {
    authentication: {
        nodeClass: 'bg-blue-500',
        badgeClass: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
        Icon: LogIn,
    },
    team: {
        nodeClass: 'bg-purple-500',
        badgeClass: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
        Icon: Users,
    },
    api: {
        nodeClass: 'bg-amber-500',
        badgeClass: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
        Icon: KeyRound,
    },
    scan: {
        nodeClass: 'bg-teal-500',
        badgeClass: 'bg-teal-100 text-teal-800 dark:bg-teal-900/30 dark:text-teal-300',
        Icon: ScanLine,
    },
    issue: {
        nodeClass: 'bg-rose-500',
        badgeClass: 'bg-rose-100 text-rose-800 dark:bg-rose-900/30 dark:text-rose-300',
        Icon: AlertTriangle,
    },
    audit: {
        nodeClass: 'bg-indigo-500',
        badgeClass: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300',
        Icon: ClipboardCheck,
    },
    settings: {
        nodeClass: 'bg-slate-500',
        badgeClass: 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-300',
        Icon: Settings2,
    },
};

const DEFAULT_CONFIG: CategoryConfig = {
    nodeClass: 'bg-muted-foreground',
    badgeClass: 'bg-muted text-muted-foreground',
    Icon: Activity,
};

function getCategoryConfig(category: string): CategoryConfig {
    return CATEGORY_CONFIG[category] ?? DEFAULT_CONFIG;
}

// ── Helpers ──────────────────────────────────────────────────────────────────

const prefersReducedMotion =
    typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function relativeTime(iso: string): string {
    const diff = Date.now() - new Date(iso).getTime();
    const mins = Math.floor(diff / 60_000);
    if (mins < 1) return 'just now';
    if (mins < 60) return `${mins}m ago`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return `${hrs}h ago`;
    return `${Math.floor(hrs / 24)}d ago`;
}

function formatFullDate(iso: string): string {
    return new Date(iso).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

function dateGroupLabel(iso: string): string {
    const date = new Date(iso);
    const today = new Date();
    const yesterday = new Date(today);
    yesterday.setDate(today.getDate() - 1);

    const toKey = (d: Date) =>
        d.toLocaleDateString(undefined, { year: 'numeric', month: 'numeric', day: 'numeric' });

    if (toKey(date) === toKey(today)) return 'Today';
    if (toKey(date) === toKey(yesterday)) return 'Yesterday';

    return date.toLocaleDateString(undefined, { day: 'numeric', month: 'long', year: 'numeric' });
}

type DateGroup = { label: string; entries: ActivityEntry[] };

function groupByDate(entries: ActivityEntry[]): DateGroup[] {
    const groups = new Map<string, DateGroup>();

    for (const entry of entries) {
        const label = dateGroupLabel(entry.created_at);
        if (!groups.has(label)) {
            groups.set(label, { label, entries: [] });
        }
        groups.get(label)!.entries.push(entry);
    }

    return Array.from(groups.values());
}

// ── Sub-components ───────────────────────────────────────────────────────────

function TimelineNode({ category }: { category: string }) {
    const { nodeClass, Icon } = getCategoryConfig(category);

    return (
        <div className={`z-10 flex h-7 w-7 shrink-0 items-center justify-center rounded-full ${nodeClass}`}>
            <Icon size={14} className="text-white" aria-hidden />
        </div>
    );
}

function TimelineItem({
    entry,
    isLast,
    newIndex,
}: {
    entry: ActivityEntry;
    isLast: boolean;
    newIndex?: number;
}) {
    const { badgeClass } = getCategoryConfig(entry.event_category);
    const labelId = `entry-${entry.id}-label`;
    const isNew = newIndex !== undefined;

    return (
        <li
            aria-labelledby={labelId}
            className="relative flex gap-4"
            style={
                isNew && !prefersReducedMotion
                    ? {
                          animation: 'timeline-slide-down 200ms ease-out both',
                          animationDelay: `${newIndex * 50}ms`,
                      }
                    : undefined
            }
        >
            <div className="flex w-8 flex-col items-center" aria-hidden="true">
                <TimelineNode category={entry.event_category} />
                {!isLast && <div className="mt-1 w-px flex-1 bg-border" />}
            </div>

            <div className="flex-1 pb-5 pt-0.5">
                <p id={labelId} className="text-sm leading-snug">
                    <span className="font-semibold text-foreground">{entry.actor_label}</span>
                    {' — '}
                    <span className="text-muted-foreground">{entry.event_label}</span>
                    {entry.subject_label && (
                        <>
                            {': '}
                            <span className="font-medium text-foreground">{entry.subject_label}</span>
                        </>
                    )}
                </p>
                <div className="mt-1.5 flex flex-wrap items-center gap-2">
                    <span className={`inline-flex items-center rounded px-2 py-0.5 text-xs font-medium ${badgeClass}`}>
                        {entry.event_category}
                    </span>
                    <time dateTime={entry.created_at} title={formatFullDate(entry.created_at)} className="text-xs text-muted-foreground">
                        {relativeTime(entry.created_at)}
                    </time>
                    {entry.ip_address && <span className="font-mono text-xs opacity-60">{entry.ip_address}</span>}
                </div>
            </div>
        </li>
    );
}

function TimelineDateGroupSeparator({ label }: { label: string }) {
    return (
        <li role="separator" aria-label={label}>
            <h3 className="sticky top-0 z-20 bg-card py-1.5 text-xs font-medium text-muted-foreground">{label}</h3>
        </li>
    );
}

function PollIndicator({ error }: { error: boolean }) {
    return (
        <div
            className="flex items-center gap-1.5 text-xs text-muted-foreground"
            title={error ? 'Polling paused — connection issue' : 'Live updates active'}
        >
            <span
                className={`h-2 w-2 rounded-full ${error ? 'bg-amber-400' : 'animate-pulse bg-green-500'}`}
                aria-hidden="true"
            />
            <span>{error ? 'Paused' : 'Live'}</span>
        </div>
    );
}

export function TimelineSkeleton() {
    return (
        <div className="flex flex-col gap-5" aria-busy="true" aria-label="Loading activity feed">
            {[0, 1, 2, 3, 4].map((i) => (
                <div key={i} className="flex gap-4">
                    <Skeleton className="h-7 w-7 shrink-0 rounded-full" />
                    <div className="flex-1 space-y-2 pt-0.5">
                        <Skeleton className="h-4 w-2/3" />
                        <Skeleton className="h-3 w-1/3" />
                    </div>
                </div>
            ))}
        </div>
    );
}

// ── Main component ───────────────────────────────────────────────────────────

const POLL_INTERVAL_MS = 30_000;
const POLL_ERROR_THRESHOLD = 3;

interface ActivityTimelineProps {
    initialData: ActivityFeedData;
}

export function ActivityTimeline({ initialData }: ActivityTimelineProps) {
    const [entries, setEntries] = useState<ActivityEntry[]>(initialData.data);
    const [nextCursor, setNextCursor] = useState<string | null>(initialData.next_cursor);
    const [pendingNew, setPendingNew] = useState<ActivityEntry[]>([]);
    const [isLoadingMore, setIsLoadingMore] = useState(false);
    const [pollError, setPollError] = useState(false);
    const [newIdIndexMap, setNewIdIndexMap] = useState<Map<number, number>>(new Map());

    const pollErrorCount = useRef(0);
    const highWaterMarkRef = useRef<number>(initialData.data[0]?.id ?? 0);

    useEffect(() => {
        if (entries.length > 0 && entries[0].id > highWaterMarkRef.current) {
            highWaterMarkRef.current = entries[0].id;
        }
    }, [entries]);

    useEffect(() => {
        const interval = setInterval(async () => {
            const afterId = highWaterMarkRef.current;
            if (afterId === 0) return;

            try {
                const res = await fetch(activityFeedRoute.url({ query: { after_id: afterId } }), {
                    headers: { Accept: 'application/json' },
                });

                if (!res.ok) throw new Error(`HTTP ${res.status}`);

                const json: ActivityFeedData = await res.json();

                pollErrorCount.current = 0;
                setPollError(false);

                if (json.data.length > 0) {
                    const newestFirst = [...json.data].reverse();
                    highWaterMarkRef.current = newestFirst[0].id;
                    setPendingNew((prev) => [...newestFirst, ...prev]);
                }
            } catch {
                pollErrorCount.current += 1;
                if (pollErrorCount.current >= POLL_ERROR_THRESHOLD) {
                    setPollError(true);
                }
            }
        }, POLL_INTERVAL_MS);

        return () => clearInterval(interval);
    }, []);

    function acceptPending() {
        const idMap = new Map(pendingNew.map((e, i) => [e.id, i]));
        setEntries((prev) => [...pendingNew, ...prev]);
        setPendingNew([]);
        setNewIdIndexMap(idMap);
        setTimeout(() => setNewIdIndexMap(new Map()), pendingNew.length * 50 + 700);
    }

    async function loadMore() {
        if (!nextCursor || isLoadingMore) return;

        setIsLoadingMore(true);

        try {
            const res = await fetch(activityFeedRoute.url({ query: { cursor: nextCursor } }), {
                headers: { Accept: 'application/json' },
            });

            if (!res.ok) throw new Error(`HTTP ${res.status}`);

            const json: ActivityFeedData = await res.json();
            setEntries((prev) => [...prev, ...json.data]);
            setNextCursor(json.next_cursor);
        } catch {
            // silent — user can retry
        } finally {
            setIsLoadingMore(false);
        }
    }

    if (entries.length === 0 && pendingNew.length === 0) {
        return (
            <div className="flex flex-col items-center justify-center py-12 text-sm text-muted-foreground">
                <Activity size={32} className="mb-3 opacity-40" aria-hidden="true" />
                <p>No activity recorded yet.</p>
            </div>
        );
    }

    const groups = groupByDate(entries);

    const flatItems: ({ type: 'separator'; label: string } | { type: 'entry'; entry: ActivityEntry; isLast: boolean })[] =
        [];
    for (const group of groups) {
        flatItems.push({ type: 'separator', label: group.label });
        group.entries.forEach((entry, idx) => {
            flatItems.push({ type: 'entry', entry, isLast: idx === group.entries.length - 1 });
        });
    }

    return (
        <div className="space-y-3">
            <div className="flex items-center justify-between">
                <PollIndicator error={pollError} />

                <div aria-live="polite" aria-atomic="true">
                    {pendingNew.length > 0 && (
                        <Button variant="outline" size="sm" onClick={acceptPending} className="text-xs">
                            ↑ {pendingNew.length} new event{pendingNew.length !== 1 ? 's' : ''} — click to load
                        </Button>
                    )}
                </div>
            </div>

            <ol role="feed" aria-label="Activity timeline" aria-busy={isLoadingMore} className="relative">
                {flatItems.map((item, idx) =>
                    item.type === 'separator' ? (
                        <TimelineDateGroupSeparator key={`sep-${item.label}-${idx}`} label={item.label} />
                    ) : (
                        <TimelineItem
                            key={item.entry.id}
                            entry={item.entry}
                            isLast={item.isLast}
                            newIndex={newIdIndexMap.get(item.entry.id)}
                        />
                    ),
                )}
            </ol>

            <div aria-live="polite" aria-atomic="true" className="pt-2 text-center">
                {nextCursor !== null ? (
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={loadMore}
                        aria-disabled={isLoadingMore}
                        className="min-h-[44px] min-w-[44px]"
                    >
                        {isLoadingMore ? (
                            <>
                                <RefreshCw size={14} className="mr-2 animate-spin" aria-hidden="true" />
                                Loading…
                            </>
                        ) : (
                            'Load more'
                        )}
                    </Button>
                ) : (
                    entries.length > 0 && (
                        <p className="text-xs text-muted-foreground">All activity loaded</p>
                    )
                )}
            </div>
        </div>
    );
}
