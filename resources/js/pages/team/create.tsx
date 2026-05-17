import { Form, Head, Link } from '@inertiajs/react';
import * as TeamController from '@/actions/App/Http/Controllers/TeamController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Role = {
    value: string;
    label: string;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Team', href: TeamController.index().url },
    { title: 'Add member', href: TeamController.create().url },
];

export default function Create({ availableRoles }: { availableRoles: Role[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Add Team Member" />

            <div className="flex flex-col gap-8 p-6">
                <Heading title="Add team member" description="Create a new user and add them directly to your team." />

                <div className="max-w-md rounded border bg-card p-6">
                    <Form
                        {...TeamController.store.form()}
                        className="flex flex-col gap-5"
                    >
                        {({ errors, processing }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Name</Label>
                                    <Input id="name" name="name" type="text" autoComplete="off" required />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="email">Email address</Label>
                                    <Input id="email" name="email" type="email" autoComplete="off" required />
                                    <InputError message={errors.email} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="password">Temporary password</Label>
                                    <Input id="password" name="password" type="password" autoComplete="new-password" required />
                                    <p className="text-xs text-muted-foreground">
                                        The user will be required to change this on first login.
                                    </p>
                                    <InputError message={errors.password} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="password_confirmation">Confirm password</Label>
                                    <Input
                                        id="password_confirmation"
                                        name="password_confirmation"
                                        type="password"
                                        autoComplete="new-password"
                                        required
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="role">Role (optional)</Label>
                                    <Select name="role">
                                        <SelectTrigger id="role">
                                            <SelectValue placeholder="No role assigned" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {availableRoles.map((role) => (
                                                <SelectItem key={role.value} value={role.value}>
                                                    {role.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.role} />
                                </div>

                                <div className="flex items-center gap-3">
                                    <Button type="submit" disabled={processing}>
                                        {processing ? 'Adding…' : 'Add member'}
                                    </Button>
                                    <Button variant="outline" asChild>
                                        <Link href={TeamController.index().url}>Cancel</Link>
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                </div>
            </div>
        </AppLayout>
    );
}
