import { Form, Head, Link } from '@inertiajs/react';
import * as OrganizationController from '@/actions/App/Http/Controllers/OrganizationController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type Organization = {
    id: number;
    name: string;
    domain: string | null;
    status: string;
};

export default function Edit({ organization }: { organization: Organization }) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Organizations', href: OrganizationController.index().url },
        { title: organization.name, href: OrganizationController.show(organization.id).url },
        { title: 'Edit', href: OrganizationController.edit(organization.id).url },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit — ${organization.name}`} />

            <div className="flex flex-col gap-6 p-6">
                <h1 className="text-2xl font-semibold">Edit organization</h1>

                <div className="max-w-lg rounded border bg-card p-6">
                    <Form
                        {...OrganizationController.update.form(organization.id)}
                        options={{ preserveScroll: true }}
                        className="space-y-5"
                    >
                        {({ errors, processing }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Name</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        defaultValue={organization.name}
                                        autoFocus
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="domain">Domain <span className="text-muted-foreground">(optional)</span></Label>
                                    <Input
                                        id="domain"
                                        name="domain"
                                        defaultValue={organization.domain ?? ''}
                                        placeholder="e.g. acme.com"
                                    />
                                    <InputError message={errors.domain} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="status">Status</Label>
                                    <Select name="status" defaultValue={organization.status}>
                                        <SelectTrigger id="status">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="active">Active</SelectItem>
                                            <SelectItem value="inactive">Inactive</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.status} />
                                </div>

                                <div className="flex items-center gap-3">
                                    <Button type="submit" disabled={processing}>
                                        {processing ? 'Saving…' : 'Save changes'}
                                    </Button>
                                    <Link
                                        href={OrganizationController.show(organization.id).url}
                                        className="text-sm text-muted-foreground hover:text-foreground"
                                    >
                                        Cancel
                                    </Link>
                                </div>
                            </>
                        )}
                    </Form>
                </div>
            </div>
        </AppLayout>
    );
}
