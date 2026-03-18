import * as d3 from 'd3';
import { useEffect, useRef, useState } from 'react';

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

type TooltipState = {
    x: number;
    y: number;
    point: HistoryPoint & { dateObj: Date };
};

export type AuditScoreTrendChartProps = {
    propertyId: number;
    window?: 7 | 30 | 90;
};

const MARGIN = { top: 16, right: 16, bottom: 36, left: 44 };
const HEIGHT = 200;
const LINE_COLOR = '#7c3aed';
const AREA_COLOR = '#7c3aed';
const DOT_COLOR = '#7c3aed';

export function AuditScoreTrendChart({ propertyId, window: initialWindow = 30 }: AuditScoreTrendChartProps) {
    const [days, setDays] = useState<7 | 30 | 90>(initialWindow);
    const [data, setData] = useState<TrendData | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [tooltip, setTooltip] = useState<TooltipState | null>(null);

    const svgRef = useRef<SVGSVGElement>(null);
    const containerRef = useRef<HTMLDivElement>(null);

    // ── Data fetching ────────────────────────────────────────────────────────
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

    // ── D3 render ────────────────────────────────────────────────────────────
    useEffect(() => {
        if (!data || !svgRef.current || !containerRef.current) return;
        if (data.history.length < 2) return;

        const totalWidth = containerRef.current.getBoundingClientRect().width || 600;
        const innerW = totalWidth - MARGIN.left - MARGIN.right;
        const innerH = HEIGHT - MARGIN.top - MARGIN.bottom;

        const parsed = data.history
            .filter((d) => d.overall_score !== null)
            .map((d) => ({
                ...d,
                dateObj: new Date(d.generated_at),
                score: d.overall_score as number,
            }));

        if (parsed.length < 2) return;

        const xScale = d3
            .scaleTime()
            .domain(d3.extent(parsed, (d) => d.dateObj) as [Date, Date])
            .range([0, innerW]);

        const yScale = d3
            .scaleLinear()
            .domain([0, 100])
            .nice()
            .range([innerH, 0]);

        const svg = d3.select(svgRef.current).attr('width', totalWidth).attr('height', HEIGHT);

        let g = svg.select<SVGGElement>('g.chart-root');
        if (g.empty()) {
            g = svg.append('g').attr('class', 'chart-root').attr('transform', `translate(${MARGIN.left},${MARGIN.top})`);
            g.append('g').attr('class', 'area-group');
            g.append('g').attr('class', 'line-group');
            g.append('g').attr('class', 'dots-group');
            g.append('g').attr('class', 'x-axis').attr('transform', `translate(0,${innerH})`);
            g.append('g').attr('class', 'y-axis');
            g.append('rect').attr('class', 'overlay').attr('fill', 'transparent').style('cursor', 'crosshair');
        }

        // ── Axes ─────────────────────────────────────────────────────────────
        const xAxis = d3
            .axisBottom(xScale)
            .ticks(5)
            .tickFormat((d) => d3.timeFormat('%b %d')(d as Date));

        const yAxis = d3.axisLeft(yScale).ticks(5).tickSize(-innerW).tickFormat((d) => `${d}`);

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
            .y1((d) => yScale(d.score))
            .curve(d3.curveCatmullRom.alpha(0.5));

        const areaPath = g.select<SVGPathElement>('.area-group').selectAll<SVGPathElement, null>('path').data([null]);
        areaPath
            .enter()
            .append('path')
            .attr('fill', AREA_COLOR)
            .attr('opacity', 0)
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
            .y((d) => yScale(d.score))
            .curve(d3.curveCatmullRom.alpha(0.5));

        const linePath = g.select<SVGPathElement>('.line-group').selectAll<SVGPathElement, null>('path').data([null]);
        linePath
            .enter()
            .append('path')
            .attr('fill', 'none')
            .attr('stroke', LINE_COLOR)
            .attr('stroke-width', 2)
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
            .data(parsed, (d) => d.id.toString());

        dots.enter()
            .append('circle')
            .attr('r', 0)
            .attr('cx', (d) => xScale(d.dateObj))
            .attr('cy', (d) => yScale(d.score))
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
            .attr('cy', (d) => yScale(d.score))
            .attr('r', 5)
            .attr('fill', DOT_COLOR);

        dots.exit().transition().duration(300).attr('r', 0).remove();

        // ── Overlay ───────────────────────────────────────────────────────────
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
    if (loading) return <Skeleton className="h-48 w-full rounded-xl" />;
    if (error) return <p className="text-sm text-destructive">{error}</p>;

    const hasEnoughData = (data?.history.filter((d) => d.overall_score !== null).length ?? 0) >= 2;

    return (
        <div ref={containerRef} className="relative w-full select-none">
            {/* Window toggle */}
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
                    {/* Tooltip */}
                    {tooltip && (
                        <div
                            role="tooltip"
                            className="pointer-events-none absolute z-10 min-w-[160px] rounded-md border bg-popover px-3 py-2 text-xs text-popover-foreground shadow-md"
                            style={{ left: tooltip.x + 14, top: tooltip.y - 64 }}
                        >
                            <p className="mb-1 font-semibold">{tooltip.point.title}</p>
                            <p>
                                Score:{' '}
                                <span className="font-medium tabular-nums">{tooltip.point.score}</span>
                                <span className="text-muted-foreground">/100</span>
                            </p>
                            <p className="text-muted-foreground">
                                {new Date(tooltip.point.generated_at).toLocaleDateString(undefined, {
                                    month: 'short',
                                    day: 'numeric',
                                    year: 'numeric',
                                })}
                            </p>
                        </div>
                    )}

                    <svg
                        ref={svgRef}
                        className="w-full overflow-visible"
                        role="img"
                        aria-label="Audit accessibility score trend – line chart"
                    />

                    <p className="mt-1 text-right text-xs text-muted-foreground">
                        {data?.audit_count ?? 0} audit{(data?.audit_count ?? 0) !== 1 ? 's' : ''} in the last {days} days
                    </p>
                </>
            )}
        </div>
    );
}
