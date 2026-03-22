import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { MessageSquare, Send } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import MentionTextarea from '@/components/MentionTextarea';

type TeamMember = { id: number; name: string };

type Activity = {
    id: number;
    type: 'comment' | 'status_change' | 'assignment' | 'due_date_change' | 'bulk_action';
    body: string | null;
    metadata: Record<string, string | number | null> | null;
    created_at: string;
    user: { id: number; name: string } | null;
};

type Issue = { id: number; rule_key: string };

interface IssueActivityLogProps {
    issue: Issue;
    activities: Activity[];
    teamMembers: TeamMember[];
}

const statusLabels: Record<string, string> = {
    open: 'Open',
    in_progress: 'In progress',
    resolved: 'Resolved',
    ignored: 'Ignored',
    false_positive: 'False positive',
};

function systemText(activity: Activity): string {
    const meta = activity.metadata ?? {};
    const actor = activity.user?.name ?? 'Someone';

    switch (activity.type) {
        case 'status_change':
            return `${actor} changed status from "${statusLabels[String(meta.from)] ?? meta.from}" to "${statusLabels[String(meta.to)] ?? meta.to}"`;
        case 'assignment':
            if (!meta.to_user_id) {
                return `${actor} removed the assignee`;
            }
            return `${actor} updated the assignee`;
        case 'due_date_change':
            if (!meta.to) {
                return `${actor} removed the due date`;
            }
            return `${actor} set due date to ${new Date(String(meta.to)).toLocaleDateString()}`;
        case 'bulk_action':
            return `${actor} applied bulk action: ${meta.action}`;
        default:
            return `${actor} performed an action`;
    }
}

function highlightMentions(text: string): React.ReactNode {
    const parts = text.split(/(\B@\w+)/g);
    return parts.map((part, i) =>
        part.startsWith('@') ? (
            <span key={i} className="font-medium text-primary">
                {part}
            </span>
        ) : (
            part
        ),
    );
}

function ActivityItem({ activity }: { activity: Activity }) {
    const isComment = activity.type === 'comment';
    const time = new Date(activity.created_at).toLocaleString();

    return (
        <div className="flex gap-3">
            <div className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold uppercase text-muted-foreground">
                {activity.user?.name?.charAt(0) ?? '?'}
            </div>
            <div className="flex-1 space-y-0.5">
                {isComment ? (
                    <>
                        <p className="text-sm font-medium">{activity.user?.name ?? 'Unknown'}</p>
                        <p className="text-sm text-foreground">{highlightMentions(activity.body ?? '')}</p>
                    </>
                ) : (
                    <p className="text-sm text-muted-foreground">{systemText(activity)}</p>
                )}
                <p className="text-xs text-muted-foreground">{time}</p>
            </div>
        </div>
    );
}

export default function IssueActivityLog({ issue, activities, teamMembers }: IssueActivityLogProps) {
    const [open, setOpen] = useState(false);

    const form = useForm({ body: '' });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        form.post(`/issues/${issue.id}/comments`, {
            preserveScroll: true,
            onSuccess: () => form.reset('body'),
        });
    }

    return (
        <Sheet open={open} onOpenChange={setOpen}>
            <SheetTrigger asChild>
                <Button variant="outline" size="sm" className="gap-1.5">
                    <MessageSquare className="h-4 w-4" />
                    Activity
                    {activities.length > 0 && (
                        <Badge variant="secondary" className="ml-0.5 h-4 min-w-4 px-1 text-[10px]">
                            {activities.length}
                        </Badge>
                    )}
                </Button>
            </SheetTrigger>

            <SheetContent side="right" className="flex w-full flex-col sm:max-w-md">
                <SheetHeader>
                    <SheetTitle>Activity log</SheetTitle>
                </SheetHeader>

                {/* Timeline */}
                <div className="flex-1 overflow-y-auto px-4 py-2">
                    {activities.length === 0 ? (
                        <div className="py-12 text-center text-sm text-muted-foreground">No activity yet.</div>
                    ) : (
                        <div className="space-y-5">
                            {[...activities].reverse().map((activity) => (
                                <ActivityItem key={activity.id} activity={activity} />
                            ))}
                        </div>
                    )}
                </div>

                {/* Compose */}
                <div className="border-t px-4 py-4">
                    <form onSubmit={submit} className="space-y-2">
                        <MentionTextarea
                            value={form.data.body}
                            onChange={(v) => form.setData('body', v)}
                            teamMembers={teamMembers}
                            placeholder="Add a comment… use @ to mention a team member"
                            rows={3}
                        />
                        {form.errors.body && <p className="text-xs text-destructive">{form.errors.body}</p>}
                        <div className="flex justify-end">
                            <Button type="submit" size="sm" disabled={form.processing || !form.data.body.trim()} className="gap-1.5">
                                <Send className="h-3.5 w-3.5" />
                                Comment
                            </Button>
                        </div>
                    </form>
                </div>
            </SheetContent>
        </Sheet>
    );
}
