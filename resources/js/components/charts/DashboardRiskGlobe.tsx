import { Html, OrbitControls } from '@react-three/drei';
import { Canvas, useFrame, type ThreeEvent } from '@react-three/fiber';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import * as THREE from 'three';

import { Skeleton } from '@/components/ui/skeleton';
import {
    GLOBE_RADIUS,
    MARKER_RADIUS,
    buildGlobeMarkers,
    colorScale,
    type GlobeMarker,
} from '@/lib/globeLayout';
import type { RiskPage } from '@/lib/riskMapLayout';

// ── Module-scope scratch objects (avoids per-frame GC allocations) ────────────

const _pos = new THREE.Vector3();
const _norm = new THREE.Vector3();
const _up = new THREE.Vector3(0, 1, 0);
const _quat = new THREE.Quaternion();
const _scale = new THREE.Vector3();
const _mat = new THREE.Matrix4();
const _color = new THREE.Color();

// ── Helpers ───────────────────────────────────────────────────────────────────

const CYLINDER_SEGS = 6; // Hexagonal cross-section — good detail/perf balance
const MAX_INSTANCES = 300;

/**
 * Write the InstancedMesh matrix for index `i` so the cylinder sits on the
 * sphere surface at (lat, lon), extruding `height` units outward.
 */
function applyMarkerMatrix(
    mesh: THREE.InstancedMesh,
    i: number,
    lat: number,
    lon: number,
    height: number,
): void {
    const midR = GLOBE_RADIUS + height / 2;
    const cosLat = Math.cos(lat);
    _pos.set(midR * cosLat * Math.sin(lon), midR * Math.sin(lat), midR * cosLat * Math.cos(lon));
    _norm.copy(_pos).normalize();
    _quat.setFromUnitVectors(_up, _norm);
    _scale.set(MARKER_RADIUS * 2, height, MARKER_RADIUS * 2);
    _mat.compose(_pos, _quat, _scale);
    mesh.setMatrixAt(i, _mat);
}

// ── RiskTooltip ───────────────────────────────────────────────────────────────

type RiskTooltipProps = { marker: GlobeMarker };

function RiskTooltip({ marker }: RiskTooltipProps) {
    return (
        <div
            role="tooltip"
            className="pointer-events-none min-w-43 rounded-lg border bg-popover px-3 py-2 text-xs text-popover-foreground shadow-md"
        >
            <p className="truncate font-semibold" title={marker.url}>
                {marker.url}
            </p>
            <p className="mb-1.5 truncate text-muted-foreground">{marker.sectionKey}</p>
            <div className="grid grid-cols-2 gap-x-3 gap-y-0.5">
                <span className="text-muted-foreground">Risk score</span>
                <span className="font-medium tabular-nums">{marker.riskScore}</span>
                <span className="text-muted-foreground">Issues</span>
                <span className="font-medium tabular-nums">{marker.issueCount}</span>
                <span className="text-muted-foreground">Lighthouse</span>
                <span className="font-medium tabular-nums">{marker.lighthouseAccessibility}</span>
            </div>
        </div>
    );
}

// ── GlobeMarkersLayer ─────────────────────────────────────────────────────────

type GlobeMarkersLayerProps = {
    markers: GlobeMarker[];
    onHover: (marker: GlobeMarker | null) => void;
};

function GlobeMarkersLayer({ markers, onHover }: GlobeMarkersLayerProps) {
    const meshRef = useRef<THREE.InstancedMesh>(null!);
    const currentHeights = useRef(new Float32Array(MAX_INSTANCES));
    const targetHeights = useRef(new Float32Array(MAX_INSTANCES));

    // Initialise and refresh whenever markers change
    useEffect(() => {
        const mesh = meshRef.current;
        if (!mesh) return;

        const count = markers.length;
        mesh.count = count;

        // Initialise heights at minimum so they animate in from small
        const minHeight = 0.1;
        currentHeights.current.fill(minHeight, 0, count);

        for (let i = 0; i < count; i++) {
            const m = markers[i];
            targetHeights.current[i] = m.height;

            // Set initial matrix imperatively (minHeight, correct orientation)
            applyMarkerMatrix(mesh, i, m.lat, m.lon, minHeight);

            // Set color from D3 gradient
            _color.set(colorScale(m.riskScore));
            mesh.setColorAt(i, _color);
        }

        mesh.instanceMatrix.needsUpdate = true;
        if (mesh.instanceColor) mesh.instanceColor.needsUpdate = true;
    }, [markers]);

    useFrame((_, dt) => {
        const mesh = meshRef.current;
        if (!mesh || markers.length === 0) return;

        const t = Math.min(1, dt * 5);
        let dirty = false;

        for (let i = 0; i < markers.length; i++) {
            const current = currentHeights.current[i];
            const target = targetHeights.current[i];
            const next = current + (target - current) * t;
            if (Math.abs(next - current) > 0.0001) {
                currentHeights.current[i] = next;
                applyMarkerMatrix(mesh, i, markers[i].lat, markers[i].lon, next);
                dirty = true;
            }
        }

        if (dirty) mesh.instanceMatrix.needsUpdate = true;
    });

    const handleOver = useCallback(
        (e: ThreeEvent<PointerEvent>) => {
            e.stopPropagation();
            const id = e.instanceId;
            if (id !== undefined && id < markers.length) {
                onHover(markers[id]);
            }
        },
        [markers, onHover],
    );

    const handleOut = useCallback(() => onHover(null), [onHover]);

    const cylinderArgs = useMemo(
        () => [MARKER_RADIUS, MARKER_RADIUS, 1, CYLINDER_SEGS] as [number, number, number, number],
        [],
    );

    return (
        <instancedMesh
            ref={meshRef}
            args={[undefined, undefined, MAX_INSTANCES]}
            onPointerOver={handleOver}
            onPointerOut={handleOut}
        >
            <cylinderGeometry args={cylinderArgs} />
            <meshStandardMaterial roughness={0.4} metalness={0.1} />
        </instancedMesh>
    );
}

// ── RiskGlobeScene ────────────────────────────────────────────────────────────

type RiskGlobeSceneProps = {
    markers: GlobeMarker[];
    hoveredMarker: GlobeMarker | null;
    onHover: (marker: GlobeMarker | null) => void;
};

function RiskGlobeScene({ markers, hoveredMarker, onHover }: RiskGlobeSceneProps) {
    return (
        <>
            {/* Lighting */}
            <ambientLight intensity={0.6} />
            <pointLight position={[15, 15, 15]} intensity={120} castShadow />
            <pointLight position={[-10, 5, -12]} intensity={40} color="#c7d2fe" />

            {/* Base planet sphere */}
            <mesh>
                <sphereGeometry args={[GLOBE_RADIUS, 64, 64]} />
                <meshStandardMaterial color="#0a1128" roughness={0.7} metalness={0.15} />
            </mesh>

            {/* Subtle atmosphere wireframe */}
            <mesh>
                <sphereGeometry args={[GLOBE_RADIUS + 0.08, 32, 32]} />
                <meshStandardMaterial color="#3b82f6" wireframe transparent opacity={0.06} />
            </mesh>

            {/* Page risk markers */}
            <GlobeMarkersLayer markers={markers} onHover={onHover} />

            {/* Controls */}
            <OrbitControls makeDefault minDistance={8} maxDistance={30} />

            {/* Scene-level tooltip — not inside a scaled mesh to avoid distortion */}
            {hoveredMarker !== null && (
                <Html
                    position={[hoveredMarker.worldX, hoveredMarker.worldY + hoveredMarker.height * 0.5 + 0.4, hoveredMarker.worldZ]}
                    center
                    style={{ pointerEvents: 'none' }}
                    zIndexRange={[100, 0]}
                >
                    <RiskTooltip marker={hoveredMarker} />
                </Html>
            )}
        </>
    );
}

// ── DashboardRiskGlobe (export) ───────────────────────────────────────────────

export type DashboardRiskGlobeProps = {
    siteId: number | null;
};

export function DashboardRiskGlobe({ siteId }: DashboardRiskGlobeProps) {
    const [markers, setMarkers] = useState<GlobeMarker[] | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [hoveredMarker, setHoveredMarker] = useState<GlobeMarker | null>(null);

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
                setMarkers(buildGlobeMarkers(json));
                setLoading(false);
            })
            .catch((err: unknown) => {
                if (err instanceof Error && err.name !== 'AbortError') {
                    setError('Failed to load globe data.');
                    setLoading(false);
                }
            });

        return () => ctrl.abort();
    }, [siteId]);

    if (!siteId) {
        return <p className="text-sm text-muted-foreground">No property selected.</p>;
    }

    const noData = !loading && !error && (!markers || markers.length === 0);

    return (
        <div className="space-y-3">
            {loading && <Skeleton className="h-150 w-full rounded-xl" />}
            {error && <p className="text-sm text-destructive">{error}</p>}
            {noData && <p className="text-sm text-muted-foreground">No page risk data available for this property.</p>}
            {!loading && !error && markers && markers.length > 0 && (
                <div
                    className="w-full overflow-hidden rounded-xl"
                    style={{ background: 'oklch(0.18 0.02 250)', height: '600px' }}
                >
                    <Canvas camera={{ position: [0, 0, 18], fov: 48 }} style={{ height: '600px', width: '100%' }}>
                        <RiskGlobeScene
                            markers={markers}
                            hoveredMarker={hoveredMarker}
                            onHover={setHoveredMarker}
                        />
                    </Canvas>
                </div>
            )}
        </div>
    );
}
