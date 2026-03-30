import { ChartOptions } from 'chart.js';
import { useEffect, useState } from 'react';
import { Line } from 'react-chartjs-2';

import { Skeleton } from '@/components/ui/skeleton';

type DayPoint = {
    date: string;
    scans: number;
    violations: number;
};

type ActivityData = {
    days: DayPoint[];
    generated_at: string;
};

export function PropertyScanActivityChart({ propertyId }: { propertyId: number }) {
    const [data, setData] = useState<ActivityData | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);
        setError(null);

        fetch(`/api/properties/${propertyId}/scans/activity`, {
            signal: controller.signal,
            headers: { Accept: 'application/json' },
        })
            .then((res) => {
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                return res.json() as Promise<ActivityData>;
            })
            .then((json) => {
                setData(json);
                setLoading(false);
            })
            .catch((err: unknown) => {
                if (err instanceof Error && err.name !== 'AbortError') {
                    setError('Failed to load scan activity.');
                    setLoading(false);
                }
            });

        return () => controller.abort();
    }, [propertyId]);

    if (loading) return <Skeleton className="h-48 w-full rounded-xl" />;
    if (error) return <p className="text-sm text-destructive">{error}</p>;

    const days = data?.days ?? [];
    const totalScans = days.reduce((s, d) => s + d.scans, 0);

    const chartData = {
        labels: days.map((d) =>
            new Date(d.date + 'T00:00:00').toLocaleDateString(undefined, { month: 'short', day: 'numeric' }),
        ),
        datasets: [
            {
                label: 'Scans',
                data: days.map((d) => d.scans),
                borderColor: '#2563eb',
                backgroundColor: 'rgba(37,99,235,0.1)',
                fill: true,
                tension: 0.3,
                pointRadius: 4,
                pointBackgroundColor: '#2563eb',
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
                        const pt = days[item.dataIndex];
                        return [`Scans: ${pt.scans}`, `Violations: ${pt.violations}`];
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
        <div className="w-full">
            <Line
                data={chartData}
                options={options}
                aria-label="Scan activity over the last 30 days"
                role="img"
            />
            <p className="mt-1 text-right text-xs text-muted-foreground">
                {totalScans} completed scan{totalScans !== 1 ? 's' : ''} in the last 30 days
            </p>
        </div>
    );
}
