import { ChartOptions } from 'chart.js';
import { useEffect, useState } from 'react';
import { Line } from 'react-chartjs-2';

import { Skeleton } from '@/components/ui/skeleton';

type HistoryPoint = {
    id: number;
    title: string;
    overall_score: number | null;
    generated_at: string;
};

type TrendData = {
    history: HistoryPoint[];
    audit_count: number;
    previous_score: number | null;
    score_delta: number | null;
    trend_direction: 'improving' | 'declining' | 'stable';
    days: number;
    generated_at: string;
};

export type AuditScoreTrendChartProps = {
    propertyId: number;
    window?: 7 | 30 | 90;
};

export function AuditScoreTrendChart({ propertyId, window: initialWindow = 30 }: AuditScoreTrendChartProps) {
    const [days, setDays] = useState<7 | 30 | 90>(initialWindow);
    const [data, setData] = useState<TrendData | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);
        setError(null);

        fetch(`/api/properties/${propertyId}/audits/trend?days=${days}`, {
            signal: controller.signal,
            headers: { Accept: 'application/json' },
        })
            .then((res) => {
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                return res.json() as Promise<TrendData>;
            })
            .then((json) => {
                setData(json);
                setLoading(false);
            })
            .catch((err: unknown) => {
                if (err instanceof Error && err.name !== 'AbortError') {
                    setError('Failed to load audit trend data.');
                    setLoading(false);
                }
            });

        return () => controller.abort();
    }, [propertyId, days]);

    if (loading) return <Skeleton className="h-48 w-full rounded-xl" />;
    if (error) return <p className="text-sm text-destructive">{error}</p>;

    const parsed = (data?.history ?? [])
        .filter((d) => d.overall_score !== null)
        .map((d) => ({ ...d, score: d.overall_score as number }));

    const hasEnoughData = parsed.length >= 2;

    const chartData = {
        labels: parsed.map((p) =>
            new Date(p.generated_at).toLocaleDateString(undefined, { month: 'short', day: 'numeric' }),
        ),
        datasets: [
            {
                label: 'Score',
                data: parsed.map((p) => p.score),
                borderColor: '#7c3aed',
                backgroundColor: 'rgba(124,58,237,0.1)',
                fill: true,
                tension: 0.3,
                pointRadius: 5,
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
                    title: (items) => parsed[items[0].dataIndex]?.title ?? '',
                    label: (item) => `Score: ${item.parsed.y}/100`,
                    afterLabel: (item) =>
                        new Date(parsed[item.dataIndex].generated_at).toLocaleDateString(undefined, {
                            month: 'short',
                            day: 'numeric',
                            year: 'numeric',
                        }),
                },
            },
        },
        scales: {
            x: { grid: { display: false } },
            y: {
                min: 0,
                max: 100,
                ticks: { color: 'var(--muted-foreground)' as string },
                grid: { color: 'rgba(0,0,0,0.06)' },
            },
        },
    };

    return (
        <div className="w-full">
            <div className="mb-3 flex gap-1">
                {([7, 30, 90] as const).map((d) => (
                    <button
                        key={d}
                        onClick={() => setDays(d)}
                        className={`rounded px-2 py-0.5 text-xs font-medium transition-colors ${
                            days === d
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        {d}d
                    </button>
                ))}
            </div>

            {!hasEnoughData ? (
                <p className="py-10 text-center text-sm text-muted-foreground">Not enough audit history to show a trend.</p>
            ) : (
                <>
                    <Line
                        data={chartData}
                        options={options}
                        aria-label="Audit accessibility score trend – line chart"
                        role="img"
                    />
                    <p className="mt-1 text-right text-xs text-muted-foreground">
                        {data?.audit_count ?? 0} audit{(data?.audit_count ?? 0) !== 1 ? 's' : ''} in the last {days} days
                    </p>
                </>
            )}
        </div>
    );
}
