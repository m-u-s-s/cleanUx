/* ============================================================================
   useReducedMotion — réactif à prefers-reduced-motion.
   Retourne `true` si l'utilisateur demande moins de mouvement. Se met à jour si
   la préférence change en cours de session. SSR-safe (initialise à false côté
   serveur ; ces îlots React sont de toute façon client-only).
   ========================================================================= */
import { useEffect, useState } from 'react';

const QUERY = '(prefers-reduced-motion: reduce)';

export function useReducedMotion() {
    const [reduced, setReduced] = useState(
        () => typeof window !== 'undefined' && window.matchMedia(QUERY).matches
    );

    useEffect(() => {
        if (typeof window === 'undefined') return undefined;
        const mq = window.matchMedia(QUERY);
        const onChange = () => setReduced(mq.matches);
        onChange();
        // addEventListener moderne + fallback Safari ancien
        if (mq.addEventListener) mq.addEventListener('change', onChange);
        else mq.addListener(onChange);
        return () => {
            if (mq.removeEventListener) mq.removeEventListener('change', onChange);
            else mq.removeListener(onChange);
        };
    }, []);

    return reduced;
}

export default useReducedMotion;
