import { ChartOptions } from 'chart.js';
import { useEffect, useState } from 'react';
import { Bar } from 'react-chartjs-2';

import { Skeleton } from '@/components/ui/skeleton';

type RiskPage = {
    url: string;
    riskScore: number;
    issueCount: number;
    lighthouseAccessibility: number;
};

function riskColor(score: number): string {
    if (score >= 70) return '#ef4444';
    if (score >= 40) return '#f97316';
    return '#22c55e';
}

function trimUrl(url: string): string {
    const path = url.replace(/^https?:\/\/[^/]+/, '') || '/';
    return path.length > 32 ? path.slice(0, 30) + '\u2026' : path;
}

const MAX_PAGES = 20;

export type AccessibilityRiskLandscapeBarChartProps = {
    siteId: number | null;
};

export function AccessibilityRiskLandscapeBarChart({ siteId }: AccessibilityRiskLandscapeBarChartProps) {
    const [data, setData] = useState<RiskPage[] | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (!siteId) {
            setLoading(false);
            return;
        }

        const ctrl = new AbortController();
        setLoading(true);
        setError(null);

        fetch(`/api/sites/${siteId}/risk-map`, {
            signal: ctrl.signal,
            headers: { Accept: 'application/json' },
        })
            .then((res) => {
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                return res.json() as Promise<RiskPage[]>;
            })
            .then((json) => {
                setData(json);
                setLoading(false);
            })
            .catch((err: unknown) => {
                if (err instanceof Error && err.name !== 'AbortError') {
                    setError('Failed to load risk landscape data.');
                    setLoading(false);
                }
            });

        return () => ctrl.abort();
    }, [siteId]);

    if (!siteId) return <p className="text-sm text-muted-foreground">No property selected.</p>;
    if (loading) return <Skeleton className="h-64 w-full rounded" />;
    if (error) return <p className="text-sm text-destructive">{error}</p>;
    if (!data || data.length === 0) return <p className="text-sm text-muted-foreground">No page risk data available for this property.</p>;

    const items = [...data].sort((a, b) => b.riskScore - a.riskScore).slice(0, MAX_PAGES);
    const chartHeight = Math.min(280, Math.max(200, items.length * 34));

    const chartData = {
        labels: items.map((p) => trimUrl(p.url)),
        datasets: [
            {
                label: 'Risk score',
                data: items.map((p) => p.riskScore),
                backgroundColor: items.map((p) => riskColor(p.riskScore)),
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
                        return `Score: ${p.riskScore}  |  ${p.issueCount} issues`;
                    },
                },
            },
        },
        scales: {
            x: { min: 0, max: 100, grid: { color: 'rgba(0,0,0,0.06)' } },
            y: { grid: { display: false } },
        },
    };

    return (
        <div className="w-full" style={{ height: chartHeight }}>
            <Bar
                data={chartData}
                options={options}
                aria-label="Accessibility risk landscape – horizontal bar chart"
                role="img"
            />
        </div>
    );
}
