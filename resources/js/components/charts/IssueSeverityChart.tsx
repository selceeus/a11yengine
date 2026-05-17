import { ChartOptions } from 'chart.js';
import { useEffect, useState } from 'react';
import { Bar } from 'react-chartjs-2';

import { Skeleton } from '@/components/ui/skeleton';

type Severity = 'critical' | 'high' | 'medium' | 'low';

type SummaryData = {
    critical: number;
    high: number;
    medium: number;
    low: number;
    total: number;
    generated_at: string;
};

export type IssueSeverityChartProps = {
    agencyId: number;
    organizationId?: number;
};

const SEVERITIES: Severity[] = ['critical', 'high', 'medium', 'low'];

const SEVERITY_COLORS: Record<Severity, string> = {
    critical: '#dc2626',
    high: '#ea580c',
    medium: '#ca8a04',
    low: '#2563eb',
};

const SEVERITY_LABELS: Record<Severity, string> = {
    critical: 'Critical',
    high: 'High',
    medium: 'Medium',
    low: 'Low',
};

export function IssueSeverityChart({ agencyId, organizationId }: IssueSeverityChartProps) {
    const [data, setData] = useState<SummaryData | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);
        setError(null);

        const url = organizationId
            ? `/api/organizations/${organizationId}/issues/summary`
            : `/api/agencies/${agencyId}/issues/summary`;

        fetch(url, {
            signal: controller.signal,
            headers: { Accept: 'application/json' },
        })
            .then((res) => {
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                return res.json() as Promise<SummaryData>;
            })
            .then((json) => {
                setData(json);
                setLoading(false);
            })
            .catch((err: unknown) => {
                if (err instanceof Error && err.name !== 'AbortError') {
                    setError('Failed to load issue summary.');
                    setLoading(false);
                }
            });

        return () => controller.abort();
    }, [agencyId, organizationId]);

    if (loading) return <Skeleton className="h-30 w-full rounded" />;
    if (error) return <p className="text-sm text-destructive">{error}</p>;
    if (!data || data.total === 0) return <p className="text-sm text-muted-foreground">No active issues found.</p>;

    const chartData = {
        labels: ['Issues'],
        datasets: SEVERITIES.map((s) => ({
            label: SEVERITY_LABELS[s],
            data: [data[s]],
            backgroundColor: SEVERITY_COLORS[s],
        })),
    };

    const options: ChartOptions<'bar'> = {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: (item) =>
                        `${item.dataset.label}: ${item.parsed.x} (${Math.round((item.parsed.x / data.total) * 100)}%)`,
                },
            },
        },
        scales: {
            x: { stacked: true, display: false },
            y: { stacked: true, display: false },
        },
    };

    return (
        <div className="w-full">
            <div
                className="h-11 w-full overflow-hidden rounded-sm"
                role="img"
                aria-label="Issues by severity – horizontal stacked bar chart"
            >
                <Bar data={chartData} options={options} />
            </div>

            <div className="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2">
                {SEVERITIES.map((s) => (
                    <div key={s} className="flex items-center gap-1.5 text-sm">
                        <span
                            aria-hidden="true"
                            className="inline-block h-2.5 w-2.5 shrink-0 rounded-sm"
                            style={{ backgroundColor: SEVERITY_COLORS[s] }}
                        />
                        <span className="text-muted-foreground">{SEVERITY_LABELS[s]}</span>
                        <span className="font-medium tabular-nums">{data[s]}</span>
                    </div>
                ))}
                <div className="ml-auto text-xs text-muted-foreground">
                    Total: <span className="font-medium tabular-nums">{data.total}</span>
                </div>
            </div>
        </div>
    );
}
