import { ApiError } from '@/api';
import { traduireMaintenant } from '@/i18n';

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
    invalid_sort: traduireMaintenant('admin_catalogue.tri_non_supporte'),
    invalid_direction: traduireMaintenant('admin_catalogue.sens_non_supporte'),
    unknown_resource: traduireMaintenant('admin_catalogue.module_non_servi'),
    forbidden_not_admin: traduireMaintenant('admin_catalogue.pas_d_acces'),
    forbidden_readonly: traduireMaintenant('admin_catalogue.lecture_seule'),
    session_expired: traduireMaintenant('admin_catalogue.session_expiree'),
  };

  const connu = connus[erreur.errorCode];

  if (connu) {
    return connu;
  }

  return `${defaut} (${erreur.errorCode})`;
}
