import { Form, Head, Link } from '@inertiajs/react';
import * as OrganizationController from '@/actions/App/Http/Controllers/OrganizationController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Organizations', href: OrganizationController.index().url },
    { title: 'Add organization', href: OrganizationController.create().url },
];

export default function Create() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Add organization" />

            <div className="flex flex-col gap-6 p-6">
                <h1 className="text-2xl font-semibold">Add organization</h1>

                <div className="max-w-lg rounded border bg-card p-6">
                    <Form
                        {...OrganizationController.store.form()}
                        className="space-y-5"
                    >
                        {({ errors, processing }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Name</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        placeholder="e.g. Acme Corp"
                                        autoFocus
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="domain">Domain <span className="text-muted-foreground">(optional)</span></Label>
                                    <Input
                                        id="domain"
                                        name="domain"
                                        placeholder="e.g. acme.com"
                                    />
                                    <InputError message={errors.domain} />
                                </div>

                                <div className="flex items-center gap-3">
                                    <Button type="submit" disabled={processing}>
                                        {processing ? 'Saving…' : 'Add organization'}
                                    </Button>
                                    <Link
                                        href={OrganizationController.index().url}
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
