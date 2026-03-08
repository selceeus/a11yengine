import { Html, OrbitControls } from '@react-three/drei';
import { Canvas, useFrame, type ThreeEvent } from '@react-three/fiber';
import { useCallback, useEffect, useRef, useState } from 'react';
import * as THREE from 'three';

import { Skeleton } from '@/components/ui/skeleton';
import { buildRiskMapGrid, riskColor, type PlacedPage, type RiskPage } from '@/lib/riskMapLayout';

// ── RiskBar ───────────────────────────────────────────────────────────────────

type RiskBarProps = {
    item: PlacedPage;
    onHover: (item: PlacedPage | null) => void;
};

function RiskBar({ item, onHover }: RiskBarProps) {
    const meshRef = useRef<THREE.Mesh>(null!);
    const targetY = useRef(item.y);

    // Set initial Three.js transform imperatively — never passed as JSX props
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
            <meshStandardMaterial color={riskColor(item.riskScore)} roughness={0.35} metalness={0.15} />
        </mesh>
    );
}

// ── RiskMapScene ──────────────────────────────────────────────────────────────

type RiskMapSceneProps = {
    items: PlacedPage[];
    hoveredItem: PlacedPage | null;
    onHover: (item: PlacedPage | null) => void;
    rows: number;
};

function RiskMapScene({ items, hoveredItem, onHover, rows }: RiskMapSceneProps) {
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
                <RiskBar key={item.url} item={item} onHover={onHover} />
            ))}
            {hoveredItem !== null && (
                <Html
                    position={[hoveredItem.x * 2, hoveredItem.y + 0.5, hoveredItem.z * 2]}
                    center
                    style={{ pointerEvents: 'none' }}
                >
                    <div className="min-w-[160px] rounded-lg border bg-popover px-3 py-2 text-xs text-popover-foreground shadow-md">
                        <p className="truncate font-semibold">{hoveredItem.url}</p>
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

// ── DashboardRiskMap ──────────────────────────────────────────────────────────

export type DashboardRiskMapProps = {
    siteId: number | null;
};

export function DashboardRiskMap({ siteId }: DashboardRiskMapProps) {
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
                <div className="min-h-[400px] w-full overflow-hidden rounded-xl" style={{ background: 'oklch(0.18 0.02 250)' }}>
                    <Canvas camera={{ position: [9, 20, camZ], fov: 48 }} style={{ height: '400px', width: '100%' }}>
                        <RiskMapScene items={data} hoveredItem={hoveredItem} onHover={setHoveredItem} rows={rows} />
                    </Canvas>
                </div>
            )}
        </div>
    );
}
