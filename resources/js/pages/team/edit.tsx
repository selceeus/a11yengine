import { Form, Head, Link, usePage } from '@inertiajs/react';
import * as TeamController from '@/actions/App/Http/Controllers/TeamController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Member = {
    id: number;
    name: string;
    email: string;
    must_change_password: boolean;
};

type Role = {
    value: string;
    label: string;
};

export default function Edit({
    member,
    currentRole,
    availableRoles,
}: {
    member: Member;
    currentRole: string | null;
    availableRoles: Role[];
}) {
    const { flash } = usePage().props;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Team', href: TeamController.index().url },
        { title: member.name, href: TeamController.edit({ user: member.id }).url },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${member.name}`} />

            <div className="flex flex-col gap-8 p-6">
                <div className="flex items-center gap-3">
                    <Heading title={`Edit ${member.name}`} />
                    {member.must_change_password && (
                        <Badge variant="outline" className="text-amber-600 border-amber-400">
                            Must change password
                        </Badge>
                    )}
                </div>

                {flash.status && (
                    <Alert>
                        <AlertDescription>{flash.status}</AlertDescription>
                    </Alert>
                )}

                {/* Profile section */}
                <section className="max-w-md">
                    <Heading variant="small" title="Profile" description="Update name and email address." />
                    <div className="rounded-xl border bg-card p-6 mt-4">
                        <Form
                            {...TeamController.update.form({ user: member.id })}
                            options={{ preserveScroll: true }}
                            className="flex flex-col gap-5"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="name">Name</Label>
                                        <Input id="name" name="name" defaultValue={member.name} type="text" required />
                                        <InputError message={errors.name} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="email">Email address</Label>
                                        <Input id="email" name="email" defaultValue={member.email} type="email" required />
                                        <InputError message={errors.email} />
                                    </div>
                                    <div>
                                        <Button type="submit" disabled={processing}>
                                            {processing ? 'Saving…' : 'Save changes'}
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </div>
                </section>

                {/* Password section */}
                <section className="max-w-md">
                    <Heading
                        variant="small"
                        title="Reset password"
                        description="Set a new temporary password. The user will be required to change it on next login."
                    />
                    <div className="rounded-xl border bg-card p-6 mt-4">
                        <Form
                            {...TeamController.updatePassword.form({ user: member.id })}
                            options={{ preserveScroll: true }}
                            className="flex flex-col gap-5"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="new-password">New password</Label>
                                        <Input
                                            id="new-password"
                                            name="password"
                                            type="password"
                                            autoComplete="new-password"
                                            required
                                        />
                                        <InputError message={errors.password} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="new-password-confirmation">Confirm password</Label>
                                        <Input
                                            id="new-password-confirmation"
                                            name="password_confirmation"
                                            type="password"
                                            autoComplete="new-password"
                                            required
                                        />
                                    </div>
                                    <div>
                                        <Button type="submit" disabled={processing} variant="outline">
                                            {processing ? 'Updating…' : 'Update password'}
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </div>
                </section>

                {/* Role section */}
                <section className="max-w-md">
                    <Heading variant="small" title="Agency role" description="Assign an agency-level role to this user." />
                    <div className="rounded-xl border bg-card p-6 mt-4">
                        <Form
                            {...TeamController.updateRole.form({ user: member.id })}
                            options={{ preserveScroll: true }}
                            className="flex flex-col gap-5"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="role">Role</Label>
                                        <Select name="role" defaultValue={currentRole ?? ''}>
                                            <SelectTrigger id="role">
                                                <SelectValue placeholder="No role assigned" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="">No role</SelectItem>
                                                {availableRoles.map((role) => (
                                                    <SelectItem key={role.value} value={role.value}>
                                                        {role.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.role} />
                                    </div>
                                    <div>
                                        <Button type="submit" disabled={processing} variant="outline">
                                            {processing ? 'Saving…' : 'Save role'}
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </div>
                </section>

                <div>
                    <Button variant="ghost" asChild>
                        <Link href={TeamController.index().url}>← Back to team</Link>
                    </Button>
                </div>
            </div>
        </AppLayout>
    );
}
