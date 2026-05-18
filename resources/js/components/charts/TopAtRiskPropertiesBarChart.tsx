import { ActiveElement, ChartEvent, ChartOptions } from 'chart.js';
import { useEffect, useState } from 'react';
import { Bar } from 'react-chartjs-2';

import { Skeleton } from '@/components/ui/skeleton';
import { router } from '@inertiajs/react';

type Severity = 'critical' | 'high' | 'medium' | 'low';

type PropertyItem = {
    id: number;
    name: string;
    organization_name: string | null;
    risk_score: number;
    open_issue_count: number;
    highest_severity: Severity | null;
};

type TopRiskData = {
    properties: PropertyItem[];
    generated_at: string;
};

const SEVERITY_COLORS: Record<Severity, string> = {
    critical: '#ef4444',
    high: '#f97316',
    medium: '#eab308',
    low: '#22c55e',
};

export type TopAtRiskPropertiesBarChartProps = {
    agencyId: number;
    organizationId?: number;
};

export function TopAtRiskPropertiesBarChart({ agencyId, organizationId }: TopAtRiskPropertiesBarChartProps) {
    const [data, setData] = useState<TopRiskData | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);
        setError(null);

        const url = organizationId
            ? `/api/organizations/${organizationId}/properties/top-risk`
            : `/api/agencies/${agencyId}/properties/top-risk`;

        fetch(url, {
            signal: controller.signal,
            headers: { Accept: 'application/json' },
        })
            .then((res) => {
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                return res.json() as Promise<TopRiskData>;
            })
            .then((json) => {
                setData(json);
                setLoading(false);
            })
            .catch((err: unknown) => {
                if (err instanceof Error && err.name !== 'AbortError') {
                    setError('Failed to load top at-risk properties.');
                    setLoading(false);
                }
            });

        return () => controller.abort();
    }, [agencyId, organizationId]);

    const noData = !loading && !error && (!data || data.properties.length === 0);

    if (loading) return <Skeleton className="h-64 w-full rounded" />;
    if (error) return <p className="text-sm text-destructive">{error}</p>;
    if (noData) return <p className="text-sm text-muted-foreground">No at-risk properties found.</p>;

    const items = [...(data?.properties ?? [])].sort((a, b) => b.risk_score - a.risk_score);
    const chartHeight = Math.min(280, Math.max(200, items.length * 40));

    const chartData = {
        labels: items.map((p) => (p.name.length > 22 ? p.name.slice(0, 20) + '\u2026' : p.name)),
        datasets: [
            {
                label: 'Risk score',
                data: items.map((p) => p.risk_score),
                backgroundColor: items.map((p) =>
                    p.highest_severity ? SEVERITY_COLORS[p.highest_severity] : '#6b7280',
                ),
                borderRadius: 4,
            },
        ],
    };

    const options: ChartOptions<'bar'> = {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: (item) => {
                        const p = items[item.dataIndex];
                        return `Risk: ${p.risk_score}  |  ${p.open_issue_count} issues`;
                    },
                },
            },
        },
        scales: {
            x: { min: 0, max: 100, grid: { color: 'rgba(0,0,0,0.06)' } },
            y: { grid: { display: false } },
        },
        onClick: (_event: ChartEvent, elements: ActiveElement[]) => {
            if (elements.length > 0) {
                router.visit(`/properties/${items[elements[0].index].id}`);
            }
        },
    };

    return (
        <div className="w-full" style={{ height: chartHeight }}>
            <Bar
                data={chartData}
                options={options}
                aria-label="Top at-risk properties – horizontal bar chart"
                role="img"
                style={{ cursor: 'pointer' }}
            />
        </div>
    );
}
