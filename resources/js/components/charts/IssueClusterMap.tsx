import { Html, OrbitControls } from '@react-three/drei';
import { Canvas, useFrame, type ThreeEvent } from '@react-three/fiber';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import * as THREE from 'three';

import { buildRiskMapGrid, type PlacedPage, type RiskPage } from '@/lib/riskMapLayout';

const CLUSTER_PALETTE = [
    '#3b82f6', // blue
    '#10b981', // emerald
    '#f59e0b', // amber
    '#ef4444', // red
    '#8b5cf6', // violet
    '#ec4899', // pink
    '#06b6d4', // cyan
    '#84cc16', // lime
    '#f97316', // orange
    '#6366f1', // indigo
] as const;

const UNCLUSTERED_COLOR = '#475569';

export type IssueClusterItem = {
    id: number;
    name: string;
    issue_ids: number[];
};

type ClusteredPage = PlacedPage & { clusterId: number | null; clusterName: string | null };

// ── ClusterLegend ─────────────────────────────────────────────────────────────

function ClusterLegend({ clusters }: { clusters: IssueClusterItem[] }) {
    if (clusters.length === 0) return null;

    return (
        <div className="flex flex-wrap gap-x-4 gap-y-1.5 rounded border bg-card p-3">
            {clusters.map((cluster, idx) => (
                <div key={cluster.id} className="flex items-center gap-1.5">
                    <span
                        className="inline-block h-3 w-3 flex-shrink-0 rounded-sm"
                        style={{ background: CLUSTER_PALETTE[idx % CLUSTER_PALETTE.length] }}
                    />
                    <span className="text-xs text-foreground">{cluster.name}</span>
                </div>
            ))}
            <div className="flex items-center gap-1.5">
                <span className="inline-block h-3 w-3 flex-shrink-0 rounded-sm" style={{ background: UNCLUSTERED_COLOR }} />
                <span className="text-xs text-muted-foreground">Unclustered</span>
            </div>
        </div>
    );
}

// ── ClusteredBar ──────────────────────────────────────────────────────────────

type ClusteredBarProps = {
    item: ClusteredPage;
    color: string;
    onHover: (item: ClusteredPage | null) => void;
};

function ClusteredBar({ item, color, onHover }: ClusteredBarProps) {
    const meshRef = useRef<THREE.Mesh>(null!);
    const targetY = useRef(item.y);

    useEffect(() => {
        const mesh = meshRef.current;
        if (mesh) {
            mesh.scale.set(1, 0.05, 1);
            mesh.position.set(item.x * 2, 0.025, item.z * 2);
        }
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    useEffect(() => {
        targetY.current = item.y;
    }, [item.y]);

    useFrame((_, dt) => {
        const mesh = meshRef.current;
        if (!mesh) return;
        const t = Math.min(1, dt * 5);
        mesh.scale.y += (targetY.current - mesh.scale.y) * t;
        mesh.position.y = mesh.scale.y * 0.5;
    });

    const handleOver = useCallback(
        (e: ThreeEvent<PointerEvent>) => {
            e.stopPropagation();
            onHover(item);
        },
        [item, onHover],
    );

    const handleOut = useCallback(() => onHover(null), [onHover]);

    return (
        <mesh ref={meshRef} onPointerOver={handleOver} onPointerOut={handleOut}>
            <boxGeometry args={[1, 1, 1]} />
            <meshStandardMaterial color={color} roughness={0.35} metalness={0.15} />
        </mesh>
    );
}

// ── IssueClusterMapScene ──────────────────────────────────────────────────────

type SceneProps = {
    items: ClusteredPage[];
    colorMap: Record<string, string>;
    hoveredItem: ClusteredPage | null;
    onHover: (item: ClusteredPage | null) => void;
    rows: number;
};

function IssueClusterMapScene({ items, colorMap, hoveredItem, onHover, rows }: SceneProps) {
    const centerX = 9;
    const centerZ = rows - 1;
    const gridSize = Math.max(22, rows * 2 + 4);

    return (
        <>
            <ambientLight intensity={0.65} />
            <pointLight position={[centerX, 20, centerZ]} intensity={120} castShadow />
            <gridHelper args={[gridSize, gridSize, '#334155', '#1e293b']} position={[centerX, 0, centerZ]} />
            <OrbitControls
                makeDefault
                target={[centerX, 5, centerZ]}
                maxPolarAngle={Math.PI / 2.05}
                minDistance={3}
                maxDistance={100}
            />
            {items.map((item) => (
                <ClusteredBar key={item.url} item={item} color={colorMap[item.url] ?? UNCLUSTERED_COLOR} onHover={onHover} />
            ))}
            {hoveredItem !== null && (
                <Html
                    position={[hoveredItem.x * 2, hoveredItem.y + 0.5, hoveredItem.z * 2]}
                    center
                    style={{ pointerEvents: 'none' }}
                >
                    <div className="min-w-[160px] rounded border bg-popover px-3 py-2 text-xs text-popover-foreground shadow-md">
                        <p className="truncate font-semibold">{hoveredItem.url}</p>
                        <div className="mt-1.5 grid grid-cols-2 gap-x-3 gap-y-0.5">
                            {hoveredItem.clusterName !== null && (
                                <>
                                    <span className="text-muted-foreground">Cluster</span>
                                    <span className="font-medium">{hoveredItem.clusterName}</span>
                                </>
                            )}
                            <span className="text-muted-foreground">Risk score</span>
                            <span className="font-medium tabular-nums">{hoveredItem.riskScore}</span>
                            <span className="text-muted-foreground">Issues</span>
                            <span className="font-medium tabular-nums">{hoveredItem.issueCount}</span>
                        </div>
                    </div>
                </Html>
            )}
        </>
    );
}

// ── IssueClusterMap ───────────────────────────────────────────────────────────

export type IssueClusterMapProps = {
    pages: RiskPage[];
    clusters: IssueClusterItem[];
    /** Maps issue_id → page URL, used to assign pages to clusters */
    issuePageMap: Record<number, string>;
};

export function IssueClusterMap({ pages, clusters, issuePageMap }: IssueClusterMapProps) {
    const [hoveredItem, setHoveredItem] = useState<ClusteredPage | null>(null);

    const { placedPages, colorMap } = useMemo(() => {
        // Build url → { id, name, paletteIndex } from cluster issue_ids
        const pageClusterMap = new Map<string, { id: number; name: string; index: number }>();

        clusters.forEach((cluster, idx) => {
            cluster.issue_ids.forEach((issueId) => {
                const url = issuePageMap[issueId];
                if (url && !pageClusterMap.has(url)) {
                    pageClusterMap.set(url, { id: cluster.id, name: cluster.name, index: idx });
                }
            });
        });

        const placed = buildRiskMapGrid(pages).map((p): ClusteredPage => {
            const cluster = pageClusterMap.get(p.url) ?? null;
            return { ...p, clusterId: cluster?.id ?? null, clusterName: cluster?.name ?? null };
        });

        const colors: Record<string, string> = {};
        placed.forEach((p) => {
            const cluster = pageClusterMap.get(p.url);
            colors[p.url] = cluster ? (CLUSTER_PALETTE[cluster.index % CLUSTER_PALETTE.length] ?? UNCLUSTERED_COLOR) : UNCLUSTERED_COLOR;
        });

        return { placedPages: placed, colorMap: colors };
    }, [pages, clusters, issuePageMap]);

    if (pages.length === 0) {
        return <p className="text-sm text-muted-foreground">No page data available for the cluster map.</p>;
    }

    const rows = Math.ceil(placedPages.length / 10);
    const camZ = Math.max(20, rows - 1 + 15);

    return (
        <div className="space-y-3">
            <ClusterLegend clusters={clusters} />
            <div className="min-h-[400px] w-full overflow-hidden rounded" style={{ background: 'oklch(0.18 0.02 250)' }}>
                <Canvas camera={{ position: [9, 20, camZ], fov: 48 }} style={{ height: '400px', width: '100%' }}>
                    <IssueClusterMapScene
                        items={placedPages}
                        colorMap={colorMap}
                        hoveredItem={hoveredItem}
                        onHover={setHoveredItem}
                        rows={rows}
                    />
                </Canvas>
            </div>
        </div>
    );
}
