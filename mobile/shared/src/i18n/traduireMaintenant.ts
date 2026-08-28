import { catalogues, CATALOGUE_DE_REPLI } from './catalogues';
import { langueActuelle } from './langue';
import { traduire } from './traduire';

/**
 * Le traducteur du code qui n'est PAS un composant — une fonction utilitaire, un gestionnaire
 * d'erreur. Il lit la langue au moment de l'appel : rien ne le redessine, mais rien n'en a
 * besoin, puisque son resultat est produit puis affiche dans la foulee.
 */
export function traduireMaintenant(
  cle: string,
  valeurs?: Record<string, string | number>
): string {
  const langue = langueActuelle();

  return traduire(catalogues[langue] ?? CATALOGUE_DE_REPLI, CATALOGUE_DE_REPLI, cle, valeurs);
}
