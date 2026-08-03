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
  | 'admin'
  | 'provider'
  | 'providerOnboarding'
  | 'switcher';

/** L'espace qu'un compte à double casquette a choisi, quand il en a choisi un. */
export type ChosenSpace = 'admin' | 'provider';

export interface SpaceInput {
  isLoading: boolean;
  isAuthenticated: boolean;
  user: { is_admin?: boolean; is_provider?: boolean } | null;
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

  if (isAdmin && isProvider && !chosenSpace) {
    return 'switcher';
  }

  if (isAdmin && chosenSpace !== 'provider') {
    return 'admin';
  }

  // Le parc déjà installé porte des jetons émis avant que `/auth/me` ne serve les casquettes :
  // sans drapeau, le compte est traité en prestataire — c'est ce qu'il est dans cet APK.
  if (onboardingComplete === false) {
    return 'providerOnboarding';
  }

  return 'provider';
}
