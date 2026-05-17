import { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Globe } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { index as integrationsIndex } from '@/routes/integrations';
import * as PropertyController from '@/actions/App/Http/Controllers/PropertyController';

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

function formatDate(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function statusVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (status) {
        case 'active': return 'default';
        case 'error': return 'destructive';
        default: return 'secondary';
    }
}

export default function IntegrationShow({ integration }: { integration: Integration }) {
    const [testing, setTesting] = useState(false);
    const [testResult, setTestResult] = useState<{ ok: boolean; message: string } | null>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Integrations', href: integrationsIndex().url },
        { title: integration.name, href: '#' },
    ];

    async function testConnection() {
        setTesting(true);
        setTestResult(null);

        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
        const res = await fetch(`/settings/integrations/${integration.id}/test`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
        });
        const json = await res.json();
        setTestResult({ ok: json.ok, message: json.message });
        setTesting(false);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={integration.name} />

            <div className="flex flex-col gap-6 p-6">
                {/* Header */}
                <div className="flex items-start justify-between gap-4">
                    <div className="flex items-start gap-4">
                        <Button variant="ghost" size="sm" asChild className="mt-0.5">
                            <Link href={integrationsIndex().url}>
                                <ArrowLeft className="mr-1.5 h-4 w-4" />
                                Back
                            </Link>
                        </Button>

                        <div>
                            <div className="flex items-center gap-3">
                                <h1 className="text-2xl font-semibold">{integration.name}</h1>
                                <Badge
                                    variant={statusVariant(integration.status)}
                                    className={integration.status === 'active' ? 'bg-green-600' : ''}
                                >
                                    {integration.status_label}
                                </Badge>
                            </div>
                            <p className="text-muted-foreground mt-1 text-sm">{integration.provider_label}</p>
                        </div>
                    </div>

                    <Button
                        variant="outline"
                        size="sm"
                        disabled={testing}
                        onClick={testConnection}
                    >
                        {testing ? 'Testing…' : 'Test Connection'}
                    </Button>
                </div>

                {testResult && (
                    <p className={`text-sm ${testResult.ok ? 'text-green-600' : 'text-destructive'}`}>
                        {testResult.message}
                    </p>
                )}

                <Separator />

                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Integration details */}
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle className="text-base">Details</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid grid-cols-2 gap-x-6 gap-y-4 text-sm">
                                <div>
                                    <dt className="text-muted-foreground">Provider</dt>
                                    <dd className="mt-0.5 font-medium">{integration.provider_label}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">Status</dt>
                                    <dd className="mt-0.5">
                                        <Badge
                                            variant={statusVariant(integration.status)}
                                            className={`text-xs ${integration.status === 'active' ? 'bg-green-600' : ''}`}
                                        >
                                            {integration.status_label}
                                        </Badge>
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">Last Synced</dt>
                                    <dd className="mt-0.5 font-medium">{formatDate(integration.last_synced_at)}</dd>
                                </div>
                                {integration.error_message && (
                                    <div className="col-span-2">
                                        <dt className="text-muted-foreground">Error</dt>
                                        <dd className="text-destructive mt-0.5 font-medium">{integration.error_message}</dd>
                                    </div>
                                )}
                            </dl>
                        </CardContent>
                    </Card>

                    {/* Property */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Property</CardTitle>
                        </CardHeader>
                        <CardContent className="text-sm">
                            {integration.property ? (
                                <Link
                                    href={PropertyController.show(integration.property.id).url}
                                    className="text-primary flex items-center gap-1.5 font-medium hover:underline"
                                >
                                    <Globe className="h-4 w-4 shrink-0" />
                                    {integration.property.name}
                                </Link>
                            ) : (
                                <p className="text-muted-foreground">All properties</p>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
