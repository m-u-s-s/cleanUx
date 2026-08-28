/** Les trois langues que l'écran de langue propose, et que le serveur accepte. */
export type Langue = 'fr' | 'nl' | 'en';

export const LANGUES: readonly Langue[] = ['fr', 'nl', 'en'] as const;

export function estUneLangue(valeur: unknown): valeur is Langue {
  return typeof valeur === 'string' && (LANGUES as readonly string[]).includes(valeur);
}

/** Un catalogue plat : la clé porte sa hiérarchie, ce qui rend la recherche triviale. */
export type Catalogue = Record<string, string>;
