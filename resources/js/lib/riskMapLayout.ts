import * as d3 from 'd3';

export type RiskPage = {
    url: string;
    riskScore: number;
    issueCount: number;
    lighthouseAccessibility: number;
};

export type PlacedPage = RiskPage & { x: number; y: number; z: number };

const heightScale = d3.scaleLinear<number>().domain([0, 100]).range([0.05, 20]);

export function buildRiskMapGrid(pages: RiskPage[]): PlacedPage[] {
    return pages.map((page, i) => ({
        ...page,
        x: i % 10,
        y: heightScale(page.riskScore),
        z: Math.floor(i / 10),
    }));
}

export function riskColor(score: number): string {
    if (score >= 70) return '#ef4444';
    if (score >= 40) return '#f97316';
    return '#22c55e';
}
