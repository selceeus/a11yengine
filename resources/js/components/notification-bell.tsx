import { useCallback, useEffect, useState } from 'react';
import { Bell, Check } from 'lucide-react';
import { router, usePage } from '@inertiajs/react';

import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useEchoPrivate } from '@/hooks/use-echo';

type NotificationData = {
    scan_id?: number;
    property_name?: string;
    total_violations?: number;
    issue_id?: number;
    rule_key?: string;
    severity?: string;
    assigner_name?: string;
    [key: string]: unknown;
};

type Notification = {
    id: string;
    type: string;
    data: NotificationData;
    read_at: string | null;
    created_at: string;
};

type PaginatedResponse = {
    data: Notification[];
    current_page: number;
    last_page: number;
};

function notificationTitle(n: Notification): string {
    if (n.type.includes('ScanCompleted')) {
        return `Scan completed: ${n.data.property_name ?? 'Unknown'}`;
    }
    if (n.type.includes('IssueAssigned')) {
        return `Issue assigned: ${n.data.rule_key ?? 'Unknown'}`;
    }
    return 'Notification';
}

function notificationDescription(n: Notification): string {
    if (n.type.includes('ScanCompleted')) {
        return `${n.data.total_violations ?? 0} violations found`;
    }
    if (n.type.includes('IssueAssigned')) {
        return `${n.data.severity ?? ''} severity — assigned by ${n.data.assigner_name ?? 'someone'}`;
    }
    return '';
}

function timeAgo(dateString: string): string {
    const seconds = Math.floor((Date.now() - new Date(dateString).getTime()) / 1000);
    if (seconds < 60) return 'just now';
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `${minutes}m ago`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours}h ago`;
    const days = Math.floor(hours / 24);
    return `${days}d ago`;
}

export function NotificationBell() {
    const [notifications, setNotifications] = useState<Notification[]>([]);
    const [unreadCount, setUnreadCount] = useState(0);
    const [open, setOpen] = useState(false);
    const agencyId = usePage().props.auth?.agencyId;

    const fetchNotifications = useCallback(async () => {
        try {
            const res = await fetch('/api/notifications', {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) return;
            const data: PaginatedResponse = await res.json();
            setNotifications(data.data.slice(0, 10));
            setUnreadCount(data.data.filter((n) => !n.read_at).length);
        } catch {
            // silently ignore
        }
    }, []);

    useEffect(() => {
        fetchNotifications();
        const interval = setInterval(fetchNotifications, 30000);
        return () => clearInterval(interval);
    }, [fetchNotifications]);

    // Real-time: refresh notifications when a scan completes or progress updates
    useEchoPrivate(agencyId ? `agency.${agencyId}` : null, {
        ScanCompleted: () => fetchNotifications(),
        ScanProgressUpdated: () => fetchNotifications(),
    });

    const markAsRead = async (id: string) => {
        await fetch(`/api/notifications/${id}/read`, {
            method: 'PATCH',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '' },
        });
        setNotifications((prev) => prev.map((n) => (n.id === id ? { ...n, read_at: new Date().toISOString() } : n)));
        setUnreadCount((c) => Math.max(0, c - 1));
    };

    const markAllAsRead = async () => {
        await fetch('/api/notifications/read-all', {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '' },
        });
        setNotifications((prev) => prev.map((n) => ({ ...n, read_at: n.read_at ?? new Date().toISOString() })));
        setUnreadCount(0);
    };

    return (
        <DropdownMenu open={open} onOpenChange={setOpen}>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" className="relative">
                    <Bell className="h-5 w-5" />
                    {unreadCount > 0 && (
                        <span className="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[10px] font-bold text-destructive-foreground">
                            {unreadCount > 9 ? '9+' : unreadCount}
                        </span>
                    )}
                    <span className="sr-only">Notifications</span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-80">
                <div className="flex items-center justify-between px-2">
                    <DropdownMenuLabel>Notifications</DropdownMenuLabel>
                    {unreadCount > 0 && (
                        <button
                            onClick={(e) => {
                                e.preventDefault();
                                markAllAsRead();
                            }}
                            className="text-xs text-muted-foreground hover:text-foreground"
                        >
                            Mark all read
                        </button>
                    )}
                </div>
                <DropdownMenuSeparator />
                {notifications.length === 0 ? (
                    <div className="px-2 py-4 text-center text-sm text-muted-foreground">No notifications</div>
                ) : (
                    notifications.map((n) => (
                        <DropdownMenuItem
                            key={n.id}
                            className="flex cursor-pointer items-start gap-2 py-2"
                            onClick={() => {
                                if (!n.read_at) markAsRead(n.id);
                            }}
                        >
                            <div className="flex-1 space-y-0.5">
                                <p className={`text-sm leading-tight ${!n.read_at ? 'font-semibold' : ''}`}>
                                    {notificationTitle(n)}
                                </p>
                                <p className="text-xs text-muted-foreground">{notificationDescription(n)}</p>
                                <p className="text-xs text-muted-foreground">{timeAgo(n.created_at)}</p>
                            </div>
                            {!n.read_at && <span className="mt-1 h-2 w-2 shrink-0 rounded-full bg-primary" />}
                        </DropdownMenuItem>
                    ))
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
