import { Form, Head, Link } from '@inertiajs/react';
import * as PropertyController from '@/actions/App/Http/Controllers/PropertyController';
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
};

type Industry = {
    value: string;
    label: string;
    risk: 'high' | 'medium' | 'low';
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Properties', href: PropertyController.index().url },
    { title: 'Add property', href: PropertyController.create().url },
];

export default function Create({ organizations, industries }: { organizations: Organization[]; industries: Industry[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Add property" />

            <div className="flex flex-col gap-6 p-6">
                <h1 className="text-2xl font-semibold">Add property</h1>

                <div className="max-w-lg rounded border bg-card p-6">
                    <Form
                        {...PropertyController.store.form()}
                        className="space-y-5"
                    >
                        {({ errors, processing }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="organization_id">Organization</Label>
                                    <Select name="organization_id">
                                        <SelectTrigger id="organization_id">
                                            <SelectValue placeholder="Select an organization…" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {organizations.map((org) => (
                                                <SelectItem key={org.id} value={String(org.id)}>
                                                    {org.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.organization_id} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="name">Property name</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        placeholder="e.g. Main website"
                                        autoFocus
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="base_url">Base URL</Label>
                                    <Input
                                        id="base_url"
                                        name="base_url"
                                        type="url"
                                        placeholder="https://example.com"
                                    />
                                    <InputError message={errors.base_url} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="industry">Industry</Label>
                                    <Select name="industry">
                                        <SelectTrigger id="industry">
                                            <SelectValue placeholder="Select an industry…" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {industries.map((ind) => (
                                                <SelectItem key={ind.value} value={ind.value}>
                                                    {ind.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <p className="text-xs text-muted-foreground">
                                        Used to assess legal risk based on ADA lawsuit data for your sector.
                                    </p>
                                    <InputError message={errors.industry} />
                                </div>

                                <div className="flex items-center gap-3">
                                    <Button type="submit" disabled={processing}>
                                        {processing ? 'Saving…' : 'Add property'}
                                    </Button>
                                    <Link
                                        href={PropertyController.index().url}
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
