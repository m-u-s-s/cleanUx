import { useCallback, useState } from 'react';
import { useGpsWatcher } from './hooks';

/**
 * Position GPS courante du prestataire, partagée par tous les écrans qui affichent une
 * distance dérivée (liste des missions, offre, accueil, tableau de bord). Enveloppe
 * `useGpsWatcher` et garde uniquement la dernière position connue en state — `null` tant
 * qu'aucune position n'a encore été reçue (permission refusée, GPS indisponible en test, etc.).
 *
 * `enabled` : à passer à `useIsFocused()` depuis tout écran d'ONGLET. React Navigation garde un
 * onglet monté une fois visité, donc sans ce garde le watcher (Accuracy.High, 5 s, 10 m)
 * continue de tourner pendant que le prestataire regarde un autre onglet. Laissé à `true` par
 * défaut pour les composants dont la durée de vie est déjà bornée par leur propre montage
 * (une offre modale, par exemple).
 */
export function useCurrentPosition(enabled: boolean = true): { latitude: number; longitude: number } | null {
  const [position, setPosition] = useState<{ latitude: number; longitude: number } | null>(null);

  useGpsWatcher(
    enabled,
    useCallback((pos) => setPosition({ latitude: pos.latitude, longitude: pos.longitude }), []),
  );

  return position;
}
