import * as d3 from 'd3';
import { useEffect, useRef, useState } from 'react';

import { Skeleton } from '@/components/ui/skeleton';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';

// ── Types ─────────────────────────────────────────────────────────────────────

type SeriesPoint = {
    date: string; // YYYY-MM-DD
    risk_score: number;
    open_issues: number;
};

type OrgSeries = {
    id: number;
    name: string;
    series: SeriesPoint[];
};

type TrendsData = {
    organizations: OrgSeries[];
    days: string[];
    generated_at: string;
};

type TooltipState = {
    x: number;
    y: number;
    date: string;
    entries: { name: string; color: string; risk_score: number; open_issues: number }[];
};

export type OrgRiskTrendsChartProps = {
    agencyId: number;
    organizationId?: number;
};

type Window = 7 | 30 | 90;

// ── Constants ─────────────────────────────────────────────────────────────────

const WINDOWS: { label: string; value: Window }[] = [
    { label: '7d', value: 7 },
    { label: '30d', value: 30 },
    { label: '90d', value: 90 },
];

const MARGIN = { top: 16, right: 16, bottom: 36, left: 44 };
const HEIGHT = 220;

// ── Component ─────────────────────────────────────────────────────────────────

export function OrgRiskTrendsChart({ agencyId, organizationId }: OrgRiskTrendsChartProps) {
    const [window, setWindow] = useState<Window>(30);
    const [data, setData] = useState<TrendsData | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [tooltip, setTooltip] = useState<TooltipState | null>(null);

    const svgRef = useRef<SVGSVGElement>(null);
    const containerRef = useRef<HTMLDivElement>(null);

    // ── Data fetching ──────────────────────────────────────────────────────────
    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);
        setError(null);

        const url = organizationId
            ? `/api/organizations/${organizationId}/risk-trends?days=${window}`
            : `/api/agencies/${agencyId}/organizations/risk-trends?days=${window}`;

        fetch(url, {
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
                    setError('Failed to load organisation risk trends.');
                    setLoading(false);
                }
            });

        return () => controller.abort();
    }, [agencyId, organizationId, window]);

    // ── D3 render ──────────────────────────────────────────────────────────────
    useEffect(() => {
        if (!data || !svgRef.current || !containerRef.current) return;
        if (data.organizations.length === 0) return;

        const totalWidth = containerRef.current.getBoundingClientRect().width || 640;
        const innerW = totalWidth - MARGIN.left - MARGIN.right;
        const innerH = HEIGHT - MARGIN.top - MARGIN.bottom;

        // Color scale — one hue per organisation
        const colorScale = d3
            .scaleOrdinal(d3.schemeTableau10)
            .domain(data.organizations.map((o) => String(o.id)));

        // Parse dates once
        type ParsedPoint = SeriesPoint & { dateObj: Date };
        const parsed: { id: number; name: string; pts: ParsedPoint[] }[] = data.organizations.map((o) => ({
            id: o.id,
            name: o.name,
            pts: o.series.map((p) => ({ ...p, dateObj: new Date(p.date + 'T00:00:00') })),
        }));

        const allDates = parsed[0]?.pts.map((p) => p.dateObj) ?? [];

        const xScale = d3
            .scaleTime()
            .domain(d3.extent(allDates) as [Date, Date])
            .range([0, innerW]);

        const maxRisk = d3.max(parsed.flatMap((o) => o.pts.map((p) => p.risk_score))) ?? 0;
        const yScale = d3
            .scaleLinear()
            .domain([0, Math.max(maxRisk, 1)])
            .nice()
            .range([innerH, 0]);

        const svg = d3.select(svgRef.current).attr('width', totalWidth).attr('height', HEIGHT);

        // Root group
        let g = svg.select<SVGGElement>('g.trends-root');
        if (g.empty()) {
            g = svg
                .append('g')
                .attr('class', 'trends-root')
                .attr('transform', `translate(${MARGIN.left},${MARGIN.top})`);
            g.append('g').attr('class', 'x-axis').attr('transform', `translate(0,${innerH})`);
            g.append('g').attr('class', 'y-axis');
            g.append('g').attr('class', 'lines');
            g.append('rect')
                .attr('class', 'overlay')
                .attr('width', innerW)
                .attr('height', innerH)
                .attr('fill', 'transparent')
                .style('cursor', 'crosshair');
        }

        // ── Axes ────────────────────────────────────────────────────────────────
        const tickCount = window <= 7 ? 7 : window <= 30 ? 6 : 6;

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

        // ── Lines ───────────────────────────────────────────────────────────────
        const lineGen = d3
            .line<ParsedPoint>()
            .x((p) => xScale(p.dateObj))
            .y((p) => yScale(p.risk_score))
            .curve(d3.curveCatmullRom.alpha(0.5));

        const linesGroup = g.select<SVGGElement>('.lines');

        const orgPaths = linesGroup
            .selectAll<SVGPathElement, (typeof parsed)[0]>('path.org-line')
            .data(parsed, (d) => String(d.id));

        // Enter
        orgPaths
            .enter()
            .append('path')
            .attr('class', 'org-line')
            .attr('fill', 'none')
            .attr('stroke', (d) => colorScale(String(d.id)))
            .attr('stroke-width', 2)
            .attr('stroke-linejoin', 'round')
            .attr('stroke-linecap', 'round')
            .attr('d', (d) => lineGen(d.pts) ?? '')
            .attr('opacity', 0)
            // Merge
            .merge(orgPaths)
            .transition()
            .duration(600)
            .ease(d3.easeCubicOut)
            .attr('d', (d) => lineGen(d.pts) ?? '')
            .attr('stroke', (d) => colorScale(String(d.id)))
            .attr('opacity', 1);

        orgPaths.exit().transition().duration(300).attr('opacity', 0).remove();

        // ── Overlay bisect tooltip ───────────────────────────────────────────────
        const bisect = d3.bisector<ParsedPoint, Date>((p) => p.dateObj).left;

        g.select<SVGRectElement>('.overlay')
            .attr('width', innerW)
            .attr('height', innerH)
            .on('mousemove', (event: MouseEvent) => {
                const [mx] = d3.pointer(event);
                const hoveredDate = xScale.invert(mx);

                const firstPts = parsed[0]?.pts ?? [];
                const idx = bisect(firstPts, hoveredDate, 1);
                const a = firstPts[idx - 1];
                const b = firstPts[idx];
                const snapPt = b && hoveredDate.getTime() - a.dateObj.getTime() > b.dateObj.getTime() - hoveredDate.getTime() ? b : a;
                if (!snapPt) return;

                const entries = parsed.map((o) => {
                    const pt = o.pts.find((p) => p.date === snapPt.date);
                    return {
                        name: o.name,
                        color: colorScale(String(o.id)),
                        risk_score: pt?.risk_score ?? 0,
                        open_issues: pt?.open_issues ?? 0,
                    };
                });

                const svgRect = containerRef.current!.getBoundingClientRect();
                setTooltip({ x: event.clientX - svgRect.left, y: event.clientY - svgRect.top, date: snapPt.date, entries });
            })
            .on('mouseleave', () => setTooltip(null));
    }, [data, window]);

    // ── Render ────────────────────────────────────────────────────────────────
    const noData = !loading && !error && (!data || data.organizations.length === 0);

    return (
        <div className="space-y-3">
            {/* Window picker */}
            <div className="flex items-center justify-between">
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
            {noData && <p className="text-sm text-muted-foreground">No organisation risk data yet.</p>}

            {!loading && !error && data && data.organizations.length > 0 && (
                <div ref={containerRef} className="relative w-full select-none">
                    {/* Tooltip */}
                    {tooltip && (
                        <div
                            role="tooltip"
                            className="pointer-events-none absolute z-10 min-w-[180px] rounded-md border bg-popover px-3 py-2 text-xs text-popover-foreground shadow-md"
                            style={{ left: tooltip.x + 14, top: tooltip.y - 60 }}
                        >
                            <p className="mb-1.5 font-semibold">
                                {new Date(tooltip.date + 'T00:00:00').toLocaleDateString(undefined, {
                                    month: 'short',
                                    day: 'numeric',
                                })}
                            </p>
                            {tooltip.entries.map((e) => (
                                <div key={e.name} className="flex items-center gap-1.5 py-0.5">
                                    <span className="inline-block h-2 w-2 shrink-0 rounded-sm" style={{ backgroundColor: e.color }} />
                                    <span className="flex-1 truncate text-muted-foreground">{e.name}</span>
                                    <span className="font-medium tabular-nums">{e.risk_score}</span>
                                    <span className="text-muted-foreground">({e.open_issues})</span>
                                </div>
                            ))}
                            <p className="mt-1 text-muted-foreground">risk score (open issues)</p>
                        </div>
                    )}

                    <svg
                        ref={svgRef}
                        className="w-full overflow-visible"
                        role="img"
                        aria-label="Organisation risk trends – line chart"
                    />

                    {/* Legend */}
                    <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1.5">
                        {data.organizations.map((o, i) => (
                            <div key={o.id} className="flex items-center gap-1.5 text-xs">
                                <span
                                    aria-hidden="true"
                                    className="inline-block h-2 w-4 shrink-0 rounded-sm"
                                    style={{
                                        backgroundColor: d3
                                            .scaleOrdinal(d3.schemeTableau10)
                                            .domain(data.organizations.map((x) => String(x.id)))(String(o.id)),
                                    }}
                                />
                                <span className="text-muted-foreground">{o.name}</span>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
