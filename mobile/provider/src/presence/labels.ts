import type { PresenceStatus } from './types';

/**
 * Source unique des libellés et variantes visuelles par statut de présence. `PresencePill`
 * (affichage seul) et `PresenceToggle` (seul point d'écriture) partagent ces tables : grâce au
 * typage `Record<PresenceStatus, …>`, un statut ajouté à `PresenceStatus` sans entrée ici fait
 * échouer la compilation, au lieu de régresser silencieusement dans une seule des deux copies.
 */
export const PRESENCE_LABELS: Record<PresenceStatus, string> = {
  online: 'En ligne',
  busy: 'Occupé',
  on_break: 'En pause',
  offline: 'Hors ligne',
};

export const PRESENCE_VARIANTS: Record<PresenceStatus, 'success' | 'urgent' | 'primary'> = {
  online: 'success',
  busy: 'urgent',
  on_break: 'primary',
  offline: 'primary',
};
