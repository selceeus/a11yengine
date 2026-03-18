import { useEffect, useRef, useState } from 'react';
import AssignIssueController from '@/actions/App/Http/Controllers/Api/AssignIssueController';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';

export type AssignableUser = {
    id: number;
    name: string;
    email: string;
};

type AssignedUser = {
    id: number;
    name: string;
    email: string;
};

export type AssignableIssue = {
    id: number;
    assigned_user_id: number | null;
    assigned_user: AssignedUser | null;
};

interface IssueAssignerProps {
    issue: AssignableIssue;
    users: AssignableUser[];
    canAssign: boolean;
    onAssigned?: (assignedUser: AssignedUser | null) => void;
}

type AlertState = { type: 'success' | 'error'; message: string } | null;

function getCsrfToken(): string {
    return (document.head.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
}

export default function IssueAssigner({ issue, users, canAssign, onAssigned }: IssueAssignerProps) {
    const [assignedUserId, setAssignedUserId] = useState<number | null>(issue.assigned_user_id);
    const [submitting, setSubmitting] = useState(false);
    const [alert, setAlert] = useState<AlertState>(null);
    const dismissTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => {
        setAssignedUserId(issue.assigned_user_id);
    }, [issue.assigned_user_id]);

    useEffect(() => {
        return () => {
            if (dismissTimerRef.current) {
                clearTimeout(dismissTimerRef.current);
            }
        };
    }, []);

    if (!canAssign) {
        return null;
    }

    function showAlert(type: 'success' | 'error', message: string) {
        setAlert({ type, message });

        if (dismissTimerRef.current) {
            clearTimeout(dismissTimerRef.current);
        }

        dismissTimerRef.current = setTimeout(() => {
            setAlert(null);
        }, 3000);
    }

    async function handleAssign(value: string) {
        const userId = value === 'unassigned' ? null : parseInt(value, 10);

        if (userId === assignedUserId) {
            return;
        }

        setSubmitting(true);
        setAlert(null);

        try {
            const response = await fetch(AssignIssueController.url(issue.id), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify({ user_id: userId }),
            });

            if (!response.ok) {
                const body = (await response.json().catch(() => ({}))) as { message?: string };
                showAlert('error', body.message ?? 'Failed to assign issue.');
                return;
            }

            const updated = (await response.json()) as { assigned_user: AssignedUser | null; assigned_user_id: number | null };
            setAssignedUserId(updated.assigned_user_id);
            const assignee = userId !== null ? (users.find((u) => u.id === userId) ?? null) : null;
            showAlert('success', assignee ? `Assigned to ${assignee.name}.` : 'Issue unassigned.');
            onAssigned?.(updated.assigned_user);
        } catch {
            showAlert('error', 'An unexpected error occurred.');
        } finally {
            setSubmitting(false);
        }
    }

    const selectValue = assignedUserId !== null ? String(assignedUserId) : 'unassigned';

    return (
        <div className="flex flex-col gap-2">
            <div className="flex items-center gap-2">
                {submitting && <Spinner className="h-4 w-4 shrink-0" />}

                <Select value={selectValue} onValueChange={handleAssign} disabled={submitting}>
                    <SelectTrigger className="w-44">
                        <SelectValue placeholder="Unassigned" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="unassigned">Unassigned</SelectItem>
                        {users.map((user) => (
                            <SelectItem key={user.id} value={String(user.id)}>
                                {user.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>

            {alert && (
                <Alert variant={alert.type === 'error' ? 'destructive' : 'default'} className="py-2 text-sm">
                    <AlertDescription>{alert.message}</AlertDescription>
                </Alert>
            )}
        </div>
    );
}
