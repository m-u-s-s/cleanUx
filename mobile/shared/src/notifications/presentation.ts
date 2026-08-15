import type { NotificationSeverity } from './hooks';

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
  if (!iso) return '';

  const date = new Date(iso);

  if (Number.isNaN(date.getTime())) return '';

  return avecHeure
    ? `${date.toLocaleDateString()} à ${date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`
    : date.toLocaleDateString();
}
