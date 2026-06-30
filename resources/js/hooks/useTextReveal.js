/* ============================================================================
   useTextReveal — révélation de texte cinématique (hook React réutilisable).
   ----------------------------------------------------------------------------
   Fin wrapper React au-dessus du moteur partagé resources/js/text-reveal-core.js
   (Web Animations API native — aucune dépendance d'animation) :
     · découpage en lignes ou mots                            -> "line splitting"
     · cascade décalée                                        -> "stagger"
     · révélation par clip-path (wipe vertical)               -> "clip-path reveal"
     · fondu d'opacité + léger translate (en em -> fluide)    -> "opacity transition"
     · re-découpage au resize / swap de police                -> "responsive typography"

   useLayoutEffect : l'état initial masqué est posé AVANT le premier paint -> pas
   de flash. prefers-reduced-motion -> aucun découpage, texte 100 % visible.

   Usage :
     const ref = useTextReveal({ granularity: 'line', stagger: 0.12 });
     return <h1 ref={ref}>Texte à révéler</h1>;

   Options (toutes optionnelles) : granularity ('line'|'word'), trigger
   ('inview'|'immediate'), threshold, once, stagger, duration, delay, distance,
   clip, easing. (L'option `lines` du cœur est réservée aux intégrations vanilla.)
   ========================================================================= */
import { useEffect, useLayoutEffect, useRef } from 'react';
import { revealText } from '../text-reveal-core';
import { useReducedMotion } from './useReducedMotion';

// useLayoutEffect côté client, useEffect côté serveur (évite le warning SSR).
const useIsoLayoutEffect = typeof window !== 'undefined' ? useLayoutEffect : useEffect;

export function useTextReveal(options = {}) {
    const {
        granularity = 'line',
        trigger = 'inview',
        threshold = 0.25,
        once = true,
        stagger = 0.11,
        duration = 0.9,
        delay = 0,
        distance = '0.4em',
        clip = true,
        easing = 'cubic-bezier(0.16, 1, 0.3, 1)',
    } = options;

    const ref = useRef(null);
    const reduced = useReducedMotion();

    useIsoLayoutEffect(() => {
        if (!ref.current) return undefined;
        // revealText gère lui-même reduced-motion (lecture matchMedia live) ;
        // `reduced` est dans les deps pour ré-exécuter au changement de préférence.
        return revealText(ref.current, {
            granularity,
            trigger,
            threshold,
            once,
            stagger,
            duration,
            delay,
            distance,
            clip,
            easing,
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [reduced, granularity, trigger, threshold, once, stagger, duration, delay, distance, clip, easing]);

    return ref;
}

export default useTextReveal;
