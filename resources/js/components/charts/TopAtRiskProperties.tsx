import * as PropertyController from '@/actions/App/Http/Controllers/PropertyController';
import { Card } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { router } from '@inertiajs/react';
import { OrbitControls } from '@react-three/drei';
import { Canvas, useFrame, type ThreeEvent } from '@react-three/fiber';
import { useCallback, useEffect, useRef, useState } from 'react';
import * as THREE from 'three';

// ── Types ─────────────────────────────────────────────────────────────────────

type Severity = 'critical' | 'high' | 'medium' | 'low';

type PropertyItem = {
    id: number;
    name: string;
    organization_id: number;
    organization_name: string | null;
    risk_score: number;
    open_issue_count: number;
    highest_severity: Severity | null;
};

type TopRiskData = {
    properties: PropertyItem[];
    generated_at: string;
};

type HoverState = {
    property: PropertyItem;
    x: number;
    y: number;
} | null;

type PositionedProperty = PropertyItem & { posX: number };

// ── Constants ─────────────────────────────────────────────────────────────────

const MAX_HEIGHT = 5;
const CUBE_WIDTH = 0.85;
const X_STEP = 1.4;
const GROUP_GAP = 1.0;

const SEVERITY_COLORS: Record<Severity, string> = {
    critical: '#ef4444',
    high: '#f97316',
    medium: '#eab308',
    low: '#22c55e',
};

const FALLBACK_COLOR = '#6b7280';

function severityHex(s: Severity | null): string {
    return s ? SEVERITY_COLORS[s] : FALLBACK_COLOR;
}

// ── Layout ────────────────────────────────────────────────────────────────────

function buildLayout(properties: PropertyItem[]): PositionedProperty[] {
    const groups = new Map<number, PropertyItem[]>();
    for (const p of properties) {
        const list = groups.get(p.organization_id) ?? [];
        list.push(p);
        groups.set(p.organization_id, list);
    }

    const result: PositionedProperty[] = [];
    let cursor = 0;

    for (const props of groups.values()) {
        props.forEach((p, i) => result.push({ ...p, posX: cursor + i * X_STEP }));
        cursor += props.length * X_STEP + GROUP_GAP;
    }

    return result;
}

// ── PropertyCube ──────────────────────────────────────────────────────────────

type PropertyCubeProps = {
    item: PositionedProperty;
    maxRisk: number;
    onHover: (item: PropertyItem | null, clientX?: number, clientY?: number) => void;
    onClick: (item: PropertyItem) => void;
};

function PropertyCube({ item, maxRisk, onHover, onClick }: PropertyCubeProps) {
    const meshRef = useRef<THREE.Mesh>(null!);
    const matRef = useRef<THREE.MeshStandardMaterial>(null!);

    // Animation targets live in refs so R3F never touches them via reconciler
    const targetHeight = useRef(0.05);
    const targetColor = useRef(new THREE.Color(severityHex(item.highest_severity)));
    const lerpColor = useRef(new THREE.Color(severityHex(item.highest_severity)));

    // Set initial Three.js transform imperatively — never passed as JSX props
    useEffect(() => {
        const mesh = meshRef.current;
        if (mesh) {
            mesh.position.set(item.posX, 0.025, 0);
            mesh.scale.set(1, 0.05, 1);
        }
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    // Update x when layout changes
    useEffect(() => {
        const mesh = meshRef.current;
        if (mesh) mesh.position.x = item.posX;
    }, [item.posX]);

    // Update animation targets when data changes
    useEffect(() => {
        targetHeight.current = maxRisk > 0 ? Math.max(0.05, (item.risk_score / maxRisk) * MAX_HEIGHT) : 0.05;
        targetColor.current.set(severityHex(item.highest_severity));
    }, [item.risk_score, item.highest_severity, maxRisk]);

    useFrame((_, dt) => {
        const mesh = meshRef.current;
        const mat = matRef.current;
        if (!mesh || !mat) return;

        const t = Math.min(1, dt * 5);
        mesh.scale.y += (targetHeight.current - mesh.scale.y) * t;
        mesh.position.y = mesh.scale.y * 0.5;
        lerpColor.current.lerp(targetColor.current, t);
        mat.color.copy(lerpColor.current);
    });

    const handleOver = useCallback(
        (e: ThreeEvent<PointerEvent>) => {
            e.stopPropagation();
            onHover(item, e.nativeEvent.clientX, e.nativeEvent.clientY);
        },
        [item, onHover],
    );

    const handleMove = useCallback(
        (e: ThreeEvent<PointerEvent>) => {
            e.stopPropagation();
            onHover(item, e.nativeEvent.clientX, e.nativeEvent.clientY);
        },
        [item, onHover],
    );

    const handleOut = useCallback(() => onHover(null), [onHover]);

    const handleClick = useCallback(
        (e: ThreeEvent<MouseEvent>) => {
            e.stopPropagation();
            onClick(item);
        },
        [item, onClick],
    );

    return (
        <mesh
            ref={meshRef}
            onPointerOver={handleOver}
            onPointerMove={handleMove}
            onPointerOut={handleOut}
            onClick={handleClick}
        >
            <boxGeometry args={[CUBE_WIDTH, 1, CUBE_WIDTH]} />
            <meshStandardMaterial
                ref={matRef}
                color={severityHex(item.highest_severity)}
                roughness={0.35}
                metalness={0.15}
            />
        </mesh>
    );
}

// ── Scene ─────────────────────────────────────────────────────────────────────

type SceneProps = {
    items: PositionedProperty[];
    maxRisk: number;
    sceneCenterX: number;
    onHover: (item: PropertyItem | null, clientX?: number, clientY?: number) => void;
    onClick: (item: PropertyItem) => void;
};

function Scene({ items, maxRisk, sceneCenterX, onHover, onClick }: SceneProps) {
    const sceneWidth = items.length > 0 ? items[items.length - 1].posX + X_STEP : 10;

    return (
        <>
            <ambientLight intensity={0.65} />
            <pointLight position={[sceneCenterX, 10, 8]} intensity={90} castShadow />
            <pointLight position={[sceneCenterX - 4, 5, -6]} intensity={40} color="#c7d2fe" />
            <gridHelper
                args={[sceneWidth + 4, Math.ceil(sceneWidth + 4), '#334155', '#1e293b']}
                position={[sceneCenterX, 0, 0]}
            />
            {items.map((item) => (
                <PropertyCube key={item.id} item={item} maxRisk={maxRisk} onHover={onHover} onClick={onClick} />
            ))}
            <OrbitControls
                makeDefault
                target={[sceneCenterX, MAX_HEIGHT * 0.3, 0]}
                maxPolarAngle={Math.PI / 2.05}
                minDistance={3}
                maxDistance={40}
            />
        </>
    );
}

// ── Main component ────────────────────────────────────────────────────────────

export type TopAtRiskPropertiesProps = {
    agencyId: number;
};

export function TopAtRiskProperties({ agencyId }: TopAtRiskPropertiesProps) {
    const [data, setData] = useState<TopRiskData | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [hover, setHover] = useState<HoverState>(null);

    const containerRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const ctrl = new AbortController();
        setLoading(true);
        setError(null);

        fetch(`/api/agencies/${agencyId}/properties/top-risk`, {
            signal: ctrl.signal,
            headers: { Accept: 'application/json' },
        })
            .then((res) => {
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                return res.json() as Promise<TopRiskData>;
            })
            .then((json) => {
                setData(json);
                setLoading(false);
            })
            .catch((err: unknown) => {
                if (err instanceof Error && err.name !== 'AbortError') {
                    setError('Failed to load property risk data.');
                    setLoading(false);
                }
            });

        return () => ctrl.abort();
    }, [agencyId]);

    const handleHover = useCallback(
        (item: PropertyItem | null, clientX?: number, clientY?: number) => {
            if (!item || clientX === undefined || clientY === undefined) {
                setHover(null);
                return;
            }
            const rect = containerRef.current?.getBoundingClientRect();
            if (!rect) return;
            setHover({ property: item, x: clientX - rect.left, y: clientY - rect.top });
        },
        [],
    );

    const handleClick = useCallback((item: PropertyItem) => {
        router.visit(PropertyController.show(item.id).url);
    }, []);

    const items = data ? buildLayout(data.properties) : [];
    const maxRisk = data ? Math.max(...data.properties.map((p) => p.risk_score), 1) : 1;
    const sceneCenterX = items.length > 0 ? items[items.length - 1].posX / 2 : 0;

    const noData = !loading && !error && (!data || data.properties.length === 0);

    // Camera position computed once from layout when data first arrives
    const camX = sceneCenterX;
    const camZ = Math.max(10, items.length * X_STEP * 0.7);

    return (
        <div className="space-y-3">
            {loading && <Skeleton className="h-72 w-full rounded" />}
            {error && <p className="text-sm text-destructive">{error}</p>}
            {noData && <p className="text-sm text-muted-foreground">No property risk data yet.</p>}

            {!loading && !error && data && data.properties.length > 0 && (
                <>
                    <div
                        ref={containerRef}
                        className="relative h-72 w-full overflow-hidden rounded"
                        style={{ background: 'oklch(0.18 0.02 250)' }}
                    >
                        <Canvas
                            camera={{ position: [camX, MAX_HEIGHT + 2, camZ], fov: 48 }}
                            shadows
                            className="absolute inset-0"
                        >
                            <Scene
                                items={items}
                                maxRisk={maxRisk}
                                sceneCenterX={sceneCenterX}
                                onHover={handleHover}
                                onClick={handleClick}
                            />
                        </Canvas>

                        {/* Hover tooltip */}
                        {hover && (
                            <div
                                role="tooltip"
                                className="pointer-events-none absolute z-10 min-w-[172px] rounded border bg-popover px-3 py-2 text-xs text-popover-foreground shadow-md"
                                style={{ left: hover.x + 14, top: Math.max(8, hover.y - 80) }}
                            >
                                <p className="truncate font-semibold">{hover.property.name}</p>
                                <p className="truncate text-muted-foreground">{hover.property.organization_name}</p>
                                <div className="mt-1.5 grid grid-cols-2 gap-x-3 gap-y-0.5">
                                    <span className="text-muted-foreground">Risk score</span>
                                    <span className="font-medium tabular-nums">{hover.property.risk_score}</span>
                                    <span className="text-muted-foreground">Open issues</span>
                                    <span className="font-medium tabular-nums">{hover.property.open_issue_count}</span>
                                    {hover.property.highest_severity && (
                                        <>
                                            <span className="text-muted-foreground">Severity</span>
                                            <span
                                                className="font-medium capitalize"
                                                style={{ color: SEVERITY_COLORS[hover.property.highest_severity] }}
                                            >
                                                {hover.property.highest_severity}
                                            </span>
                                        </>
                                    )}
                                </div>
                                <p className="mt-1.5 text-muted-foreground/70">Click to open →</p>
                            </div>
                        )}

                        {/* Severity legend */}
                        <div className="absolute bottom-2 right-2 flex flex-wrap gap-x-3 gap-y-1 rounded bg-black/40 px-2 py-1 backdrop-blur-sm">
                            {(Object.entries(SEVERITY_COLORS) as [Severity, string][]).map(([severity, color]) => (
                                <div key={severity} className="flex items-center gap-1 text-xs">
                                    <span className="inline-block h-2.5 w-2.5 rounded-sm" style={{ backgroundColor: color }} />
                                    <span className="capitalize text-white/70">{severity}</span>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Property name labels row */}
                    <div className="flex flex-wrap gap-2">
                        {data.properties.map((p) => (
                            <button
                                key={p.id}
                                type="button"
                                onClick={() => handleClick(p)}
                                className="flex items-center gap-1.5 rounded border px-2 py-1 text-xs transition-colors hover:bg-muted"
                            >
                                <span
                                    className="inline-block h-2 w-2 shrink-0 rounded-sm"
                                    style={{ backgroundColor: severityHex(p.highest_severity) }}
                                />
                                <span className="max-w-[140px] truncate text-muted-foreground">{p.name}</span>
                            </button>
                        ))}
                    </div>
                </>
            )}
        </div>
    );
}
