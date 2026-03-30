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

type TrendsData = {
    series: SeriesPoint[];
    days: string[];
    generated_at: string;
};

type Window = 7 | 30 | 90;

const WINDOWS: { label: string; value: Window }[] = [
    { label: '7d', value: 7 },
    { label: '30d', value: 30 },
    { label: '90d', value: 90 },
];

export function PropertyRiskTrendsChart({ propertyId }: { propertyId: number }) {
    const [window, setWindow] = useState<Window>(30);
    const [data, setData] = useState<TrendsData | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);
        setError(null);

        fetch(`/api/properties/${propertyId}/risk-trends?days=${window}`, {
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
                    setError('Failed to load property risk trends.');
                    setLoading(false);
                }
            });

        return () => controller.abort();
    }, [propertyId, window]);

    const noData = !loading && !error && (!data || data.series.every((p) => p.risk_score === 0));

    const series = data?.series ?? [];

    const chartData = {
        labels: series.map((p) =>
            new Date(p.date + 'T00:00:00').toLocaleDateString(undefined, { month: 'short', day: 'numeric' }),
        ),
        datasets: [
            {
                label: 'Risk score',
                data: series.map((p) => p.risk_score),
                borderColor: '#7c3aed',
                backgroundColor: 'rgba(124,58,237,0.08)',
                fill: true,
                tension: 0.3,
                pointRadius: 3,
                pointBackgroundColor: '#7c3aed',
                pointBorderColor: 'white',
                pointBorderWidth: 2,
            },
        ],
    };

    const options: ChartOptions<'line'> = {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: (item) => {
                        const pt = series[item.dataIndex];
                        return [`Risk score: ${pt.risk_score}`, `Open issues: ${pt.open_issues}`];
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
            <div className="flex items-center justify-end">
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

            {loading && <Skeleton className="h-48 w-full rounded-xl" />}
            {error && <p className="text-sm text-destructive">{error}</p>}
            {noData && <p className="text-sm text-muted-foreground">No risk trend data yet.</p>}

            {!loading && !error && data && !noData && (
                <Line
                    data={chartData}
                    options={options}
                    aria-label="Property risk trend"
                    role="img"
                />
            )}
        </div>
    );
}
