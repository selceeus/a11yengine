import { Form, Head, Link, usePage, useForm } from '@inertiajs/react';
import SendInvitationController from '@/actions/App/Http/Controllers/SendInvitationController';
import * as TeamController from '@/actions/App/Http/Controllers/TeamController';
import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Member = {
    id: number;
    name: string;
    email: string;
    created_at: string;
};

type Invitation = {
    id: number;
    email: string;
    created_at: string;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Team', href: TeamController.index().url },
];

function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

export default function Index({
    members,
    invitations,
    canManageTeam,
}: {
    members: Member[];
    invitations: Invitation[];
    canManageTeam: boolean;
}) {
    const { flash, auth } = usePage().props;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Team" />

            <div className="flex flex-col gap-8 p-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">Team</h1>
                    {canManageTeam && (
                        <Button asChild size="sm">
                            <Link href={TeamController.create().url}>Add member</Link>
                        </Button>
                    )}
                </div>

                {flash.status && (
                    <Alert>
                        <AlertDescription>{flash.status}</AlertDescription>
                    </Alert>
                )}

                {/* Members */}
                <section>
                    <h2 className="mb-3 text-sm font-medium text-muted-foreground uppercase tracking-wide">
                        Members ({members.length})
                    </h2>
                    <div className="rounded border bg-card">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-xs text-muted-foreground">
                                    <th className="px-4 py-2 text-left font-medium">Name</th>
                                    <th className="px-4 py-2 text-left font-medium">Email</th>
                                    <th className="px-4 py-2 text-left font-medium">Joined</th>
                                    <th className="px-4 py-2" />
                                </tr>
                            </thead>
                            <tbody className="divide-y px-4">
                                {members.map((member) => (
                                    <tr key={member.id}>
                                        <td className="px-4 py-3 font-medium">{member.name}</td>
                                        <td className="px-4 py-3 text-muted-foreground">{member.email}</td>
                                        <td className="px-4 py-3 text-muted-foreground">{formatDate(member.created_at)}</td>
                                        <td className="px-4 py-3 text-right">
                                            {canManageTeam && (
                                                <div className="flex items-center justify-end gap-2">
                                                    <Button variant="outline" size="sm" asChild>
                                                        <Link href={TeamController.edit({ user: member.id }).url}>Edit</Link>
                                                    </Button>
                                                    {member.id !== auth.user.id && (
                                                        <RemoveMemberButton member={member} />
                                                    )}
                                                </div>
                                            )}
                                            {!canManageTeam && member.id !== auth.user.id && (
                                                <RemoveMemberButton member={member} />
                                            )}
                                        </td>
                                    </tr>
                                ))}
                                {members.length === 0 && (
                                    <tr>
                                        <td colSpan={4} className="px-4 py-6 text-center text-muted-foreground">
                                            No members yet.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>

                {/* Pending Invitations */}
                {invitations.length > 0 && (
                    <section>
                        <h2 className="mb-3 text-sm font-medium text-muted-foreground uppercase tracking-wide">
                            Pending Invitations ({invitations.length})
                        </h2>
                        <div className="rounded border bg-card">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-xs text-muted-foreground">
                                        <th className="px-4 py-2 text-left font-medium">Email</th>
                                        <th className="px-4 py-2 text-left font-medium">Sent</th>
                                        <th className="px-4 py-2" />
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {invitations.map((invitation) => (
                                        <tr key={invitation.id}>
                                            <td className="px-4 py-3">{invitation.email}</td>
                                            <td className="px-4 py-3 text-muted-foreground">{formatDate(invitation.created_at)}</td>
                                            <td className="px-4 py-3 text-right">
                                                <CancelInvitationButton invitation={invitation} />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>
                )}

                {/* Invite form */}
                <section>
                    <h2 className="mb-3 text-sm font-medium text-muted-foreground uppercase tracking-wide">
                        Invite a team member
                    </h2>
                    <div className="max-w-md rounded border bg-card p-6">
                        <Form
                            {...SendInvitationController.form()}
                            className="flex flex-col gap-4"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="email">Email address</Label>
                                        <Input
                                            id="email"
                                            name="email"
                                            type="email"
                                            placeholder="colleague@example.com"
                                            autoComplete="off"
                                        />
                                        <InputError message={errors.email} />
                                    </div>
                                    <Button type="submit" disabled={processing} className="w-fit">
                                        {processing ? 'Sending…' : 'Send invitation'}
                                    </Button>
                                </>
                            )}
                        </Form>
                    </div>
                </section>
            </div>
        </AppLayout>
    );
}

function RemoveMemberButton({ member }: { member: Member }) {
    const { delete: destroy, processing } = useForm();

    function handleRemove() {
        if (!confirm(`Remove ${member.name} from the team?`)) return;
        destroy(TeamController.destroyMember({ user: member.id }).url);
    }

    return (
        <Button
            variant="destructive"
            size="sm"
            onClick={handleRemove}
            disabled={processing}
        >
            Remove
        </Button>
    );
}

function CancelInvitationButton({ invitation }: { invitation: Invitation }) {
    const { delete: destroy, processing } = useForm();

    function handleCancel() {
        if (!confirm(`Cancel invitation for ${invitation.email}?`)) return;
        destroy(TeamController.destroyInvitation({ invitation: invitation.id }).url);
    }

    return (
        <Button
            variant="outline"
            size="sm"
            onClick={handleCancel}
            disabled={processing}
        >
            Cancel
        </Button>
    );
}
