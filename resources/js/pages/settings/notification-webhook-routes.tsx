import { Head, router, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { index, store, destroy } from '@/routes/notification-webhook-routes';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Notification Webhooks', href: index().url },
];

type WebhookRoute = {
    id: number;
    category: string;
    platform: string;
    platform_label: string;
    webhook_url_masked: string;
    label: string | null;
};

type Category = {
    value: string;
    label: string;
    description: string;
};

type Platform = {
    value: string;
    label: string;
};

interface Props {
    routes: WebhookRoute[];
    categories: Category[];
    platforms: Platform[];
}

export default function NotificationWebhookRoutes({ routes, categories, platforms }: Props) {
    const routesByCategory = categories.map((cat) => ({
        ...cat,
        routes: routes.filter((r) => r.category === cat.value),
    }));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Notification Webhooks" />

            <div className="space-y-6 p-6">
                <div>
                    <h1 className="text-xl font-semibold">Notification Webhooks</h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Send notifications to Slack, Microsoft Teams, or Discord channels when accessibility events occur.
                    </p>
                </div>

                {routesByCategory.map((category) => (
                    <CategoryCard key={category.value} category={category} platforms={platforms} />
                ))}
            </div>
        </AppLayout>
    );
}

function CategoryCard({
    category,
    platforms,
}: {
    category: { value: string; label: string; description: string; routes: WebhookRoute[] };
    platforms: Platform[];
}) {
    const [open, setOpen] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        category: category.value,
        platform: '',
        webhook_url: '',
        label: '',
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post(store().url, {
            onSuccess: () => {
                reset('platform', 'webhook_url', 'label');
                setOpen(false);
            },
        });
    }

    function remove(id: number) {
        if (!confirm('Remove this webhook?')) return;
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
                    Add webhook
                </Button>
            </div>

            {open && (
                <form onSubmit={submit} className="border-b bg-muted/30 px-5 py-4">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                        <div className="w-full space-y-1 sm:w-40">
                            <Label htmlFor={`platform-${category.value}`} className="text-xs">
                                Platform
                            </Label>
                            <Select
                                value={data.platform}
                                onValueChange={(val) => setData('platform', val)}
                                required
                            >
                                <SelectTrigger id={`platform-${category.value}`}>
                                    <SelectValue placeholder="Select..." />
                                </SelectTrigger>
                                <SelectContent>
                                    {platforms.map((p) => (
                                        <SelectItem key={p.value} value={p.value}>
                                            {p.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.platform && <p className="text-destructive text-xs">{errors.platform}</p>}
                        </div>
                        <div className="flex-1 space-y-1">
                            <Label htmlFor={`url-${category.value}`} className="text-xs">
                                Webhook URL
                            </Label>
                            <Input
                                id={`url-${category.value}`}
                                type="url"
                                placeholder="https://hooks.slack.com/services/..."
                                value={data.webhook_url}
                                onChange={(e) => setData('webhook_url', e.target.value)}
                                required
                            />
                            {errors.webhook_url && <p className="text-destructive text-xs">{errors.webhook_url}</p>}
                        </div>
                        <div className="w-full space-y-1 sm:w-36">
                            <Label htmlFor={`label-${category.value}`} className="text-xs">
                                Label (optional)
                            </Label>
                            <Input
                                id={`label-${category.value}`}
                                placeholder="e.g. #alerts"
                                value={data.label}
                                onChange={(e) => setData('label', e.target.value)}
                            />
                        </div>
                        <div className="flex gap-2">
                            <Button type="submit" size="sm" disabled={processing}>
                                Save
                            </Button>
                            <Button type="button" size="sm" variant="ghost" onClick={() => { setOpen(false); reset('platform', 'webhook_url', 'label'); }}>
                                Cancel
                            </Button>
                        </div>
                    </div>
                </form>
            )}

            {category.routes.length === 0 ? (
                <p className="text-muted-foreground px-5 py-4 text-sm">No webhooks configured.</p>
            ) : (
                <div className="rounded-xl border">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b">
                            <th className="px-5 py-2 text-left text-xs font-medium">Platform</th>
                            <th className="px-5 py-2 text-left text-xs font-medium">URL</th>
                            <th className="px-5 py-2 text-left text-xs font-medium">Label</th>
                            <th className="px-5 py-2"><span className="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {category.routes.map((route) => (
                            <tr key={route.id} className="group">
                                <td className="px-5 py-3 text-xs font-medium">{route.platform_label}</td>
                                <td className="text-muted-foreground px-5 py-3 font-mono text-xs">{route.webhook_url_masked}</td>
                                <td className="text-muted-foreground px-5 py-3 text-xs">{route.label ?? '—'}</td>
                                <td className="px-5 py-3 text-right">
                                    <Button
                                        size="icon"
                                        variant="ghost"
                                        className="h-7 w-7 opacity-0 group-hover:opacity-100"
                                        onClick={() => remove(route.id)}
                                        aria-label="Remove webhook"
                                    >
                                        <Trash2 className="h-3.5 w-3.5 text-destructive" />
                                    </Button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                </div>
            )}
        </div>
    );
}
