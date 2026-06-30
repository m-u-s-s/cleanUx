/* ============================================================================
   useImageReveal — révélation d'image au scroll (hook React réutilisable).
   Fin wrapper au-dessus du cœur partagé resources/js/image-reveal-core.js
   (IntersectionObserver + CSS transitions, parallaxe gatée par visibilité).

   Effets : 'clip' | 'mask' | 'blur' | 'scale' | 'fade' (composables) + parallax.
   À poser sur un <figure> qui contient un <img>.

   Usage :
     const ref = useImageReveal({ effects: ['clip', 'blur'], parallax: 0.2 });
     return <figure ref={ref}><img src="…" alt="…" /></figure>;

   prefers-reduced-motion -> image visible, aucune animation (géré par le cœur).
   ========================================================================= */
import { useEffect, useLayoutEffect, useRef } from 'react';
import { observeImageReveal } from '../image-reveal-core';
import { useReducedMotion } from './useReducedMotion';

const useIsoLayoutEffect = typeof window !== 'undefined' ? useLayoutEffect : useEffect;

export function useImageReveal(options = {}) {
    const {
        effects = ['clip', 'fade'],
        parallax = 0,
        once = true,
        threshold = 0.2,
        rootMargin = '0px 0px -10% 0px',
    } = options;

    const ref = useRef(null);
    const reduced = useReducedMotion();

    useIsoLayoutEffect(() => {
        if (!ref.current) return undefined;
        return observeImageReveal(ref.current, { effects, parallax, once, threshold, rootMargin });
        // observeImageReveal lit lui-même reduced-motion ; `reduced` dans les deps
        // ré-exécute au changement de préférence.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [reduced, effects.join(' '), parallax, once, threshold, rootMargin]);

    return ref;
}

export default useImageReveal;
