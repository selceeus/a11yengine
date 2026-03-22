import * as d3 from 'd3';
import { useEffect, useRef, useState } from 'react';

import { Skeleton } from '@/components/ui/skeleton';

type Severity = 'critical' | 'high' | 'medium' | 'low';

type SummaryData = {
    critical: number;
    high: number;
    medium: number;
    low: number;
    total: number;
    generated_at: string;
};

type TooltipState = {
    x: number;
    y: number;
    severity: Severity;
    count: number;
};

export type IssueSeverityChartProps = {
    agencyId: number;
    organizationId?: number;
};

const SEVERITIES: Severity[] = ['critical', 'high', 'medium', 'low'];

const SEVERITY_COLORS: Record<Severity, string> = {
    critical: '#dc2626',
    high: '#ea580c',
    medium: '#ca8a04',
    low: '#2563eb',
};

const SEVERITY_LABELS: Record<Severity, string> = {
    critical: 'Critical',
    high: 'High',
    medium: 'Medium',
    low: 'Low',
};

type Segment = { severity: Severity; count: number; x: number; w: number };

function buildSegments(data: SummaryData, totalWidth: number): Segment[] {
    const total = data.total > 0 ? data.total : 1;
    let cumulative = 0;

    return SEVERITIES.map((severity) => {
        const count = data[severity];
        const x = (cumulative / total) * totalWidth;
        const w = (count / total) * totalWidth;
        cumulative += count;
        return { severity, count, x, w };
    });
}

export function IssueSeverityChart({ agencyId, organizationId }: IssueSeverityChartProps) {
    const [data, setData] = useState<SummaryData | null>(null);
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

        const url = organizationId
            ? `/api/organizations/${organizationId}/issues/summary`
            : `/api/agencies/${agencyId}/issues/summary`;

        fetch(url, {
            signal: controller.signal,
            headers: { Accept: 'application/json' },
        })
            .then((res) => {
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                return res.json() as Promise<SummaryData>;
            })
            .then((json) => {
                setData(json);
                setLoading(false);
            })
            .catch((err: unknown) => {
                if (err instanceof Error && err.name !== 'AbortError') {
                    setError('Failed to load issue summary.');
                    setLoading(false);
                }
            });

        return () => controller.abort();
    }, [agencyId, organizationId]);

    // ── D3 render + animate ──────────────────────────────────────────────────
    useEffect(() => {
        if (!data || !svgRef.current || !containerRef.current) return;

        const totalWidth = containerRef.current.getBoundingClientRect().width || 600;
        const barHeight = 44;

        const svg = d3.select(svgRef.current).attr('width', totalWidth).attr('height', barHeight);

        const segments = buildSegments(data, totalWidth);

        const rects = svg
            .selectAll<SVGRectElement, Segment>('rect.seg')
            .data(segments, (d) => d.severity);

        // ENTER — start collapsed at their target x so they grow outward
        const entered = rects
            .enter()
            .append('rect')
            .attr('class', 'seg')
            .attr('y', 0)
            .attr('height', barHeight)
            .attr('fill', (d) => SEVERITY_COLORS[d.severity])
            .attr('x', (d) => d.x)
            .attr('width', 0)
            .style('cursor', 'pointer');

        // Attach tooltip events to enter + update
        const merged = entered.merge(rects);

        merged
            .on('mousemove', (event: MouseEvent, d) => {
                const rect = containerRef.current!.getBoundingClientRect();
                setTooltip({
                    x: event.clientX - rect.left,
                    y: event.clientY - rect.top,
                    severity: d.severity,
                    count: d.count,
                });
            })
            .on('mouseleave', () => setTooltip(null));

        // Animate position + width on every data update
        merged
            .transition()
            .duration(600)
            .ease(d3.easeCubicOut)
            .attr('x', (d) => d.x)
            .attr('width', (d) => d.w);

        // EXIT
        rects.exit().transition().duration(300).attr('width', 0).remove();
    }, [data]);

    // ── Render ───────────────────────────────────────────────────────────────
    if (loading) {
        return <Skeleton className="h-30 w-full rounded-xl" />;
    }

    if (error) {
        return <p className="text-sm text-destructive">{error}</p>;
    }

    if (!data || data.total === 0) {
        return <p className="text-sm text-muted-foreground">No active issues found.</p>;
    }

    return (
        <div ref={containerRef} className="relative w-full select-none">
            {/* Tooltip */}
            {tooltip && (
                <div
                    role="tooltip"
                    className="pointer-events-none absolute z-10 rounded-md border bg-popover px-2.5 py-1.5 text-xs text-popover-foreground shadow-md"
                    style={{ left: tooltip.x + 14, top: tooltip.y - 40 }}
                >
                    <span className="font-semibold">{SEVERITY_LABELS[tooltip.severity]}</span>
                    {': '}
                    {tooltip.count}
                    {' ('}
                    {Math.round((tooltip.count / data.total) * 100)}
                    {'%)'}
                </div>
            )}

            {/* Stacked bar */}
            <svg
                ref={svgRef}
                className="w-full overflow-visible rounded-sm"
                role="img"
                aria-label="Issues by severity – horizontal stacked bar chart"
            />

            {/* Legend */}
            <div className="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2">
                {SEVERITIES.map((s) => (
                    <div key={s} className="flex items-center gap-1.5 text-sm">
                        <span
                            aria-hidden="true"
                            className="inline-block h-2.5 w-2.5 shrink-0 rounded-sm"
                            style={{ backgroundColor: SEVERITY_COLORS[s] }}
                        />
                        <span className="text-muted-foreground">{SEVERITY_LABELS[s]}</span>
                        <span className="font-medium tabular-nums">{data[s]}</span>
                    </div>
                ))}
                <div className="ml-auto text-xs text-muted-foreground">
                    Total: <span className="font-medium tabular-nums">{data.total}</span>
                </div>
            </div>
        </div>
    );
}
