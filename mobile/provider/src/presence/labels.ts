import { traduireMaintenant } from '@/i18n';
import type { PresenceStatus } from './types';

/**
 * Source unique des libellés et variantes visuelles par statut de présence. `PresencePill`
 * (affichage seul) et `PresenceToggle` (seul point d'écriture) partagent ces tables : grâce au
 * typage `Record<PresenceStatus, …>`, un statut ajouté à `PresenceStatus` sans entrée ici fait
 * échouer la compilation, au lieu de régresser silencieusement dans une seule des deux copies.
 */
/* Des CLES. `presenceLabel()` les traduit a la lecture ; une table de libelles figerait
   la langue du chargement du module. */
export const PRESENCE_LABELS: Record<PresenceStatus, string> = {
  online: 'presence.statut_en_ligne',
  busy: 'presence.statut_occupe',
  on_break: 'presence.statut_en_pause',
  offline: 'presence.statut_hors_ligne',
};

export function presenceLabel(statut: PresenceStatus): string {
  return traduireMaintenant(PRESENCE_LABELS[statut]);
}

export const PRESENCE_VARIANTS: Record<PresenceStatus, 'success' | 'urgent' | 'primary'> = {
  online: 'success',
  busy: 'urgent',
  on_break: 'primary',
  offline: 'primary',
};
