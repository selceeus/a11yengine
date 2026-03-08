import * as d3 from 'd3';

import type { RiskPage } from './riskMapLayout';

// ── Constants ─────────────────────────────────────────────────────────────────

export const GLOBE_RADIUS = 5;
export const MARKER_RADIUS = 0.06;
const MAX_MARKERS = 300;

// ── Types ─────────────────────────────────────────────────────────────────────

export type GlobeMarker = RiskPage & {
    /** First URL path segment, e.g. "blog", "products", "root" */
    sectionKey: string;
    /** Spherical latitude in radians [-π/2, π/2] */
    lat: number;
    /** Spherical longitude in radians [-π, π] */
    lon: number;
    /** Extrusion height above sphere surface */
    height: number;
    /** Pre-computed world X at midpoint of extrusion (used for tooltip) */
    worldX: number;
    /** Pre-computed world Y at midpoint of extrusion */
    worldY: number;
    /** Pre-computed world Z at midpoint of extrusion */
    worldZ: number;
};

// ── D3 Scales ──────────────────────────────────────────────────────────────────

export const heightScale = d3.scaleLinear<number>().domain([0, 100]).range([0.1, 1.8]).clamp(true);

export const colorScale = d3
    .scaleSequential(d3.interpolateRgbBasis(['#22c55e', '#eab308', '#ef4444']))
    .domain([0, 100])
    .clamp(true);

// ── Helpers ────────────────────────────────────────────────────────────────────

/**
 * Extract the first non-empty path segment from a URL string.
 * "/blog/2024/post-title" → "blog"
 * "/" or "" → "root"
 */
export function extractSection(url: string): string {
    try {
        // Handle relative paths (no scheme)
        const pathname = url.startsWith('http') ? new URL(url).pathname : url.split('?')[0] ?? url;
        const parts = pathname.split('/').filter(Boolean);
        return parts[0] ?? 'root';
    } catch {
        return 'root';
    }
}

/** Convert spherical (lat, lon) at radius r to Cartesian (x, y, z). */
function sphericalToCartesian(lat: number, lon: number, r: number): [number, number, number] {
    const cosLat = Math.cos(lat);
    return [r * cosLat * Math.sin(lon), r * Math.sin(lat), r * cosLat * Math.cos(lon)];
}

// ── Main Transform ─────────────────────────────────────────────────────────────

/**
 * Convert an array of RiskPages into GlobeMarkers positioned on the sphere.
 *
 * Strategy:
 * - Cap to top 300 pages by riskScore (descending)
 * - Group by URL section (first path segment)
 * - Sections spread evenly across longitudes
 * - Pages within a section spread across latitudes (±35° band, polar caps free)
 */
export function buildGlobeMarkers(pages: RiskPage[]): GlobeMarker[] {
    // Cap and sort
    const top = [...pages].sort((a, b) => b.riskScore - a.riskScore).slice(0, MAX_MARKERS);

    // Group by section
    const groups = new Map<string, RiskPage[]>();
    for (const page of top) {
        const key = extractSection(page.url);
        const list = groups.get(key) ?? [];
        list.push(page);
        groups.set(key, list);
    }

    // Sort sections alphabetically for deterministic positioning
    const sortedSections = [...groups.keys()].sort();
    const totalSections = sortedSections.length;

    const markers: GlobeMarker[] = [];

    sortedSections.forEach((sectionKey, sectionIdx) => {
        const pagesInSection = groups.get(sectionKey)!;
        const sectionTotal = pagesInSection.length;

        // Longitude: spread evenly from -π to +π
        const lon = totalSections > 1 ? ((sectionIdx / totalSections) * 2 * Math.PI) - Math.PI : 0;

        pagesInSection.forEach((page, pageIdx) => {
            // Latitude: spread ±(π * 0.35) within each section; single page → equator
            const lat =
                sectionTotal > 1
                    ? ((pageIdx / (sectionTotal - 1)) - 0.5) * Math.PI * 0.7
                    : 0;

            const height = heightScale(page.riskScore);
            const midR = GLOBE_RADIUS + height / 2;
            const [worldX, worldY, worldZ] = sphericalToCartesian(lat, lon, midR);

            markers.push({
                ...page,
                sectionKey,
                lat,
                lon,
                height,
                worldX,
                worldY,
                worldZ,
            });
        });
    });

    return markers;
}
