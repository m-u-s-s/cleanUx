/**
 * CE QUE L'UTILISATEUR LIT — en français, daté comme on date une date, et jamais en jargon.
 *
 * Ces quatre aides sont PARTAGÉES par les deux applications parce que les défauts l'étaient aussi.
 * Corrigés côté client, ils sont réapparus à l'identique côté prestataire le lendemain : statut
 * technique affiché tel quel, horodatage ISO, message interne d'axios en rouge. Une seule copie
 * évite de redécouvrir la même chose une troisième fois.
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
 * Une entrée illisible ressort telle quelle : sur l'écran de quelqu'un, une date approximative vaut
 * mieux qu'un tiret.
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

/**
 * UNE ADRESSE NE CONTIENT PAS LE MOT « null ».
 *
 * Les écrans concaténaient `${adresse}, ${ville}` sans garde. Quand la ville manque — ce qui arrive
 * dès qu'une commande vient d'un lieu enregistré sans commune —, le prestataire lisait
 * « Rue Haute 42, null » sur l'écran qui lui dit où se rendre.
 */
export function formatAdresse(...morceaux: Array<string | null | undefined>): string {
  return morceaux
    .map(m => (typeof m === 'string' ? m.trim() : ''))
    .filter(m => m !== '' && m !== 'null' && m !== 'undefined')
    .join(', ');
}

/**
 * UN DÉLAI SE LIT, IL NE SE COMPTE PAS EN SECONDES.
 *
 * Le compte à rebours d'une offre affichait la valeur brute : « 1658 s pour répondre ». Pour une
 * offre immédiate de vingt secondes c'est la bonne unité — c'est même la seule qui compte. Pour une
 * offre planifiée avec une demi-heure de délai, personne ne convertit de tête.
 *
 * Sous la minute on garde les secondes, précisément parce que c'est là qu'elles pressent.
 */
export function formatDelai(secondes: number | null | undefined): string {
  const valeur = Math.max(0, Math.floor(Number(secondes ?? 0)));

  if (valeur < 60) {
    return `${valeur} s`;
  }

  const minutes = Math.floor(valeur / 60);

  if (minutes < 60) {
    return `${minutes} min`;
  }

  const heures = Math.floor(minutes / 60);
  const reste = minutes % 60;

  return reste === 0 ? `${heures} h` : `${heures} h ${reste} min`;
}

/**
 * CE QU'ON MONTRE QUAND UNE REQUÊTE ÉCHOUE — jamais le texte d'axios.
 *
 * Le repli habituel était `error.message`, c'est-à-dire le message interne de la bibliothèque
 * HTTP : « Request failed with status code 404 », « … 422 ». Vu en rouge dans les deux
 * applications, en anglais, au milieu d'écrans français.
 *
 * L'ordre suit ce qui est le plus informé : le message du serveur, puis ses erreurs de validation,
 * puis le code HTTP traduit, puis un repli fourni par l'appelant qui sait de quel geste il parle.
 */
export function messageDErreur(erreur: any, repli = 'Une erreur est survenue. Réessayez dans un instant.'): string {
  /*
   * DEUX FORMES D'ERREUR COHABITENT, et ne pas le savoir rend cette aide inopérante.
   *
   * L'intercepteur des applications convertit les échecs axios en `ApiError` : `status`,
   * `message`, `errors`, `payload` — et AUCUN champ `response`. Une première version de cette
   * fonction ne lisait que `response.data` : elle ne s'appliquait donc jamais sur le chemin
   * principal, et retombait silencieusement sur le repli de l'appelant.
   *
   * On lit les deux : l'objet converti d'abord, l'erreur axios brute ensuite — celle qui subsiste
   * partout où l'appel court-circuite l'intercepteur.
   */
  const corps = erreur?.response?.data;

  const duServeur = erreur?.message ?? corps?.message;

  /*
   * « Request failed with status code 422 » est le texte INTERNE d'axios. Il ne doit jamais
   * atteindre un écran — c'est précisément ce qu'on a vu s'afficher, en rouge, dans les deux
   * applications. Le reconnaître explicitement est le seul moyen de le distinguer d'un message de
   * serveur, puisque l'intercepteur le recopie dans le même champ.
   */
  const estDuJargonAxios = typeof duServeur === 'string'
    && (/^Request failed with status code/.test(duServeur)
      || /^Network Error$/.test(duServeur)
      || /^timeout of /.test(duServeur));

  if (typeof duServeur === 'string' && duServeur.trim() !== '' && ! estDuJargonAxios) {
    return duServeur;
  }

  // Les erreurs de validation de Laravel : `{ errors: { champ: ["…"] } }`.
  const validation = erreur?.errors ?? corps?.errors;
  const premiere = validation && typeof validation === 'object'
    ? (Object.values(validation as Record<string, unknown>).flat() as unknown[])[0]
    : undefined;

  if (typeof premiere === 'string' && premiere.trim() !== '') {
    return premiere;
  }

  switch (erreur?.status ?? erreur?.response?.status) {
    case 401:
      return 'Votre session a expiré. Reconnectez-vous.';
    case 403:
      return 'Vous n’avez pas accès à cette action.';
    case 404:
      return 'Cet élément n’existe plus ou n’est pas encore disponible.';
    case 422:
      return 'Cette valeur n’a pas été acceptée. Vérifiez et réessayez.';
    case 429:
      return 'Trop de demandes coup sur coup. Réessayez dans une minute.';
    default:
      return repli;
  }
}
