import { ApiError } from '@/api';

/**
 * Ce qu'on affiche quand un écran du catalogue ne charge pas.
 *
 * POURQUOI CETTE FONCTION EXISTE. Le premier essai de l'onglet a montré « Impossible de charger les
 * pays » — et la cause était un tri non déclaré, que le serveur nommait précisément dans sa
 * réponse (`invalid_sort`, avec la liste des tris permis). Le message générique a coûté un
 * aller-retour de diagnostic pour une information que l'application AVAIT déjà.
 *
 * Un message d'erreur qui tait ce que le serveur a dit est un message qui ment par omission.
 */
export function messageDErreur(erreur: unknown, defaut: string): string {
  if (!(erreur instanceof ApiError)) {
    return defaut;
  }

  /*
   * Les cas qu'on sait traduire, parce qu'ils appellent une action précise de la part de qui les
   * lit. Les autres tombent sur le code brut : lisible par un développeur, et bien plus utile
   * qu'une phrase rassurante qui ne dit rien.
   */
  const connus: Record<string, string> = {
    invalid_sort: 'Tri non pris en charge par le serveur.',
    invalid_direction: 'Sens de tri non pris en charge.',
    unknown_resource: 'Ce module n’est pas servi par le serveur.',
    forbidden_not_admin: 'Votre compte n’a pas accès à l’administration.',
    forbidden_readonly: 'Votre compte est en lecture seule.',
    session_expired: 'Session expirée. Reconnectez-vous.',
  };

  const connu = connus[erreur.errorCode];

  if (connu) {
    return connu;
  }

  return `${defaut} (${erreur.errorCode})`;
}
