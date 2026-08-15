/**
 * CE QUE LE CLIENT LIT — en français, et daté comme on date une date.
 *
 * Deux choses remontaient de l'API telles quelles jusqu'à l'écran : le statut technique
 * (« pending ») et l'horodatage ISO (« 2026-08-20 à 11:00 »). Vus sur l'accueil de l'app,
 * au milieu de « Bonjour, Client » et « Réserver un service ».
 *
 * Ce ne sont pas des détails de traduction : un statut est une PROMESSE faite au client — « en
 * attente d'un professionnel » ne dit pas la même chose que « confirmé » —, et une date qu'il faut
 * déchiffrer est une date qu'on lit de travers.
 */

/**
 * Les états normalisés par l'API cliente.
 *
 * ⚠️ Cette liste suit `ClientBookingController::normalisedState()` — six valeurs, dont `unknown`.
 * Elle ne suit PAS les statuts du domaine (`en_attente`, `confirme`, `termine`…), que l'API ne
 * laisse jamais sortir.
 */
const LIBELLES: Record<string, string> = {
  pending: 'En attente',
  confirmed: 'Confirmée',
  in_progress: 'En cours',
  completed: 'Terminée',
  cancelled: 'Annulée',
  unknown: 'À préciser',
};

/**
 * Le libellé français d'un statut de réservation.
 *
 * Un statut inconnu ressort tel quel plutôt que masqué : mieux vaut un mot technique visible —
 * qu'on corrigera — qu'un vide qui laisse croire que la réservation n'a pas d'état.
 */
export function libelleStatut(statut: string | null | undefined): string {
  const inconnu = LIBELLES.unknown ?? 'À préciser';

  if (!statut) {
    return inconnu;
  }

  return LIBELLES[statut] ?? statut;
}

const MOIS = [
  'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
  'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre',
];

/**
 * « 20 août 2026 à 11h00 » à partir d'une date ISO et d'une heure.
 *
 * Écrit à la main plutôt que confié à `toLocaleDateString` : le formatage local dépend des données
 * de langue embarquées sur l'appareil, absentes de certains Android, et retombe alors
 * silencieusement en anglais — précisément le défaut qu'on corrige.
 *
 * Une entrée illisible ressort telle quelle : sur cet écran, une date approximative vaut mieux
 * qu'un tiret.
 */
export function formatDateHeure(date?: string | null, heure?: string | null): string {
  if (!date) {
    return heure ?? '';
  }

  const parties = date.slice(0, 10).split('-');

  if (parties.length !== 3) {
    return heure ? `${date} à ${heure}` : date;
  }

  const [annee, mois, jour] = parties;
  const nomDuMois = MOIS[Number(mois) - 1];

  if (!nomDuMois) {
    return heure ? `${date} à ${heure}` : date;
  }

  const lisible = `${Number(jour)} ${nomDuMois} ${annee}`;

  if (!heure) {
    return lisible;
  }

  // « 11:00:00 » et « 11:00 » donnent tous deux « 11h00 » ; les secondes n'intéressent personne.
  const [h, m] = heure.split(':');

  return `${lisible} à ${h}h${m ?? '00'}`;
}
