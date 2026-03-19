import { Html, OrbitControls } from '@react-three/drei';
import { Canvas, useFrame, type ThreeEvent } from '@react-three/fiber';
import { useCallback, useEffect, useRef, useState } from 'react';
import * as THREE from 'three';

import { Skeleton } from '@/components/ui/skeleton';
import { buildRiskMapGrid, riskColor, type PlacedPage, type RiskPage } from '@/lib/riskMapLayout';

export type PriorityItem = {
    rank: number;
    issue_id: number;
    title: string;
    rule_key: string;
    severity: string;
    risk_reduction_score: number;
    ease_of_remediation: string;
    affected_page_urls: string[];
    quick_win: boolean;
};

export type PriorityRiskMapProps = {
    siteId: number | null;
    priorityItems: PriorityItem[];
};

// ── PriorityBar ───────────────────────────────────────────────────────────────

type PriorityBarProps = {
    item: PlacedPage;
    isPriority: boolean;
    topRank: number | null;
    topScore: number | null;
    onHover: (item: PlacedPage | null) => void;
};

function PriorityBar({ item, isPriority, topRank, topScore, onHover }: PriorityBarProps) {
    const meshRef = useRef<THREE.Mesh>(null!);
    const targetY = useRef(item.y);
    const color = isPriority ? '#f59e0b' : riskColor(item.riskScore);

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
            <meshStandardMaterial
                color={color}
                roughness={0.35}
                metalness={0.15}
                emissive={isPriority ? '#f59e0b' : '#000000'}
                emissiveIntensity={isPriority ? 0.3 : 0}
            />
        </mesh>
    );
}

// ── PriorityRiskMapScene ──────────────────────────────────────────────────────

type PriorityRiskMapSceneProps = {
    items: PlacedPage[];
    hoveredItem: PlacedPage | null;
    onHover: (item: PlacedPage | null) => void;
    priorityPageUrls: Set<string>;
    priorityMeta: Map<string, { rank: number; score: number }>;
    rows: number;
};

function PriorityRiskMapScene({ items, hoveredItem, onHover, priorityPageUrls, priorityMeta, rows }: PriorityRiskMapSceneProps) {
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
            {items.map((item) => {
                const isPriority = priorityPageUrls.has(item.url);
                const meta = priorityMeta.get(item.url);

                return (
                    <PriorityBar
                        key={item.url}
                        item={item}
                        isPriority={isPriority}
                        topRank={meta?.rank ?? null}
                        topScore={meta?.score ?? null}
                        onHover={onHover}
                    />
                );
            })}
            {hoveredItem !== null && (
                <Html
                    position={[hoveredItem.x * 2, hoveredItem.y + 0.5, hoveredItem.z * 2]}
                    center
                    style={{ pointerEvents: 'none' }}
                >
                    <div className="min-w-[180px] rounded-lg border bg-popover px-3 py-2 text-xs text-popover-foreground shadow-md">
                        <p className="truncate font-semibold">{hoveredItem.url}</p>
                        {priorityPageUrls.has(hoveredItem.url) && (
                            <p className="mt-0.5 font-medium text-amber-500">
                                Priority fix &middot; rank #{priorityMeta.get(hoveredItem.url)?.rank}
                                {priorityMeta.get(hoveredItem.url)?.score !== undefined && (
                                    <span className="ml-1 text-muted-foreground">
                                        ({priorityMeta.get(hoveredItem.url)?.score} risk reduction)
                                    </span>
                                )}
                            </p>
                        )}
                        <div className="mt-1.5 grid grid-cols-2 gap-x-3 gap-y-0.5">
                            <span className="text-muted-foreground">Risk score</span>
                            <span className="font-medium tabular-nums">{hoveredItem.riskScore}</span>
                            <span className="text-muted-foreground">Issues</span>
                            <span className="font-medium tabular-nums">{hoveredItem.issueCount}</span>
                            <span className="text-muted-foreground">Lighthouse</span>
                            <span className="font-medium tabular-nums">{hoveredItem.lighthouseAccessibility}</span>
                        </div>
                    </div>
                </Html>
            )}
        </>
    );
}

// ── PriorityLegend ────────────────────────────────────────────────────────────

function PriorityLegend() {
    return (
        <div className="flex flex-wrap items-center gap-4 text-xs text-muted-foreground">
            <span className="flex items-center gap-1.5">
                <span className="inline-block h-3 w-3 rounded-sm bg-amber-400" />
                Priority fix page
            </span>
            <span className="flex items-center gap-1.5">
                <span className="inline-block h-3 w-3 rounded-sm bg-red-500" />
                High risk (&ge;70)
            </span>
            <span className="flex items-center gap-1.5">
                <span className="inline-block h-3 w-3 rounded-sm bg-orange-500" />
                Medium risk (40–69)
            </span>
            <span className="flex items-center gap-1.5">
                <span className="inline-block h-3 w-3 rounded-sm bg-green-500" />
                Low risk (&lt;40)
            </span>
        </div>
    );
}

// ── PriorityRiskMap ───────────────────────────────────────────────────────────

export function PriorityRiskMap({ siteId, priorityItems }: PriorityRiskMapProps) {
    const [data, setData] = useState<PlacedPage[] | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [hoveredItem, setHoveredItem] = useState<PlacedPage | null>(null);

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
                setData(buildRiskMapGrid(json));
                setLoading(false);
            })
            .catch((err: unknown) => {
                if (err instanceof Error && err.name !== 'AbortError') {
                    setError('Failed to load risk map data.');
                    setLoading(false);
                }
            });

        return () => ctrl.abort();
    }, [siteId]);

    const priorityPageUrls = new Set(priorityItems.flatMap((p) => p.affected_page_urls));

    const priorityMeta = new Map<string, { rank: number; score: number }>();
    for (const item of priorityItems) {
        for (const url of item.affected_page_urls) {
            if (!priorityMeta.has(url) || item.rank < (priorityMeta.get(url)?.rank ?? Infinity)) {
                priorityMeta.set(url, { rank: item.rank, score: item.risk_reduction_score });
            }
        }
    }

    if (!siteId) {
        return <p className="text-sm text-muted-foreground">No property selected.</p>;
    }

    const noData = !loading && !error && (!data || data.length === 0);
    const rows = data ? Math.ceil(data.length / 10) : 1;
    const camZ = Math.max(20, rows - 1 + 15);

    return (
        <div className="space-y-3">
            {loading && <Skeleton className="h-96 w-full rounded-xl" />}
            {error && <p className="text-sm text-destructive">{error}</p>}
            {noData && <p className="text-sm text-muted-foreground">No page risk data available for this property.</p>}
            {!loading && !error && data && data.length > 0 && (
                <>
                    <div className="min-h-[400px] w-full overflow-hidden rounded-xl" style={{ background: 'oklch(0.18 0.02 250)' }}>
                        <Canvas camera={{ position: [9, 20, camZ], fov: 48 }} style={{ height: '400px', width: '100%' }}>
                            <PriorityRiskMapScene
                                items={data}
                                hoveredItem={hoveredItem}
                                onHover={setHoveredItem}
                                priorityPageUrls={priorityPageUrls}
                                priorityMeta={priorityMeta}
                                rows={rows}
                            />
                        </Canvas>
                    </div>
                    <PriorityLegend />
                </>
            )}
        </div>
    );
}
