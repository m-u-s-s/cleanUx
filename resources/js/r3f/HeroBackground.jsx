/* ============================================================================
   CleanUx — R3F hero background (Canvas wrapper, performance-tuned)
   ----------------------------------------------------------------------------
   Performance:
     • dpr clamped to [1, 1.75] (avoids 3x retina overdraw).
     • antialias off (points don't need MSAA), alpha on, high-performance GPU.
     • render loop paused (frameloop="never") whenever the hero is scrolled out
       of view — zero GPU cost off-screen, instant resume on return.
     • a soft brand fog adds depth almost for free.
   ========================================================================= */

import { Canvas } from '@react-three/fiber';
import { useEffect, useRef, useState } from 'react';
import ParticleField from './ParticleField';

export default function HeroBackground({ colors, fog = '#070b14' }) {
    const [active, setActive] = useState(true);
    const wrapRef = useRef(null);

    // Pause rendering when the hero leaves the viewport (zero GPU cost off-screen).
    useEffect(() => {
        const el = wrapRef.current; // the <canvas> element (forwarded by R3F)
        if (!el || !('IntersectionObserver' in window)) return undefined;
        const io = new IntersectionObserver(
            ([entry]) => setActive(entry.isIntersecting),
            { threshold: 0.01 }
        );
        io.observe(el);
        return () => io.disconnect();
    }, []);

    return (
        <Canvas
            ref={wrapRef}
            frameloop={active ? 'always' : 'never'}
            dpr={[1, 1.75]}
            gl={{
                antialias: false,
                alpha: true,
                stencil: false,
                depth: true,
                powerPreference: 'high-performance',
            }}
            camera={{ position: [0, 0, 14], fov: 60, near: 0.1, far: 100 }}
            style={{ position: 'absolute', inset: 0, width: '100%', height: '100%' }}
        >
            {/* Soft brand-tinted depth fog (procedural, no asset). */}
            <fog attach="fog" args={[fog, 14, 34]} />
            <ParticleField colors={colors} />
        </Canvas>
    );
}
