import * as d3 from 'd3';
import { useEffect, useRef, useState } from 'react';

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

type TooltipState = {
    x: number;
    y: number;
    point: SeriesPoint;
};

type Window = 7 | 30 | 90;

const WINDOWS: { label: string; value: Window }[] = [
    { label: '7d', value: 7 },
    { label: '30d', value: 30 },
    { label: '90d', value: 90 },
];

const MARGIN = { top: 16, right: 16, bottom: 36, left: 44 };
const HEIGHT = 220;
const LINE_COLOR = '#7c3aed';

export function PropertyRiskTrendsChart({ propertyId }: { propertyId: number }) {
    const [window, setWindow] = useState<Window>(30);
    const [data, setData] = useState<TrendsData | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [tooltip, setTooltip] = useState<TooltipState | null>(null);

    const svgRef = useRef<SVGSVGElement>(null);
    const containerRef = useRef<HTMLDivElement>(null);

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

    useEffect(() => {
        if (!data || !svgRef.current || !containerRef.current || data.series.length === 0) return;

        const totalWidth = containerRef.current.getBoundingClientRect().width || 640;
        const innerW = totalWidth - MARGIN.left - MARGIN.right;
        const innerH = HEIGHT - MARGIN.top - MARGIN.bottom;

        type Parsed = SeriesPoint & { dateObj: Date };
        const parsed: Parsed[] = data.series.map((p) => ({
            ...p,
            dateObj: new Date(p.date + 'T00:00:00'),
        }));

        const xScale = d3
            .scaleTime()
            .domain(d3.extent(parsed, (d) => d.dateObj) as [Date, Date])
            .range([0, innerW]);

        const maxRisk = d3.max(parsed, (d) => d.risk_score) ?? 0;
        const yScale = d3
            .scaleLinear()
            .domain([0, Math.max(maxRisk, 1)])
            .nice()
            .range([innerH, 0]);

        const svg = d3.select(svgRef.current).attr('width', totalWidth).attr('height', HEIGHT);

        let g = svg.select<SVGGElement>('g.trends-root');
        if (g.empty()) {
            g = svg
                .append('g')
                .attr('class', 'trends-root')
                .attr('transform', `translate(${MARGIN.left},${MARGIN.top})`);
            g.append('g').attr('class', 'area-group');
            g.append('g').attr('class', 'line-group');
            g.append('g').attr('class', 'x-axis').attr('transform', `translate(0,${innerH})`);
            g.append('g').attr('class', 'y-axis');
            g.append('rect')
                .attr('class', 'overlay')
                .attr('width', innerW)
                .attr('height', innerH)
                .attr('fill', 'transparent')
                .style('cursor', 'crosshair');
        }

        const tickCount = window <= 7 ? 7 : 6;
        const xAxis = d3
            .axisBottom(xScale)
            .ticks(tickCount)
            .tickFormat((d) => d3.timeFormat(window <= 7 ? '%a' : '%b %d')(d as Date));

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
                    .attr('opacity', 0.4),
            )
            .call((ax) => ax.selectAll('.tick text').attr('fill', 'var(--muted-foreground)').attr('font-size', '11'));

        const areaGen = d3
            .area<Parsed>()
            .x((d) => xScale(d.dateObj))
            .y0(innerH)
            .y1((d) => yScale(d.risk_score))
            .curve(d3.curveCatmullRom.alpha(0.5));

        const lineGen = d3
            .line<Parsed>()
            .x((d) => xScale(d.dateObj))
            .y((d) => yScale(d.risk_score))
            .curve(d3.curveCatmullRom.alpha(0.5));

        const areaPath = g
            .select<SVGPathElement>('.area-group')
            .selectAll<SVGPathElement, null>('path')
            .data([null]);

        areaPath
            .enter()
            .append('path')
            .attr('fill', LINE_COLOR)
            .attr('opacity', 0)
            .merge(areaPath)
            .transition()
            .duration(600)
            .ease(d3.easeCubicOut)
            .attr('d', areaGen(parsed) ?? '')
            .attr('fill', LINE_COLOR)
            .attr('opacity', 0.08);

        const linePath = g
            .select<SVGPathElement>('.line-group')
            .selectAll<SVGPathElement, null>('path')
            .data([null]);

        linePath
            .enter()
            .append('path')
            .attr('fill', 'none')
            .attr('stroke', LINE_COLOR)
            .attr('stroke-width', 2)
            .attr('stroke-linejoin', 'round')
            .attr('stroke-linecap', 'round')
            .merge(linePath)
            .transition()
            .duration(600)
            .ease(d3.easeCubicOut)
            .attr('d', lineGen(parsed) ?? '')
            .attr('stroke', LINE_COLOR);

        const bisect = d3.bisector<Parsed, Date>((d) => d.dateObj).left;

        g.select<SVGRectElement>('.overlay')
            .attr('width', innerW)
            .attr('height', innerH)
            .on('mousemove', (event: MouseEvent) => {
                const [mx] = d3.pointer(event);
                const date = xScale.invert(mx);
                const idx = bisect(parsed, date, 1);
                const a = parsed[idx - 1];
                const b = parsed[idx];
                const pt =
                    b && date.getTime() - a.dateObj.getTime() > b.dateObj.getTime() - date.getTime() ? b : a;
                if (!pt) return;
                const svgRect = containerRef.current!.getBoundingClientRect();
                setTooltip({ x: event.clientX - svgRect.left, y: event.clientY - svgRect.top, point: pt });
            })
            .on('mouseleave', () => setTooltip(null));
    }, [data, window]);

    const noData =
        !loading && !error && (!data || data.series.every((p) => p.risk_score === 0));

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
                <div ref={containerRef} className="relative w-full select-none">
                    {tooltip && (
                        <div
                            role="tooltip"
                            className="pointer-events-none absolute z-10 min-w-40 rounded-md border bg-popover px-3 py-2 text-xs text-popover-foreground shadow-md"
                            style={{ left: tooltip.x + 14, top: tooltip.y - 56 }}
                        >
                            <p className="mb-1 font-semibold">
                                {new Date(tooltip.point.date + 'T00:00:00').toLocaleDateString(undefined, {
                                    month: 'short',
                                    day: 'numeric',
                                })}
                            </p>
                            <p>
                                Risk score:{' '}
                                <span className="font-medium tabular-nums">{tooltip.point.risk_score}</span>
                            </p>
                            <p>
                                Open issues:{' '}
                                <span className="font-medium tabular-nums">{tooltip.point.open_issues}</span>
                            </p>
                        </div>
                    )}
                    <svg
                        ref={svgRef}
                        className="w-full overflow-visible"
                        role="img"
                        aria-label="Property risk trend"
                    />
                </div>
            )}
        </div>
    );
}
