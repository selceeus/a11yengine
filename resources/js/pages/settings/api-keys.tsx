import { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import type { BreadcrumbItem } from '@/types';
import { index } from '@/routes/api-keys';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'API Keys', href: index().url },
];

type Scope = {
    value: string;
    label: string;
    description: string;
};

type ApiKey = {
    id: number;
    name: string;
    key_prefix: string;
    scopes: string[];
    is_active: boolean;
    last_used_at: string | null;
    expires_at: string | null;
    created_at: string | null;
    created_by: { id: number; name: string } | null;
};

interface Props {
    apiKeys: ApiKey[];
    availableScopes: Scope[];
    newToken: string | null;
}

function formatDate(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

export default function ApiKeysIndex({ apiKeys, availableScopes, newToken }: Props) {
    const [open, setOpen] = useState(false);
    const [tokenVisible, setTokenVisible] = useState<string | null>(newToken);

    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        scopes: [] as string[],
        expires_at: '',
    });

    function toggleScope(value: string) {
        setData('scopes', data.scopes.includes(value)
            ? data.scopes.filter((s) => s !== value)
            : [...data.scopes, value],
        );
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post(index().url, {
            onSuccess: () => {
                reset();
                setOpen(false);
            },
        });
    }

    function revoke(id: number) {
        if (!confirm('Revoke this API key? This cannot be undone.')) return;
        router.delete(`/settings/api-keys/${id}`);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="API Keys" />
            <h1 className="sr-only">API Keys Settings</h1>

            <SettingsLayout>
                <div className="space-y-6">
                    <div className="flex items-start justify-between">
                        <Heading
                            variant="small"
                            title="API Keys"
                            description="Manage API keys for CI/CD pipelines, MCP clients, and WordPress integrations"
                        />
                        <Dialog open={open} onOpenChange={setOpen}>
                            <DialogTrigger asChild>
                                <Button size="sm">New API Key</Button>
                            </DialogTrigger>
                            <DialogContent className="sm:max-w-lg">
                                <DialogHeader>
                                    <DialogTitle>Create API Key</DialogTitle>
                                    <DialogDescription>
                                        Choose a descriptive name and select the scopes this key will have access to.
                                    </DialogDescription>
                                </DialogHeader>
                                <form onSubmit={submit} className="space-y-4">
                                    <div className="space-y-1.5">
                                        <Label htmlFor="key-name">Name</Label>
                                        <Input
                                            id="key-name"
                                            value={data.name}
                                            onChange={(e) => setData('name', e.target.value)}
                                            placeholder="e.g. GitHub Actions CI"
                                            required
                                        />
                                        {errors.name && <p className="text-destructive text-xs">{errors.name}</p>}
                                    </div>

                                    <div className="space-y-2">
                                        <Label>Scopes</Label>
                                        <div className="space-y-2">
                                            {availableScopes.map((scope) => (
                                                <div key={scope.value} className="flex items-start gap-3">
                                                    <Checkbox
                                                        id={`scope-${scope.value}`}
                                                        checked={data.scopes.includes(scope.value)}
                                                        onCheckedChange={() => toggleScope(scope.value)}
                                                    />
                                                    <div>
                                                        <label
                                                            htmlFor={`scope-${scope.value}`}
                                                            className="cursor-pointer text-sm font-medium"
                                                        >
                                                            {scope.label}
                                                        </label>
                                                        <p className="text-muted-foreground text-xs">{scope.description}</p>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                        {errors.scopes && <p className="text-destructive text-xs">{errors.scopes}</p>}
                                    </div>

                                    <div className="space-y-1.5">
                                        <Label htmlFor="expires-at">Expiry Date (optional)</Label>
                                        <Input
                                            id="expires-at"
                                            type="date"
                                            value={data.expires_at}
                                            onChange={(e) => setData('expires_at', e.target.value)}
                                        />
                                        {errors.expires_at && <p className="text-destructive text-xs">{errors.expires_at}</p>}
                                    </div>

                                    <DialogFooter>
                                        <Button type="button" variant="outline" onClick={() => setOpen(false)}>
                                            Cancel
                                        </Button>
                                        <Button type="submit" disabled={processing}>
                                            Create Key
                                        </Button>
                                    </DialogFooter>
                                </form>
                            </DialogContent>
                        </Dialog>
                    </div>

                    {tokenVisible && (
                        <div className="bg-success/10 border-success/30 rounded-lg border p-4">
                            <p className="text-success mb-1 text-sm font-semibold">API key created — copy it now, it won&#39;t be shown again.</p>
                            <div className="flex items-center gap-2">
                                <code className="bg-background grow rounded border px-3 py-1.5 font-mono text-sm break-all">
                                    {tokenVisible}
                                </code>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => {
                                        navigator.clipboard.writeText(tokenVisible);
                                    }}
                                >
                                    Copy
                                </Button>
                                <Button size="sm" variant="ghost" onClick={() => setTokenVisible(null)}>
                                    Dismiss
                                </Button>
                            </div>
                        </div>
                    )}

                    {apiKeys.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No API keys yet. Create one to get started.</p>
                    ) : (
                        <div className="rounded-lg border">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b">
                                        <th className="px-4 py-3 text-left font-medium">Name</th>
                                        <th className="px-4 py-3 text-left font-medium">Prefix</th>
                                        <th className="px-4 py-3 text-left font-medium">Scopes</th>
                                        <th className="px-4 py-3 text-left font-medium">Last Used</th>
                                        <th className="px-4 py-3 text-left font-medium">Expires</th>
                                        <th className="px-4 py-3 text-left font-medium">Status</th>
                                        <th className="px-4 py-3"><span className="sr-only">Actions</span></th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {apiKeys.map((key) => (
                                        <tr key={key.id}>
                                            <td className="px-4 py-3 font-medium">{key.name}</td>
                                            <td className="px-4 py-3">
                                                <code className="text-muted-foreground font-mono text-xs">{key.key_prefix}</code>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex flex-wrap gap-1">
                                                    {key.scopes.map((scope) => (
                                                        <Badge key={scope} variant="secondary" className="text-xs">
                                                            {scope}
                                                        </Badge>
                                                    ))}
                                                </div>
                                            </td>
                                            <td className="text-muted-foreground px-4 py-3">
                                                {formatDate(key.last_used_at)}
                                            </td>
                                            <td className="text-muted-foreground px-4 py-3">
                                                {formatDate(key.expires_at)}
                                            </td>
                                            <td className="px-4 py-3">
                                                {key.is_active ? (
                                                    <Badge variant="default" className="bg-green-600 text-xs">Active</Badge>
                                                ) : (
                                                    <Badge variant="secondary" className="text-xs">Inactive</Badge>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                {key.is_active && (
                                                    <Button
                                                        size="sm"
                                                        variant="destructive"
                                                        onClick={() => revoke(key.id)}
                                                    >
                                                        Revoke
                                                    </Button>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
