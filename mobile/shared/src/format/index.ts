import { traduireMaintenant } from '@/i18n/traduireMaintenant';
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
// Ces tables vivent hors de tout composant : elles portent la CLE, la fonction traduit.
const LIBELLES: Record<string, string> = {
  pending: 'statut.en_attente',
  confirmed: 'statut.confirmee',
  in_progress: 'statut.en_cours',
  completed: 'statut.terminee',
  cancelled: 'statut.annulee',
  unknown: 'statut.a_preciser',
};

/**
 * Le libellé français d'un statut de réservation.
 *
 * Un statut inconnu ressort tel quel plutôt que masqué : mieux vaut un mot technique visible —
 * qu'on corrigera — qu'un vide qui laisse croire que la réservation n'a pas d'état.
 */
export function libelleStatut(statut: string | null | undefined): string {
  const inconnu = traduireMaintenant(LIBELLES.unknown ?? 'statut.a_preciser');

  if (!statut) {
    return inconnu;
  }

  const cle = LIBELLES[statut];

  return cle ? traduireMaintenant(cle) : statut;
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
      return traduireMaintenant('erreur.session_expiree');
    case 403:
      return traduireMaintenant('erreur.pas_acces');
    case 404:
      return traduireMaintenant('erreur.element_absent');
    case 422:
      return traduireMaintenant('erreur.valeur_refusee');
    case 429:
      return traduireMaintenant('erreur.trop_de_demandes');
    default:
      return repli;
  }
}

/**
 * L'HEURE D'UN FIL D'ÉVÉNEMENTS — avec la date dès qu'on sort du jour même.
 *
 * Le fil « sur place » affichait `H:i` nu, sur cette hypothèse écrite noir sur blanc : « le fil se
 * lit dans la journée où il se déroule ». Elle tombe dès qu'on rouvre une mission le lendemain.
 * Relevé le 21 août à 03 h 40, un fil démarré le 18 à 04:32 affichait « 04:32 » — soit, pour qui
 * lit, une heure ENCORE À VENIR dans la journée en cours. Les trois repères de l'écran (départ,
 * gel de la liste, fin estimée) se lisaient ainsi à l'envers, sans rien qui signale l'écart.
 *
 * Le jour même ne change pas : c'est le cas courant, et « 14:05 » s'y lit mieux que n'importe
 * quelle date répétée à chaque ligne. La date n'apparaît que lorsqu'elle porte une information.
 *
 * `maintenant` est un paramètre pour que la règle soit vérifiable sans truquer l'horloge.
 */
function lireLHeure(iso: string | null | undefined): { d: Date; hhmm: string } | null {
  if (!iso) {
    return null;
  }

  const d = new Date(iso);

  if (Number.isNaN(d.getTime())) {
    return null;
  }

  return { d, hhmm: `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}` };
}

function estLeMemeJour(d: Date, maintenant: Date): boolean {
  return d.getFullYear() === maintenant.getFullYear()
    && d.getMonth() === maintenant.getMonth()
    && d.getDate() === maintenant.getDate();
}

/** La forme de PHRASE : « Fin estimée vers 18 août à 06:16 ». */
export function formatHeureDuFil(iso: string | null | undefined, maintenant: Date = new Date()): string {
  const lu = lireLHeure(iso);

  if (!lu) {
    return '—';
  }

  if (estLeMemeJour(lu.d, maintenant)) {
    return lu.hhmm;
  }

  return `${lu.d.getDate()} ${MOIS[lu.d.getMonth()] ?? '?'} à ${lu.hhmm}`;
}

/**
 * La forme de RAIL : la colonne étroite d'un fil d'événements.
 *
 * La forme de phrase ne tient pas dans cette gouttière de 52 px : elle s'y coupait après le « à »,
 * et « 18 août à » se lisait alors comme le début du libellé posé juste à droite — « 18 août à En
 * route », avec « 04:32 » relégué en dessous. Relevé à l'écran après la première correction.
 *
 * D'où deux lignes assumées, sans mot de liaison à couper : la date, puis l'heure. Le jour même
 * reste sur une seule ligne, comme avant.
 */
export function formatHeureDuFilCompacte(iso: string | null | undefined, maintenant: Date = new Date()): string {
  const lu = lireLHeure(iso);

  if (!lu) {
    return '—';
  }

  if (estLeMemeJour(lu.d, maintenant)) {
    return lu.hhmm;
  }

  const jour = String(lu.d.getDate()).padStart(2, '0');
  const mois = String(lu.d.getMonth() + 1).padStart(2, '0');

  // Le retour est ECHAPPE, pas ecrit en clair : un passage de formatage reindenterait la
  // seconde ligne, et l'heure gagnerait alors les espaces de l'indentation.
  return `${jour}/${mois}\n${lu.hhmm}`;
}

/**
 * UNE DATE ISO, EN FRANÇAIS — sans rien demander à l'appareil.
 *
 * `formatDateHeure` ci-dessus explique déjà pourquoi ce module écrit ses dates à la main. La leçon
 * n'avait pas atteint les notifications : `formatNotificationDate` appelait `toLocaleDateString()`
 * SANS locale, donc suivait celle du téléphone. Relevé dans l'émulateur, réglé en anglais : le fil
 * de notifications affichait « 8/18/2026 » — un mois et un jour inversés pour qui lit en français,
 * au milieu d'une application qui ne l'est pas.
 *
 * Cinq points d'appel en dépendent, dans les DEUX applications.
 */
export function formatDateIso(iso: string | null | undefined, avecHeure = false): string {
  const lu = lireLHeure(iso);

  if (!lu) {
    return '';
  }

  const jour = `${lu.d.getDate()} ${MOIS[lu.d.getMonth()] ?? '?'} ${lu.d.getFullYear()}`;

  return avecHeure ? `${jour} à ${lu.hhmm}` : jour;
}

/**
 * LES NEUF ÉTATS D'UNE VÉRIFICATION D'IDENTITÉ, EN FRANÇAIS.
 *
 * L'écran « Vérification d'identité » de l'application prestataire affichait la valeur brute :
 * relevé à l'écran, un prestataire lisait « clear » sous le titre, en anglais et en jargon de
 * fournisseur de contrôle. Les huit autres états sont du même tonneau — `in_review`,
 * `awaiting_documents`, `unidentified`…
 *
 * Même règle que `libelleStatut` juste au-dessus : un état inconnu ressort TEL QUEL plutôt que
 * masqué. Mieux vaut un mot technique visible, qu'on corrigera, qu'un vide qui laisse croire que
 * la vérification n'a pas d'état.
 */
const LIBELLES_KYC: Record<string, string> = {
  pending: 'statut.en_attente',
  in_review: 'kyc.en_cours_dexamen',
  awaiting_documents: 'kyc.documents_attendus',
  clear: 'kyc.verifiee',
  consider: 'kyc.a_examiner',
  unidentified: 'kyc.identite_non_etablie',
  rejected: 'kyc.refusee',
  expired: 'kyc.expiree',
  cancelled: 'statut.annulee',
};

export function libelleStatutKyc(statut: string | null | undefined): string {
  if (!statut) {
    return traduireMaintenant('kyc.non_verifie');
  }

  const cle = LIBELLES_KYC[statut];

  return cle ? traduireMaintenant(cle) : statut;
}
