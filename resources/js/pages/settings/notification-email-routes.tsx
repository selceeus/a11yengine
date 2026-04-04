import { Head, router, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { index, store, destroy } from '@/routes/notification-email-routes';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Notification Emails', href: index().url },
];

type EmailRoute = {
    id: number;
    category: string;
    email: string;
    label: string | null;
};

type Category = {
    value: string;
    label: string;
    description: string;
};

interface Props {
    routes: EmailRoute[];
    categories: Category[];
}

export default function NotificationEmailRoutes({ routes, categories }: Props) {
    const routesByCategory = categories.map((cat) => ({
        ...cat,
        routes: routes.filter((r) => r.category === cat.value),
    }));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Notification Emails" />

            <div className="space-y-6 p-6">
                <div>
                    <h1 className="text-xl font-semibold">Notification Emails</h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Route notifications to team inboxes or client addresses — independent of personal notification preferences.
                    </p>
                </div>

                {routesByCategory.map((category) => (
                    <CategoryCard key={category.value} category={category} />
                ))}
            </div>
        </AppLayout>
    );
}

function CategoryCard({ category }: { category: { value: string; label: string; description: string; routes: EmailRoute[] } }) {
    const [open, setOpen] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        category: category.value,
        email: '',
        label: '',
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post(store().url, {
            onSuccess: () => {
                reset('email', 'label');
                setOpen(false);
            },
        });
    }

    function remove(id: number) {
        if (!confirm('Remove this email address?')) return;
        router.delete(destroy(id).url);
    }

    return (
        <div className="rounded-lg border">
            <div className="flex items-start justify-between border-b px-5 py-4">
                <div>
                    <h2 className="text-sm font-semibold">{category.label}</h2>
                    <p className="text-muted-foreground mt-0.5 text-xs">{category.description}</p>
                </div>
                <Button size="sm" variant="outline" onClick={() => setOpen(!open)}>
                    <Plus className="mr-1.5 h-3.5 w-3.5" />
                    Add email
                </Button>
            </div>

            {open && (
                <form onSubmit={submit} className="border-b bg-muted/30 px-5 py-4">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                        <div className="flex-1 space-y-1">
                            <Label htmlFor={`email-${category.value}`} className="text-xs">
                                Email address
                            </Label>
                            <Input
                                id={`email-${category.value}`}
                                type="email"
                                placeholder="team@example.com"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                required
                            />
                            {errors.email && <p className="text-destructive text-xs">{errors.email}</p>}
                        </div>
                        <div className="w-full space-y-1 sm:w-40">
                            <Label htmlFor={`label-${category.value}`} className="text-xs">
                                Label (optional)
                            </Label>
                            <Input
                                id={`label-${category.value}`}
                                placeholder="e.g. Dev team"
                                value={data.label}
                                onChange={(e) => setData('label', e.target.value)}
                            />
                        </div>
                        <div className="flex gap-2">
                            <Button type="submit" size="sm" disabled={processing}>
                                Save
                            </Button>
                            <Button type="button" size="sm" variant="ghost" onClick={() => { setOpen(false); reset('email', 'label'); }}>
                                Cancel
                            </Button>
                        </div>
                    </div>
                </form>
            )}

            {category.routes.length === 0 ? (
                <p className="text-muted-foreground px-5 py-4 text-sm">No email addresses configured.</p>
            ) : (
                <table className="w-full text-sm">
                    <tbody className="divide-y">
                        {category.routes.map((route) => (
                            <tr key={route.id} className="group">
                                <td className="px-5 py-3 font-mono text-xs">{route.email}</td>
                                <td className="text-muted-foreground px-5 py-3 text-xs">{route.label ?? '—'}</td>
                                <td className="px-5 py-3 text-right">
                                    <Button
                                        size="icon"
                                        variant="ghost"
                                        className="h-7 w-7 opacity-0 group-hover:opacity-100"
                                        onClick={() => remove(route.id)}
                                        aria-label="Remove email"
                                    >
                                        <Trash2 className="h-3.5 w-3.5 text-destructive" />
                                    </Button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
        </div>
    );
}
