import { Head, router } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Role = {
    id: number;
    role: string;
    organization: { id: number; name: string } | null;
    property: { id: number; name: string } | null;
};

type ReviewUser = {
    id: number;
    name: string;
    email: string;
    two_factor_confirmed_at: string | null;
    roles: Role[];
};

type Review = {
    id: number;
    period: string;
    status: 'pending' | 'completed';
    due_at: string;
    completed_at: string | null;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Settings', href: '/settings/profile' },
    { title: 'Access Reviews', href: '/settings/access-reviews' },
    { title: 'Review', href: '#' },
];

function roleScope(role: Role): string {
    if (role.property) return `property: ${role.property.name}`;
    if (role.organization) return `org: ${role.organization.name}`;
    return 'agency';
}

export default function AccessReviewShow({ review, users }: { review: Review; users: ReviewUser[] }) {
    const isPending = review.status === 'pending';

    function confirm(userId: number) {
        router.post(`/settings/access-reviews/${review.id}/users/${userId}/confirm`);
    }

    function revoke(userId: number) {
        if (!confirm(`Remove all agency roles for this user?`)) return;
        router.post(`/settings/access-reviews/${review.id}/users/${userId}/revoke`);
    }

    function complete() {
        router.post(`/settings/access-reviews/${review.id}/complete`);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Access Review — ${review.period}`} />

            <div className="space-y-6 p-6">
                <div className="flex items-start justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">Access Review — {review.period}</h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            {isPending
                                ? `Due ${new Date(review.due_at).toLocaleDateString()}. Review each user and confirm or revoke their access.`
                                : `Completed ${new Date(review.completed_at!).toLocaleDateString()}.`}
                        </p>
                    </div>
                    <Badge variant={isPending ? 'destructive' : 'default'}>{review.status}</Badge>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Team Members</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Email</TableHead>
                                    <TableHead>Roles</TableHead>
                                    <TableHead>2FA</TableHead>
                                    {isPending && <TableHead className="text-right">Actions</TableHead>}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {users.map((user) => (
                                    <TableRow key={user.id}>
                                        <TableCell className="font-medium">{user.name}</TableCell>
                                        <TableCell className="text-muted-foreground text-sm">{user.email}</TableCell>
                                        <TableCell>
                                            <div className="flex flex-wrap gap-1">
                                                {user.roles.map((role) => (
                                                    <Badge key={role.id} variant="secondary" className="text-xs">
                                                        {role.role} · {roleScope(role)}
                                                    </Badge>
                                                ))}
                                                {user.roles.length === 0 && (
                                                    <span className="text-muted-foreground text-xs">no role</span>
                                                )}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            {user.two_factor_confirmed_at ? (
                                                <Badge variant="default" className="text-xs">Enabled</Badge>
                                            ) : (
                                                <Badge variant="outline" className="text-xs">Disabled</Badge>
                                            )}
                                        </TableCell>
                                        {isPending && (
                                            <TableCell className="text-right">
                                                <div className="flex justify-end gap-2">
                                                    <Button size="sm" variant="outline" onClick={() => confirm(user.id)}>
                                                        Confirm
                                                    </Button>
                                                    <Button size="sm" variant="destructive" onClick={() => revoke(user.id)}>
                                                        Revoke
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        )}
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                {isPending && (
                    <div className="flex justify-end">
                        <Button onClick={complete}>Mark Review Complete</Button>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
