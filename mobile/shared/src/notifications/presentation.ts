import { colors } from '@/theme';
import type { NotificationSeverity } from './hooks';
import { formatDateIso } from '../format';

/**
 * Présentation partagée entre les deux applications.
 *
 * Les écrans de notifications du client et du prestataire sont des jumeaux copiés-collés : même
 * rendu, même `accessibilityLabel`, mêmes styles. Le défaut « title/body jamais envoyés » était
 * donc présent DEUX FOIS, et une correction sur un seul écran aurait laissé l'autre vide. Ce qui
 * peut être décidé une fois l'est ici.
 */

/** La sévérité du serveur, traduite dans les variantes du composant `Badge`. */
export function severityVariant(severity: NotificationSeverity): 'neutral' | 'info' | 'success' | 'warning' | 'danger' {
  switch (severity) {
    case 'danger':
      return 'danger';
    case 'warning':
      return 'warning';
    case 'success':
      return 'success';
    case 'info':
      return 'info';
    default:
      return 'neutral';
  }
}

/**
 * LE LISERÉ DE GAUCHE D'UNE CARTE.
 *
 * La liste empilait des lignes séparées par un filet d'un pixel : rien ne disait où finissait une
 * notification et où commençait la suivante, et l'urgence ne se voyait nulle part. Chaque carte
 * porte maintenant sa sévérité sur son bord gauche, comme le centre web.
 *
 * Les teintes 500 tiennent sur fond clair ET sur fond nuit — c'est un aplat de 3 px, pas du
 * texte : le contraste de lecture n'est pas en jeu, seule la lisibilité de la couleur l'est.
 */
export function severityAccent(severity: NotificationSeverity, bordureParDefaut: string): string {
  switch (severity) {
    case 'danger':
      return colors.danger[500];
    case 'warning':
      return colors.warning[500];
    case 'success':
      return colors.success[500];
    case 'info':
      return colors.brand[500];
    default:
      return bordureParDefaut;
  }
}

/**
 * Les clés de `context` sont techniques. Celles qu'on connaît reçoivent leur nom ; les autres
 * sont rendues telles quelles plutôt que masquées — une clé ajoutée demain doit rester visible.
 */
const LIBELLES: Record<string, string> = {
  rdv_id: 'Mission',
  invoice_number: 'Facture',
  zone: 'Zone',
  service: 'Service',
  google_email: 'Compte Google',
};

export function contextLabel(cle: string): string {
  return LIBELLES[cle] ?? cle;
}

/**
 * `new Date(x).toLocaleDateString()` sur une chaîne vide ou absente rend « Invalid Date » à
 * l'écran, ce que rien n'attrape. Une notification sans date n'affiche rien du tout.
 */
export function formatNotificationDate(iso: string | null | undefined, avecHeure = false): string {
  // La règle vit dans `../format`, avec les autres dates de l'application : `toLocaleDateString()`
  // suivait la langue de l'appareil et rendait « 8/18/2026 » sur un téléphone en anglais.
  return formatDateIso(iso, avecHeure);
}
