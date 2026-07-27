import { useCallback, useState } from 'react';
import { useGpsWatcher } from './hooks';

/**
 * Position GPS courante du prestataire, partagée par tous les écrans qui affichent une
 * distance dérivée (liste des missions, offre, accueil, tableau de bord). Enveloppe
 * `useGpsWatcher` et garde uniquement la dernière position connue en state — `null` tant
 * qu'aucune position n'a encore été reçue (permission refusée, GPS indisponible en test, etc.).
 *
 * `useGpsWatcher` ne renvoie rien aujourd'hui ; une tâche ultérieure lui fera renvoyer un état
 * de permission. On ignore volontairement sa valeur de retour ici pour ne pas dépendre de sa
 * forme actuelle.
 */
export function useCurrentPosition(): { latitude: number; longitude: number } | null {
  const [position, setPosition] = useState<{ latitude: number; longitude: number } | null>(null);

  useGpsWatcher(
    true,
    useCallback((pos) => setPosition({ latitude: pos.latitude, longitude: pos.longitude }), []),
  );

  return position;
}
