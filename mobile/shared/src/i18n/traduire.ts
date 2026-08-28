import type { Catalogue } from './types';

/**
 * Cherche une clé, puis se rabat sur le français, puis rend la clé elle-même.
 *
 * Le repli sur le français est délibéré : une traduction manquante montre une phrase juste dans
 * la mauvaise langue, jamais un identifiant technique. La clé nue reste le dernier recours, et
 * elle se voit — c'est ce qui la fait corriger.
 */
export function traduire(
  catalogue: Catalogue,
  repli: Catalogue,
  cle: string,
  valeurs?: Record<string, string | number>
): string {
  const brut = catalogue[cle] ?? repli[cle] ?? cle;

  return valeurs ? interpoler(brut, valeurs) : brut;
}

/** Remplace `:nom` par sa valeur — même notation que les catalogues du serveur. */
export function interpoler(texte: string, valeurs: Record<string, string | number>): string {
  return texte.replace(/:([a-zA-Z_][a-zA-Z0-9_]*)/g, (entier, nom: string) =>
    Object.prototype.hasOwnProperty.call(valeurs, nom) ? String(valeurs[nom]) : entier
  );
}
