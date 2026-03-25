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

type Property = {
    id: number;
    name: string;
    base_url: string;
    industry: string | null;
    status: string;
    organization: Organization | null;
};

export default function Edit({
    property,
    organizations,
    industries,
}: {
    property: Property;
    organizations: Organization[];
    industries: Industry[];
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Properties', href: PropertyController.index().url },
        { title: property.name, href: PropertyController.show(property.id).url },
        { title: 'Edit', href: PropertyController.edit(property.id).url },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit — ${property.name}`} />

            <div className="flex flex-col gap-6 p-6">
                <h1 className="text-xl font-semibold">Edit property</h1>

                <div className="max-w-lg rounded-xl border bg-card p-6">
                    <Form
                        {...PropertyController.update.form(property.id)}
                        options={{ preserveScroll: true }}
                        className="space-y-5"
                    >
                        {({ errors, processing }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Property name</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        defaultValue={property.name}
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
                                        defaultValue={property.base_url}
                                    />
                                    <InputError message={errors.base_url} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="industry">Industry</Label>
                                    <Select name="industry" defaultValue={property.industry ?? undefined}>
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
                                        {processing ? 'Saving…' : 'Save changes'}
                                    </Button>
                                    <Link
                                        href={PropertyController.show(property.id).url}
                                        className="text-sm text-muted-foreground hover:text-foreground"
                                    >
                                        Cancel
                                    </Link>
                                </div>
                            </>
                        )}
                    </Form>
                </div>

                {/* Organization info (read-only — changing org would require re-scoping data) */}
                {property.organization && (
                    <p className="max-w-lg text-xs text-muted-foreground">
                        Organization: <span className="font-medium">{property.organization.name}</span>.
                        To move a property to a different organization, delete it and recreate it.
                    </p>
                )}
            </div>
        </AppLayout>
    );
}
