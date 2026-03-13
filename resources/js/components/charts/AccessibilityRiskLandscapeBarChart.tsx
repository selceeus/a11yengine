 import * as d3 from 'd3';
import { useEffect, useRef, useState } from 'react';

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
    return path.length > 32 ? path.slice(0, 30) + '…' : path;
}

const MAX_PAGES = 20;
const MARGIN = { top: 8, right: 64, bottom: 32, left: 200 };
const BAR_HEIGHT = 24;
const BAR_GAP = 6;

export type AccessibilityRiskLandscapeBarChartProps = {
    siteId: number | null;
};

export function AccessibilityRiskLandscapeBarChart({ siteId }: AccessibilityRiskLandscapeBarChartProps) {
    const [data, setData] = useState<RiskPage[] | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const svgRef = useRef<SVGSVGElement>(null);
    const containerRef = useRef<HTMLDivElement>(null);

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

    useEffect(() => {
        if (!data || !svgRef.current || !containerRef.current) return;

        const items = [...data].sort((a, b) => b.riskScore - a.riskScore).slice(0, MAX_PAGES);

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
            .domain(items.map((d) => d.url))
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
        g.selectAll('rect')
            .data(items)
            .join('rect')
            .attr('x', 0)
            .attr('y', (d) => yScale(d.url) ?? 0)
            .attr('height', yScale.bandwidth())
            .attr('rx', 4)
            .attr('width', 0)
            .attr('fill', (d) => riskColor(d.riskScore))
            .attr('opacity', 0.85)
            .transition()
            .duration(600)
            .ease(d3.easeCubicOut)
            .attr('width', (d) => xScale(d.riskScore));

        // Score labels after bar
        g.selectAll('text.score')
            .data(items)
            .join('text')
            .attr('class', 'score')
            .attr('x', (d) => xScale(d.riskScore) + 6)
            .attr('y', (d) => (yScale(d.url) ?? 0) + yScale.bandwidth() / 2)
            .attr('dy', '0.35em')
            .attr('fill', 'var(--foreground)')
            .attr('font-size', '11')
            .text((d) => d.riskScore);

        // Issue count badge inside bar
        g.selectAll('text.issues')
            .data(items.filter((d) => xScale(d.riskScore) > 60))
            .join('text')
            .attr('class', 'issues')
            .attr('x', (d) => xScale(d.riskScore) - 6)
            .attr('y', (d) => (yScale(d.url) ?? 0) + yScale.bandwidth() / 2)
            .attr('dy', '0.35em')
            .attr('text-anchor', 'end')
            .attr('fill', '#fff')
            .attr('font-size', '10')
            .text((d) => `${d.issueCount} issues`);

        // Y axis labels
        g.append('g')
            .selectAll('text')
            .data(items)
            .join('text')
            .attr('x', -8)
            .attr('y', (d) => (yScale(d.url) ?? 0) + yScale.bandwidth() / 2)
            .attr('dy', '0.35em')
            .attr('text-anchor', 'end')
            .attr('fill', 'var(--muted-foreground)')
            .attr('font-size', '11')
            .text((d) => trimUrl(d.url));
    }, [data]);

    if (!siteId) {
        return <p className="text-sm text-muted-foreground">No property selected.</p>;
    }

    const noData = !loading && !error && (!data || data.length === 0);

    return (
        <div ref={containerRef} className="w-full">
            {loading && <Skeleton className="h-64 w-full rounded-xl" />}
            {error && <p className="text-sm text-destructive">{error}</p>}
            {noData && <p className="text-sm text-muted-foreground">No page risk data available for this property.</p>}
            {!loading && !error && data && data.length > 0 && (
                <svg ref={svgRef} className="w-full overflow-visible" />
            )}
        </div>
    );
}
