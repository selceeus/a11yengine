import * as d3 from 'd3';
import { useEffect, useRef, useState } from 'react';

import { Skeleton } from '@/components/ui/skeleton';

type DayPoint = {
    date: string; // YYYY-MM-DD
    scans: number;
    violations: number;
};

type ActivityData = {
    days: DayPoint[];
    generated_at: string;
};

type TooltipState = {
    x: number;
    y: number;
    point: DayPoint;
};

export type ScanActivityChartProps = {
    agencyId: number;
};

const MARGIN = { top: 16, right: 16, bottom: 36, left: 40 };
const HEIGHT = 200;
const LINE_COLOR = '#2563eb';
const AREA_COLOR = '#2563eb';
const DOT_COLOR = '#2563eb';

export function ScanActivityChart({ agencyId }: ScanActivityChartProps) {
    const [data, setData] = useState<ActivityData | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [tooltip, setTooltip] = useState<TooltipState | null>(null);

    const svgRef = useRef<SVGSVGElement>(null);
    const containerRef = useRef<HTMLDivElement>(null);
    const widthRef = useRef(0);

    // ── Data fetching ────────────────────────────────────────────────────────
    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);
        setError(null);

        fetch(`/api/agencies/${agencyId}/scans/activity`, {
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
    }, [agencyId]);

    // ── D3 render + animate ──────────────────────────────────────────────────
    useEffect(() => {
        if (!data || !svgRef.current || !containerRef.current) return;

        const totalWidth = containerRef.current.getBoundingClientRect().width || 600;
        widthRef.current = totalWidth;

        const innerW = totalWidth - MARGIN.left - MARGIN.right;
        const innerH = HEIGHT - MARGIN.top - MARGIN.bottom;

        const parsed: (DayPoint & { dateObj: Date })[] = data.days.map((d) => ({
            ...d,
            dateObj: new Date(d.date + 'T00:00:00'),
        }));

        const xScale = d3
            .scaleTime()
            .domain(d3.extent(parsed, (d) => d.dateObj) as [Date, Date])
            .range([0, innerW]);

        const maxScans = d3.max(parsed, (d) => d.scans) ?? 0;
        const yScale = d3
            .scaleLinear()
            .domain([0, Math.max(maxScans, 1)])
            .nice()
            .range([innerH, 0]);

        const svg = d3.select(svgRef.current).attr('width', totalWidth).attr('height', HEIGHT);

        // Root group (created once, translated once)
        let g = svg.select<SVGGElement>('g.chart-root');
        if (g.empty()) {
            g = svg.append('g').attr('class', 'chart-root').attr('transform', `translate(${MARGIN.left},${MARGIN.top})`);
            g.append('g').attr('class', 'area-group');
            g.append('g').attr('class', 'line-group');
            g.append('g').attr('class', 'dots-group');
            g.append('g').attr('class', 'x-axis').attr('transform', `translate(0,${innerH})`);
            g.append('g').attr('class', 'y-axis');
            // Invisible overlay for mouse events
            g.append('rect')
                .attr('class', 'overlay')
                .attr('width', innerW)
                .attr('height', innerH)
                .attr('fill', 'transparent')
                .style('cursor', 'crosshair');
        }

        // ── Axes ─────────────────────────────────────────────────────────────
        const xAxis = d3
            .axisBottom(xScale)
            .ticks(6)
            .tickFormat((d) => d3.timeFormat('%b %d')(d as Date));

        const yAxis = d3.axisLeft(yScale).ticks(4).tickSize(-innerW);

        g.select<SVGGElement>('.x-axis')
            .transition()
            .duration(400)
            .call(xAxis)
            .call((ax) => ax.select('.domain').attr('stroke', 'var(--border)'))
            .call((ax) => ax.selectAll('.tick line').attr('stroke', 'var(--border)'))
            .call((ax) => ax.selectAll('.tick text').attr('fill', 'var(--muted-foreground)').attr('font-size', '11'));

        g.select<SVGGElement>('.y-axis')
            .transition()
            .duration(400)
            .call(yAxis)
            .call((ax) => ax.select('.domain').remove())
            .call((ax) =>
                ax
                    .selectAll('.tick line')
                    .attr('stroke', 'var(--border)')
                    .attr('stroke-dasharray', '3,3')
                    .attr('opacity', 0.5),
            )
            .call((ax) => ax.selectAll('.tick text').attr('fill', 'var(--muted-foreground)').attr('font-size', '11'));

        // ── Area ─────────────────────────────────────────────────────────────
        const areaGen = d3
            .area<(typeof parsed)[0]>()
            .x((d) => xScale(d.dateObj))
            .y0(innerH)
            .y1((d) => yScale(d.scans))
            .curve(d3.curveCatmullRom.alpha(0.5));

        const areaPath = g.select<SVGPathElement>('.area-group').selectAll<SVGPathElement, null>('path').data([null]);

        const areaEntered = areaPath.enter().append('path').attr('fill', AREA_COLOR).attr('opacity', 0);

        areaEntered
            .merge(areaPath)
            .transition()
            .duration(600)
            .ease(d3.easeCubicOut)
            .attr('d', areaGen(parsed) ?? '')
            .attr('fill', AREA_COLOR)
            .attr('opacity', 0.1);

        // ── Line ─────────────────────────────────────────────────────────────
        const lineGen = d3
            .line<(typeof parsed)[0]>()
            .x((d) => xScale(d.dateObj))
            .y((d) => yScale(d.scans))
            .curve(d3.curveCatmullRom.alpha(0.5));

        const linePath = g.select<SVGPathElement>('.line-group').selectAll<SVGPathElement, null>('path').data([null]);

        const lineEntered = linePath
            .enter()
            .append('path')
            .attr('fill', 'none')
            .attr('stroke', LINE_COLOR)
            .attr('stroke-width', 2);

        lineEntered
            .merge(linePath)
            .transition()
            .duration(600)
            .ease(d3.easeCubicOut)
            .attr('d', lineGen(parsed) ?? '')
            .attr('stroke', LINE_COLOR)
            .attr('stroke-width', 2);

        // ── Dots ─────────────────────────────────────────────────────────────
        const dots = g
            .select<SVGGElement>('.dots-group')
            .selectAll<SVGCircleElement, (typeof parsed)[0]>('circle')
            .data(parsed, (d) => d.date);

        dots.enter()
            .append('circle')
            .attr('r', 0)
            .attr('cx', (d) => xScale(d.dateObj))
            .attr('cy', (d) => yScale(d.scans))
            .attr('fill', DOT_COLOR)
            .attr('stroke', 'var(--background)')
            .attr('stroke-width', 2)
            .merge(dots)
            .on('mousemove', (event: MouseEvent, d) => {
                const svgRect = containerRef.current!.getBoundingClientRect();
                setTooltip({ x: event.clientX - svgRect.left, y: event.clientY - svgRect.top, point: d });
            })
            .on('mouseleave', () => setTooltip(null))
            .transition()
            .duration(600)
            .ease(d3.easeCubicOut)
            .attr('cx', (d) => xScale(d.dateObj))
            .attr('cy', (d) => yScale(d.scans))
            .attr('r', (d) => (d.scans > 0 ? 4 : 2))
            .attr('fill', DOT_COLOR);

        dots.exit().transition().duration(300).attr('r', 0).remove();

        // ── Overlay mouse tracking for fine-grained tooltip ──────────────────
        const bisect = d3.bisector<(typeof parsed)[0], Date>((d) => d.dateObj).left;

        g.select<SVGRectElement>('.overlay')
            .attr('width', innerW)
            .attr('height', innerH)
            .on('mousemove', (event: MouseEvent) => {
                const [mx] = d3.pointer(event);
                const date = xScale.invert(mx);
                const idx = bisect(parsed, date, 1);
                const a = parsed[idx - 1];
                const b = parsed[idx];
                const pt = b && date.getTime() - a.dateObj.getTime() > b.dateObj.getTime() - date.getTime() ? b : a;
                if (!pt) return;
                const svgRect = containerRef.current!.getBoundingClientRect();
                setTooltip({ x: event.clientX - svgRect.left, y: event.clientY - svgRect.top, point: pt });
            })
            .on('mouseleave', () => setTooltip(null));
    }, [data]);

    // ── Render ───────────────────────────────────────────────────────────────
    if (loading) {
        return <Skeleton className="h-48 w-full rounded-xl" />;
    }

    if (error) {
        return <p className="text-sm text-destructive">{error}</p>;
    }

    const totalScans = data?.days.reduce((s, d) => s + d.scans, 0) ?? 0;

    return (
        <div ref={containerRef} className="relative w-full select-none">
            {/* Tooltip */}
            {tooltip && (
                <div
                    role="tooltip"
                    className="pointer-events-none absolute z-10 min-w-[140px] rounded-md border bg-popover px-3 py-2 text-xs text-popover-foreground shadow-md"
                    style={{ left: tooltip.x + 14, top: tooltip.y - 56 }}
                >
                    <p className="mb-1 font-semibold">{new Date(tooltip.point.date + 'T00:00:00').toLocaleDateString(undefined, { month: 'short', day: 'numeric' })}</p>
                    <p>
                        Scans:{' '}
                        <span className="font-medium tabular-nums">{tooltip.point.scans}</span>
                    </p>
                    <p>
                        Violations:{' '}
                        <span className="font-medium tabular-nums">{tooltip.point.violations}</span>
                    </p>
                </div>
            )}

            {/* Chart */}
            <svg
                ref={svgRef}
                className="w-full overflow-visible"
                role="img"
                aria-label="Scan activity over the last 30 days – line chart"
            />

            {/* Footer */}
            <p className="mt-1 text-right text-xs text-muted-foreground">
                {totalScans} completed scan{totalScans !== 1 ? 's' : ''} in the last 30 days
            </p>
        </div>
    );
}
