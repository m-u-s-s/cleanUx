/* ============================================================================
   Brio — R3F particle field (procedural, GPU-driven)
   ----------------------------------------------------------------------------
   A single THREE.Points cloud animated entirely in the vertex shader, so the
   CPU cost stays flat regardless of particle count (easily 60 FPS):
     • animated particles  → per-particle drift in the vertex shader
     • soft light movement → additive blending + soft round procedural sprites
     • depth effect        → z-spread + size attenuation + depth-based fade
     • subtle camera motion→ camera eased toward pointer + slow sine (useFrame)
   Procedural only: the round glow is computed from gl_PointCoord — no textures,
   no external assets. Brand colours come from the Brio tokens.
   ========================================================================= */

import { useEffect, useMemo, useRef } from 'react';
import { useFrame } from '@react-three/fiber';
import * as THREE from 'three';

// Densité adaptative : pleine sur desktop costaud, réduite sur mobile / peu de
// mémoire (GPU mobiles + fillrate) — l'effet reste identique, juste moins dense.
function particleCount() {
    if (typeof window === 'undefined') return 4800;
    const mem = typeof navigator.deviceMemory === 'number' ? navigator.deviceMemory : 8;
    const narrow = window.innerWidth < 768;
    if (mem <= 4 || narrow) return 2400;
    return 4800;
}
const COUNT = particleCount();            // GPU-cheap; densité selon l'appareil
const SPREAD_X = 15;
const SPREAD_Y = 9;
const SPREAD_Z = 9;            // depth range (±) around the origin

// Palette par défaut (home Brio) ; surchargée par la prop `colors` (ex. hero
// luxe « or champagne »). a = teinte proche, b = highlight, c = profondeur.
const DEFAULT_COLORS = { a: '#ffb648', b: '#4fe3d6', c: '#8b7bff' };

const VERT = /* glsl */ `
    uniform float uTime;
    uniform float uSize;
    uniform float uPixelRatio;
    attribute float aScale;
    attribute float aSeed;
    varying float vDepth;
    varying float vSeed;

    void main() {
        vec3 p = position;

        // Gentle, looping drift — phase offset per particle via aSeed.
        float t = uTime * 0.10;
        float ph = aSeed * 6.2831853;
        p.x += sin(t + ph) * 0.7;
        p.y += cos(t * 0.9 + ph) * 0.6;
        p.z += sin(t * 0.7 + ph * 0.5) * 0.5;

        vec4 mv = modelViewMatrix * vec4(p, 1.0);
        float dist = max(-mv.z, 0.001);

        // Perspective size attenuation (distant particles shrink → depth).
        gl_PointSize = uSize * aScale * uPixelRatio * (14.0 / dist);
        gl_Position = projectionMatrix * mv;

        vDepth = clamp((dist - 5.0) / 22.0, 0.0, 1.0);
        vSeed = aSeed;
    }
`;

const FRAG = /* glsl */ `
    precision highp float;
    uniform vec3 uColorA;
    uniform vec3 uColorB;
    uniform vec3 uColorC;
    varying float vDepth;
    varying float vSeed;

    void main() {
        // Soft round sprite (procedural — no texture).
        vec2 uv = gl_PointCoord - 0.5;
        float d = length(uv);
        if (d > 0.5) discard;
        float alpha = smoothstep(0.5, 0.0, d);
        alpha *= alpha;

        // Colour by seed, drifting toward violet with depth.
        vec3 col = mix(uColorA, uColorB, vSeed);
        col = mix(col, uColorC, smoothstep(0.25, 1.0, vDepth));

        // Fade distant particles for atmospheric depth.
        float depthFade = mix(1.0, 0.22, vDepth);

        gl_FragColor = vec4(col, alpha * depthFade * 0.9);
    }
`;

export default function ParticleField({ colors }) {
    const matRef = useRef();
    const pal = colors || DEFAULT_COLORS;

    // Build geometry + uniforms once.
    const { geometry, uniforms } = useMemo(() => {
        const positions = new Float32Array(COUNT * 3);
        const scales = new Float32Array(COUNT);
        const seeds = new Float32Array(COUNT);

        for (let i = 0; i < COUNT; i++) {
            positions[i * 3 + 0] = (Math.random() * 2 - 1) * SPREAD_X;
            positions[i * 3 + 1] = (Math.random() * 2 - 1) * SPREAD_Y;
            positions[i * 3 + 2] = (Math.random() * 2 - 1) * SPREAD_Z;
            scales[i] = 0.5 + Math.random() * 1.6;
            seeds[i] = Math.random();
        }

        const g = new THREE.BufferGeometry();
        g.setAttribute('position', new THREE.BufferAttribute(positions, 3));
        g.setAttribute('aScale', new THREE.BufferAttribute(scales, 1));
        g.setAttribute('aSeed', new THREE.BufferAttribute(seeds, 1));

        const u = {
            uTime: { value: 0 },
            uSize: { value: 26.0 },
            uPixelRatio: { value: Math.min(typeof window !== 'undefined' ? window.devicePixelRatio : 1, 1.75) },
            uColorA: { value: new THREE.Color(pal.a) },
            uColorB: { value: new THREE.Color(pal.b) },
            uColorC: { value: new THREE.Color(pal.c) },
        };
        return { geometry: g, uniforms: u };
        // pal.* sont statiques par montage (data-attr) → useMemo ne ré-exécute pas.
    }, [pal.a, pal.b, pal.c]);

    // <primitive> geometries are owned by us → dispose on unmount (Livewire nav).
    useEffect(() => () => geometry.dispose(), [geometry]);

    // Stable constructor args so R3F does NOT rebuild the material every render.
    const matArgs = useMemo(
        () => [{ uniforms, vertexShader: VERT, fragmentShader: FRAG }],
        [uniforms]
    );

    useFrame((state, delta) => {
        // Clamp delta so a tab regaining focus doesn't jump the animation.
        const dt = Math.min(delta, 0.05);
        if (matRef.current) matRef.current.uniforms.uTime.value += dt;

        // Subtle camera motion: ease toward pointer + a slow sine wander.
        const t = state.clock.elapsedTime;
        const cam = state.camera;
        const tx = state.pointer.x * 1.6 + Math.sin(t * 0.10) * 0.5;
        const ty = state.pointer.y * 1.0 + Math.cos(t * 0.13) * 0.35;
        const k = 1 - Math.pow(0.001, dt); // frame-rate-independent easing
        cam.position.x += (tx - cam.position.x) * k;
        cam.position.y += (ty - cam.position.y) * k;
        cam.lookAt(0, 0, 0);
    });

    return (
        <points frustumCulled={false}>
            <primitive object={geometry} attach="geometry" />
            <shaderMaterial
                ref={matRef}
                attach="material"
                args={matArgs}
                transparent
                depthWrite={false}
                depthTest={false}
                blending={THREE.AdditiveBlending}
            />
        </points>
    );
}
