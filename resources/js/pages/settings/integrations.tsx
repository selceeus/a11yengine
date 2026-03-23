import { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import type { BreadcrumbItem } from '@/types';
import { index } from '@/routes/integrations';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Integrations', href: index().url },
];

type CredentialField = {
    key: string;
    label: string;
    type: string;
    required: boolean;
};

type Provider = {
    value: string;
    label: string;
    is_implemented: boolean;
    credential_fields: CredentialField[];
    supports_webhooks: boolean;
};

type Integration = {
    id: number;
    provider: string;
    provider_label: string;
    name: string;
    status: string;
    status_label: string;
    error_message: string | null;
    last_synced_at: string | null;
    property: { id: number; name: string } | null;
};

interface Props {
    integrations: Integration[];
    providers: Provider[];
}

function formatDate(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

export default function IntegrationsIndex({ integrations, providers }: Props) {
    const [connectingProvider, setConnectingProvider] = useState<Provider | null>(null);
    const [testingId, setTestingId] = useState<number | null>(null);
    const [testResult, setTestResult] = useState<{ id: number; ok: boolean; message: string } | null>(null);

    const { data, setData, post, processing, errors, reset } = useForm<{
        provider: string;
        name: string;
        credentials: Record<string, string>;
        property_id: string;
    }>({
        provider: '',
        name: '',
        credentials: {},
        property_id: '',
    });

    const implementedProviders = providers.filter((p) => p.is_implemented);
    const comingSoonProviders = providers.filter((p) => !p.is_implemented);

    function openConnect(provider: Provider) {
        setConnectingProvider(provider);
        setData({
            provider: provider.value,
            name: provider.label,
            credentials: {},
            property_id: '',
        });
    }

    function setCredential(key: string, value: string) {
        setData('credentials', { ...data.credentials, [key]: value });
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post(index().url, {
            onSuccess: () => {
                reset();
                setConnectingProvider(null);
            },
        });
    }

    async function testConnection(integration: Integration) {
        setTestingId(integration.id);
        setTestResult(null);

        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
        const res = await fetch(`/settings/integrations/${integration.id}/test`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
        });
        const json = await res.json();
        setTestResult({ id: integration.id, ok: json.ok, message: json.message });
        setTestingId(null);
    }

    function remove(id: number) {
        if (!confirm('Remove this integration? Issue links will also be deleted.')) return;
        router.delete(`/settings/integrations/${id}`);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Integrations" />
            <h1 className="sr-only">Integrations Settings</h1>

            <SettingsLayout>
                <div className="space-y-8">
                    <Heading
                        variant="small"
                        title="Integrations"
                        description="Connect accessibility issues to your project management tools"
                    />

                    {/* Connected integrations */}
                    {integrations.length > 0 && (
                        <div className="space-y-3">
                            <h2 className="text-sm font-semibold">Connected</h2>
                            <div className="rounded-lg border">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b">
                                            <th className="px-4 py-3 text-left font-medium">Name</th>
                                            <th className="px-4 py-3 text-left font-medium">Provider</th>
                                            <th className="px-4 py-3 text-left font-medium">Property</th>
                                            <th className="px-4 py-3 text-left font-medium">Status</th>
                                            <th className="px-4 py-3 text-left font-medium">Last Synced</th>
                                            <th className="px-4 py-3"><span className="sr-only">Actions</span></th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {integrations.map((integration) => (
                                            <tr key={integration.id}>
                                                <td className="px-4 py-3 font-medium">{integration.name}</td>
                                                <td className="px-4 py-3">{integration.provider_label}</td>
                                                <td className="text-muted-foreground px-4 py-3">
                                                    {integration.property?.name ?? 'All properties'}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div>
                                                        <Badge
                                                            variant={integration.status === 'active' ? 'default' : integration.status === 'error' ? 'destructive' : 'secondary'}
                                                            className={`text-xs ${integration.status === 'active' ? 'bg-green-600' : ''}`}
                                                        >
                                                            {integration.status_label}
                                                        </Badge>
                                                        {testResult?.id === integration.id && (
                                                            <p className={`mt-1 text-xs ${testResult.ok ? 'text-green-600' : 'text-destructive'}`}>
                                                                {testResult.message}
                                                            </p>
                                                        )}
                                                        {integration.error_message && (
                                                            <p className="text-destructive mt-1 text-xs">{integration.error_message}</p>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="text-muted-foreground px-4 py-3">
                                                    {formatDate(integration.last_synced_at)}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center gap-2">
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            disabled={testingId === integration.id}
                                                            onClick={() => testConnection(integration)}
                                                        >
                                                            {testingId === integration.id ? 'Testing…' : 'Test'}
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            variant="destructive"
                                                            onClick={() => remove(integration.id)}
                                                        >
                                                            Remove
                                                        </Button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}

                    {/* Available providers */}
                    <div className="space-y-3">
                        <h2 className="text-sm font-semibold">Available</h2>
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            {implementedProviders.map((provider) => (
                                <div
                                    key={provider.value}
                                    className="rounded-lg border p-4"
                                >
                                    <p className="font-medium">{provider.label}</p>
                                    {provider.supports_webhooks && (
                                        <p className="text-muted-foreground mt-0.5 text-xs">Supports webhooks</p>
                                    )}
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        className="mt-3 w-full"
                                        onClick={() => openConnect(provider)}
                                    >
                                        Connect
                                    </Button>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Coming soon providers */}
                    {comingSoonProviders.length > 0 && (
                        <div className="space-y-3">
                            <h2 className="text-sm font-semibold">Coming Soon</h2>
                            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                {comingSoonProviders.map((provider) => (
                                    <div
                                        key={provider.value}
                                        className="rounded-lg border border-dashed p-4 opacity-50"
                                    >
                                        <p className="font-medium">{provider.label}</p>
                                        <p className="text-muted-foreground mt-0.5 text-xs">Coming soon</p>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </SettingsLayout>

            {/* Connect dialog */}
            <Dialog open={connectingProvider !== null} onOpenChange={(open) => { if (!open) setConnectingProvider(null); }}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Connect {connectingProvider?.label}</DialogTitle>
                        <DialogDescription>
                            Enter your credentials to connect this integration.
                        </DialogDescription>
                    </DialogHeader>
                    {connectingProvider && (
                        <form onSubmit={submit} className="space-y-4">
                            <div className="space-y-1.5">
                                <Label htmlFor="integration-name">Integration Name</Label>
                                <Input
                                    id="integration-name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    required
                                />
                                {errors.name && <p className="text-destructive text-xs">{errors.name}</p>}
                            </div>

                            {connectingProvider.credential_fields.map((field) => (
                                <div key={field.key} className="space-y-1.5">
                                    <Label htmlFor={`cred-${field.key}`}>
                                        {field.label}
                                        {!field.required && <span className="text-muted-foreground ml-1 text-xs">(optional)</span>}
                                    </Label>
                                    <Input
                                        id={`cred-${field.key}`}
                                        type={field.type === 'password' ? 'password' : field.type === 'email' ? 'email' : field.type === 'url' ? 'url' : 'text'}
                                        value={data.credentials[field.key] ?? ''}
                                        onChange={(e) => setCredential(field.key, e.target.value)}
                                        required={field.required}
                                    />
                                </div>
                            ))}

                            {errors.credentials && (
                                <p className="text-destructive text-xs">{errors.credentials as unknown as string}</p>
                            )}

                            <DialogFooter>
                                <Button type="button" variant="outline" onClick={() => setConnectingProvider(null)}>
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    Connect
                                </Button>
                            </DialogFooter>
                        </form>
                    )}
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
