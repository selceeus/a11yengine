import { ChartOptions } from 'chart.js';
import { useEffect, useState } from 'react';
import { Line } from 'react-chartjs-2';

import { Skeleton } from '@/components/ui/skeleton';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';

type SeriesPoint = {
    date: string;
    risk_score: number;
    open_issues: number;
};

type OrgSeries = {
    id: number;
    name: string;
    series: SeriesPoint[];
};

type TrendsData = {
    organizations: OrgSeries[];
    days: string[];
    generated_at: string;
};

export type OrgRiskTrendsChartProps = {
    agencyId: number;
    organizationId?: number;
};

type Window = 7 | 30 | 90;

const WINDOWS: { label: string; value: Window }[] = [
    { label: '7d', value: 7 },
    { label: '30d', value: 30 },
    { label: '90d', value: 90 },
];

const TABLEAU10 = [
    '#4e79a7', '#f28e2b', '#e15759', '#76b7b2', '#59a14f',
    '#edc948', '#b07aa1', '#ff9da7', '#9c755f', '#bab0ac',
];

export function OrgRiskTrendsChart({ agencyId, organizationId }: OrgRiskTrendsChartProps) {
    const [window, setWindow] = useState<Window>(30);
    const [data, setData] = useState<TrendsData | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);
        setError(null);

        const url = organizationId
            ? `/api/organizations/${organizationId}/risk-trends?days=${window}`
            : `/api/agencies/${agencyId}/organizations/risk-trends?days=${window}`;

        fetch(url, {
            signal: controller.signal,
            headers: { Accept: 'application/json' },
        })
            .then((res) => {
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                return res.json() as Promise<TrendsData>;
            })
            .then((json) => {
                setData(json);
                setLoading(false);
            })
            .catch((err: unknown) => {
                if (err instanceof Error && err.name !== 'AbortError') {
                    setError('Failed to load organisation risk trends.');
                    setLoading(false);
                }
            });

        return () => controller.abort();
    }, [agencyId, organizationId, window]);

    const noData = !loading && !error && (!data || data.organizations.length === 0);
    const orgs = data?.organizations ?? [];
    const allDates = orgs[0]?.series.map((p) => p.date) ?? [];

    const chartData = {
        labels: allDates.map((d) =>
            new Date(d + 'T00:00:00').toLocaleDateString(undefined, { month: 'short', day: 'numeric' }),
        ),
        datasets: orgs.map((org, i) => ({
            label: org.name,
            data: org.series.map((p) => p.risk_score),
            borderColor: TABLEAU10[i % TABLEAU10.length],
            backgroundColor: 'transparent',
            tension: 0.3,
            pointRadius: 3,
        })),
    };

    const options: ChartOptions<'line'> = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: true, position: 'bottom' },
            tooltip: {
                callbacks: {
                    label: (item) => {
                        const org = orgs[item.datasetIndex];
                        const pt = org?.series[item.dataIndex];
                        return `${org?.name ?? ''}: ${pt?.risk_score ?? 0} (${pt?.open_issues ?? 0} issues)`;
                    },
                },
            },
        },
        scales: {
            x: { grid: { display: false } },
            y: {
                min: 0,
                ticks: { color: 'var(--muted-foreground)' as string },
                grid: { color: 'rgba(0,0,0,0.06)' },
            },
        },
    };

    return (
        <div className="space-y-3">
            <div className="flex items-center justify-between">
                <ToggleGroup
                    type="single"
                    value={String(window)}
                    onValueChange={(v) => {
                        if (v) setWindow(Number(v) as Window);
                    }}
                    variant="outline"
                    size="sm"
                >
                    {WINDOWS.map((w) => (
                        <ToggleGroupItem key={w.value} value={String(w.value)} aria-label={`Last ${w.value} days`}>
                            {w.label}
                        </ToggleGroupItem>
                    ))}
                </ToggleGroup>
            </div>

            {loading && <Skeleton className="h-48 w-full rounded" />}
            {error && <p className="text-sm text-destructive">{error}</p>}
            {noData && <p className="text-sm text-muted-foreground">No organisation risk data yet.</p>}

            {!loading && !error && data && data.organizations.length > 0 && (
                <div className="h-55">
                    <Line
                        data={chartData}
                        options={options}
                        aria-label="Organisation risk trends – line chart"
                        role="img"
                    />
                </div>
            )}
        </div>
    );
}
