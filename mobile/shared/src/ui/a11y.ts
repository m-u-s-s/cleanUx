import { AccessibilityInfo } from 'react-native';
import { useEffect, useState } from 'react';

export function useReducedMotion(): boolean {
  const [reduced, setReduced] = useState(false);
  useEffect(() => {
    AccessibilityInfo.isReduceMotionEnabled().then(setReduced);
    const sub = AccessibilityInfo.addEventListener('reduceMotionChanged', setReduced);
    return () => sub.remove();
  }, []);
  return reduced;
}

export function useScreenReader(): boolean {
  const [active, setActive] = useState(false);
  useEffect(() => {
    AccessibilityInfo.isScreenReaderEnabled().then(setActive);
    const sub = AccessibilityInfo.addEventListener('screenReaderChanged', setActive);
    return () => sub.remove();
  }, []);
  return active;
}

/**
 * Rend l'animation d'entree demandee, ou rien si l'utilisateur a reduit les mouvements.
 * `entering={useEntree(FadeIn.duration(280))}` : l'ecran apparait alors sans transition.
 */
export function useEntree<T>(animation: T): T | undefined {
  return useReducedMotion() ? undefined : animation;
}

/**
 * Rend la duree demandee, ou zero si l'utilisateur a reduit les mouvements.
 * Une duree nulle laisse l'animation se jouer d'un coup, sans la supprimer du code appelant.
 */
export function useDuree(millisecondes: number): number {
  return useReducedMotion() ? 0 : millisecondes;
}

export const a11y = {
  minTouchSize: 44,
  announce: (message: string) => AccessibilityInfo.announceForAccessibility(message),
};
