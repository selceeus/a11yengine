import * as d3 from 'd3';
import { useEffect, useRef, useState } from 'react';

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

const MARGIN = { top: 8, right: 64, bottom: 32, left: 160 };
const BAR_HEIGHT = 28;
const BAR_GAP = 6;

export type TopAtRiskPropertiesBarChartProps = {
    agencyId: number;
    organizationId?: number;
};

export function TopAtRiskPropertiesBarChart({ agencyId, organizationId }: TopAtRiskPropertiesBarChartProps) {
    const [data, setData] = useState<TopRiskData | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const svgRef = useRef<SVGSVGElement>(null);
    const containerRef = useRef<HTMLDivElement>(null);

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

    useEffect(() => {
        if (!data || !svgRef.current || !containerRef.current) return;

        const items = [...data.properties].sort((a, b) => b.risk_score - a.risk_score);

        if (items.length === 0) return;

        const totalWidth = containerRef.current.getBoundingClientRect().width || 600;
        const innerW = totalWidth - MARGIN.left - MARGIN.right;
        const innerH = items.length * (BAR_HEIGHT + BAR_GAP);
        const totalHeight = innerH + MARGIN.top + MARGIN.bottom;

        const svg = d3.select(svgRef.current);
        svg.selectAll('*').remove();
        svg.attr('width', totalWidth).attr('height', totalHeight);

        const g = svg.append('g').attr('transform', `translate(${MARGIN.left},${MARGIN.top})`);

        const xScale = d3.scaleLinear().domain([0, 100]).range([0, innerW]);
        const yScale = d3
            .scaleBand()
            .domain(items.map((d) => String(d.id)))
            .range([0, innerH])
            .padding(0.2);

        // Grid lines
        g.append('g')
            .selectAll('line')
            .data(xScale.ticks(5))
            .join('line')
            .attr('x1', (d) => xScale(d))
            .attr('x2', (d) => xScale(d))
            .attr('y1', 0)
            .attr('y2', innerH)
            .attr('stroke', 'var(--border)')
            .attr('stroke-dasharray', '3,3');

        // X axis
        g.append('g')
            .attr('transform', `translate(0,${innerH})`)
            .call(d3.axisBottom(xScale).ticks(5))
            .call((ax) => ax.select('.domain').remove())
            .call((ax) => ax.selectAll('.tick line').remove())
            .call((ax) =>
                ax
                    .selectAll('.tick text')
                    .attr('fill', 'var(--muted-foreground)')
                    .attr('font-size', '11'),
            );

        // Bars
        const barG = g.append('g');

        barG.selectAll('rect')
            .data(items)
            .join('rect')
            .attr('x', 0)
            .attr('y', (d) => yScale(String(d.id)) ?? 0)
            .attr('height', yScale.bandwidth())
            .attr('rx', 4)
            .attr('width', 0)
            .attr('fill', (d) => (d.highest_severity ? SEVERITY_COLORS[d.highest_severity] : '#6b7280'))
            .attr('opacity', 0.85)
            .style('cursor', 'pointer')
            .on('click', (_, d) => router.visit(`/properties/${d.id}`))
            .transition()
            .duration(600)
            .ease(d3.easeCubicOut)
            .attr('width', (d) => xScale(d.risk_score));

        // Issue count badge inside bar
        barG.selectAll('text.issues')
            .data(items.filter((d) => xScale(d.risk_score) > 60))
            .join('text')
            .attr('class', 'issues')
            .attr('x', (d) => xScale(d.risk_score) - 6)
            .attr('y', (d) => (yScale(String(d.id)) ?? 0) + yScale.bandwidth() / 2)
            .attr('dy', '0.35em')
            .attr('text-anchor', 'end')
            .attr('fill', '#fff')
            .attr('font-size', '10')
            .text((d) => `${d.open_issue_count} issues`);

        // Score labels after bar
        barG.selectAll('text.score')
            .data(items)
            .join('text')
            .attr('class', 'score')
            .attr('x', (d) => xScale(d.risk_score) + 6)
            .attr('y', (d) => (yScale(String(d.id)) ?? 0) + yScale.bandwidth() / 2)
            .attr('dy', '0.35em')
            .attr('fill', 'var(--foreground)')
            .attr('font-size', '11')
            .text((d) => d.risk_score);

        // Y axis labels
        g.append('g')
            .selectAll('text')
            .data(items)
            .join('text')
            .attr('x', -8)
            .attr('y', (d) => (yScale(String(d.id)) ?? 0) + yScale.bandwidth() / 2)
            .attr('dy', '0.35em')
            .attr('text-anchor', 'end')
            .attr('fill', 'var(--foreground)')
            .attr('font-size', '12')
            .style('cursor', 'pointer')
            .on('click', (_, d) => router.visit(`/properties/${d.id}`))
            .text((d) => (d.name.length > 22 ? d.name.slice(0, 20) + '…' : d.name));
    }, [data]);

    const noData = !loading && !error && (!data || data.properties.length === 0);

    return (
        <div ref={containerRef} className="w-full">
            {loading && <Skeleton className="h-64 w-full rounded-xl" />}
            {error && <p className="text-sm text-destructive">{error}</p>}
            {noData && <p className="text-sm text-muted-foreground">No at-risk properties found.</p>}
            {!loading && !error && data && data.properties.length > 0 && (
                <svg ref={svgRef} className="w-full overflow-visible" />
            )}
        </div>
    );
}
