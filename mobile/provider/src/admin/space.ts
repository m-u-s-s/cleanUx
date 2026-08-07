/**
 * Quel espace ouvrir au démarrage.
 *
 * L'application prestataire sert désormais DEUX publics : le prestataire de terrain et
 * l'administrateur de plateforme. Le serveur accepte explicitement les deux dans cet APK
 * (`AppAudience::allows`, règle 2) — c'est le mobile qui n'en tenait pas compte : tout compte
 * authentifié traversait le parcours de vérification prestataire, puis atterrissait sur des
 * onglets dont chaque écran appelle des routes gardées `role:employe`.
 *
 * L'ORDRE DES CONDITIONS EST TOUT. La qualité d'administrateur se teste AVANT le parcours
 * prestataire : un administrateur n'a pas de dossier à compléter, et l'évaluer d'abord
 * l'enfermerait dehors de sa propre console.
 *
 * Ce n'est qu'un aiguillage d'INTERFACE. Les frontières de privilèges restent tenues par les
 * gardes de rôle du serveur, qui refusent un jeton prestataire sur une route d'administration
 * quel que soit l'écran affiché.
 */
export type Space =
  | 'loading'
  | 'login'
  | 'superAdmin'
  | 'admin'
  | 'provider'
  | 'providerCompany'
  | 'providerOnboarding'
  | 'switcher';

/** L'espace qu'un compte à plusieurs casquettes a choisi, quand il en a choisi un. */
export type ChosenSpace = 'superAdmin' | 'admin' | 'provider' | 'providerCompany';

export interface SpaceInput {
  isLoading: boolean;
  isAuthenticated: boolean;
  user: {
    is_admin?: boolean;
    is_provider?: boolean;
    /**
     * Le SIXIÈME rôle, tranché par le serveur (`roleCanonique()`).
     *
     * Surtout pas déduit de `is_admin`, qui est vrai pour un administrateur ordinaire comme pour
     * un super administrateur : aiguiller dessus donnerait le même espace aux deux, et les
     * distinguer n'aurait servi à rien.
     *
     * Absent des jetons du parc déjà installé — sans drapeau, le compte est traité en
     * administrateur, ce qu'il était hier.
     */
    is_super_admin?: boolean;
    /**
     * Le droit de PILOTER une société prestataire, tranché par le serveur (`missions.view_all`).
     *
     * Surtout pas `organization_type === 'provider_company'` : ce champ vaut la même chose pour le
     * patron et pour le nettoyeur, tous deux membres de la même organisation. Aiguiller dessus
     * enverrait un employé dans un espace de pilotage sans missions, sans revenus, sans présence.
     */
    can_manage_company?: boolean;
  } | null;
  /**
   * `true` dossier complet, `false` incomplet, `undefined` inconnu (chargement ou erreur).
   * L'inconnu LAISSE PASSER : mieux vaut un tableau de bord partiellement bloqué par le serveur
   * qu'un utilisateur enfermé hors de son application parce qu'une requête a échoué.
   */
  onboardingComplete?: boolean;
  chosenSpace?: ChosenSpace;
}

export function resolveSpace(input: SpaceInput): Space {
  const { isLoading, isAuthenticated, user, onboardingComplete, chosenSpace } = input;

  if (isLoading) {
    return 'loading';
  }

  if (!isAuthenticated || !user) {
    return 'login';
  }

  const isAdmin = user.is_admin === true;
  const isProvider = user.is_provider === true;
  const isSuperAdmin = user.is_super_admin === true;
  const pilotSociete = user.can_manage_company === true;

  /*
   * LE SUPER ADMINISTRATEUR OUVRE SON ESPACE, ET IL SE TESTE EN PREMIER.
   *
   * `is_admin` est vrai pour lui aussi : placé plus bas, ce test ne serait jamais atteint et le
   * sixième rôle recevrait la console, exactement comme le cinquième.
   *
   * On ne lui impose PAS le sélecteur au démarrage. Le choix reste possible — les cartes de
   * `SpaceSwitcherScreen` mènent à la console, au terrain et à la société — mais lui faire trancher
   * chaque matin ferait payer un écran de choix à quelqu'un qui fait le même geste tous les jours.
   * C'est le raisonnement qui a déjà décidé de RETENIR le choix d'espace.
   *
   * Les trois autres espaces sont exclus un par un plutôt que testés par `!chosenSpace` : un super
   * administrateur qui a choisi « société » doit y rester, et une liste ouverte laisserait passer
   * toute valeur future sans qu'on s'en aperçoive.
   */
  if (
    isSuperAdmin &&
    chosenSpace !== 'admin' &&
    chosenSpace !== 'provider' &&
    chosenSpace !== 'providerCompany'
  ) {
    return 'superAdmin';
  }

  if (isAdmin && (isProvider || pilotSociete) && !chosenSpace) {
    return 'switcher';
  }

  if (isAdmin && chosenSpace !== 'provider' && chosenSpace !== 'providerCompany') {
    return 'admin';
  }

  /*
   * L'ESPACE SOCIÉTÉ SE TESTE ICI, ET L'EMPLACEMENT EST LE PROPOS.
   *
   * APRÈS l'administration : un compte qui est les deux garde son choix, et le sélecteur reste le
   * seul juge.
   *
   * AVANT `providerOnboarding` : un gérant n'a pas de dossier de TERRAIN à compléter — pas de
   * pièce d'identité à scanner pour répartir les missions de ses équipes. L'y soumettre
   * l'enfermerait hors de sa propre société, exactement comme le parcours prestataire enfermait
   * l'administrateur avant l'extraction de cette fonction.
   *
   * Un gérant qui a explicitement choisi « terrain » retombe plus bas : dans une petite société, le
   * patron nettoie souvent lui-même, et le retenir ici lui retirerait ses missions.
   */
  if (pilotSociete && chosenSpace !== 'provider') {
    return 'providerCompany';
  }

  // Le parc déjà installé porte des jetons émis avant que `/auth/me` ne serve les casquettes :
  // sans drapeau, le compte est traité en prestataire — c'est ce qu'il est dans cet APK.
  if (onboardingComplete === false) {
    return 'providerOnboarding';
  }

  return 'provider';
}
