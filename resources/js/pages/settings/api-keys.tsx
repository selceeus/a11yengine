import { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
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

type ExpiryStatus = 'expired' | 'expiring-soon' | 'active' | null;

function getExpiryStatus(key: ApiKey): ExpiryStatus {
    if (!key.expires_at) return null;
    const now = Date.now();
    const expiry = new Date(key.expires_at).getTime();
    if (expiry <= now) return 'expired';
    if (expiry <= now + 30 * 24 * 60 * 60 * 1000) return 'expiring-soon';
    return 'active';
}

export default function ApiKeysIndex({ apiKeys, availableScopes, newToken }: Props) {
    const [open, setOpen] = useState(false);
    const [tokenVisible, setTokenVisible] = useState<string | null>(newToken);

    const expiringKeys = apiKeys.filter((k) => k.is_active && getExpiryStatus(k) === 'expiring-soon');
    const expiredActiveKeys = apiKeys.filter((k) => k.is_active && getExpiryStatus(k) === 'expired');

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

            <div className="space-y-6 p-6">
                <div className="flex items-start justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">API Keys</h1>
                        <p className="text-muted-foreground mt-1 text-sm">Manage API keys for CI/CD pipelines, MCP clients, and WordPress integrations</p>
                    </div>
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
                        <div className="bg-success/10 border-success/30 rounded border p-4">
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

                    {(expiringKeys.length > 0 || expiredActiveKeys.length > 0) && (
                        <div className="rounded border border-amber-200 bg-amber-50 p-4 dark:border-amber-900/50 dark:bg-amber-950/20">
                            <p className="text-sm font-medium text-amber-800 dark:text-amber-400">
                                {expiredActiveKeys.length > 0 && (
                                    <span>
                                        {expiredActiveKeys.length} key{expiredActiveKeys.length > 1 ? 's have' : ' has'} expired and should be revoked.{' '}
                                    </span>
                                )}
                                {expiringKeys.length > 0 && (
                                    <span>
                                        {expiringKeys.length} key{expiringKeys.length > 1 ? 's are' : ' is'} expiring within 30 days.
                                    </span>
                                )}
                            </p>
                        </div>
                    )}

                    {apiKeys.length === 0 ? (
                        <p className="text-muted-foreground text-sm">No API keys yet. Create one to get started.</p>
                    ) : (
                        <div className="rounded border">
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
                                            <td className="px-4 py-3">
                                                {key.expires_at ? (
                                                    <div className="flex flex-col gap-1">
                                                        <span className="text-muted-foreground">{formatDate(key.expires_at)}</span>
                                                        {getExpiryStatus(key) === 'expired' && (
                                                            <Badge variant="destructive" className="w-fit text-xs">Expired</Badge>
                                                        )}
                                                        {getExpiryStatus(key) === 'expiring-soon' && (
                                                            <Badge variant="outline" className="w-fit border-amber-400 text-xs text-amber-700 dark:text-amber-400">
                                                                Expiring soon
                                                            </Badge>
                                                        )}
                                                    </div>
                                                ) : (
                                                    <span className="text-muted-foreground">—</span>
                                                )}
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

                    {/* WordPress Plugin Integration Guide */}
                    <div className="rounded border">
                        <div className="border-b bg-muted/30 px-4 py-3">
                            <h2 className="text-sm font-semibold">WordPress Plugin Integration</h2>
                            <p className="mt-0.5 text-xs text-muted-foreground">
                                Use an API key with the <code className="rounded bg-muted px-1 py-0.5 font-mono">wordpress</code> scope to connect the plugin to your properties.
                            </p>
                        </div>
                        <div className="space-y-4 p-4">
                            <p className="text-sm text-muted-foreground">
                                Authenticate all requests using an <code className="rounded bg-muted px-1 py-0.5 font-mono text-xs">Authorization</code> header:
                            </p>
                            <pre className="overflow-x-auto rounded bg-muted px-4 py-3 font-mono text-xs">
                                {`Authorization: Bearer <your-api-key>`}
                            </pre>

                            <div className="divide-y rounded border text-sm">
                                <div className="grid grid-cols-[auto_1fr] gap-x-4 px-4 py-3">
                                    <span className="shrink-0 rounded bg-blue-100 px-2 py-0.5 font-mono text-xs text-blue-700 dark:bg-blue-950 dark:text-blue-300">GET</span>
                                    <div>
                                        <code className="font-mono text-xs">/api/wordpress/properties</code>
                                        <p className="mt-0.5 text-xs text-muted-foreground">List all properties accessible to this agency.</p>
                                    </div>
                                </div>
                                <div className="grid grid-cols-[auto_1fr] gap-x-4 px-4 py-3">
                                    <span className="shrink-0 rounded bg-blue-100 px-2 py-0.5 font-mono text-xs text-blue-700 dark:bg-blue-950 dark:text-blue-300">GET</span>
                                    <div>
                                        <code className="font-mono text-xs">/api/wordpress/properties/{'{propertySlug}'}/issues</code>
                                        <p className="mt-0.5 text-xs text-muted-foreground">Retrieve open accessibility issues for a property.</p>
                                    </div>
                                </div>
                                <div className="grid grid-cols-[auto_1fr] gap-x-4 px-4 py-3">
                                    <span className="shrink-0 rounded bg-blue-100 px-2 py-0.5 font-mono text-xs text-blue-700 dark:bg-blue-950 dark:text-blue-300">GET</span>
                                    <div>
                                        <code className="font-mono text-xs">/api/wordpress/properties/{'{propertySlug}'}/risk-summary</code>
                                        <p className="mt-0.5 text-xs text-muted-foreground">Get the latest risk advisory summary for a property.</p>
                                    </div>
                                </div>
                                <div className="grid grid-cols-[auto_1fr] gap-x-4 px-4 py-3">
                                    <span className="shrink-0 rounded bg-green-100 px-2 py-0.5 font-mono text-xs text-green-700 dark:bg-green-950 dark:text-green-300">POST</span>
                                    <div>
                                        <code className="font-mono text-xs">/api/wordpress/properties/{'{propertySlug}'}/scans</code>
                                        <p className="mt-0.5 text-xs text-muted-foreground">Trigger a new accessibility scan for a property.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
            </div>
        </AppLayout>
    );
}
