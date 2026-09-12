/**
 * Les six langues que l'écran de langue propose, et que le serveur accepte.
 *
 * La liste suit celle du web (`SetLocale::SUPPORTED`) : une langue proposée ici mais
 * refusée là-bas ferait retomber le compte en français au premier appel, sans rien dire.
 */
export type Langue = 'fr' | 'nl' | 'en' | 'es' | 'it' | 'de';

export const LANGUES: readonly Langue[] = ['fr', 'nl', 'en', 'es', 'it', 'de'] as const;

export function estUneLangue(valeur: unknown): valeur is Langue {
  return typeof valeur === 'string' && (LANGUES as readonly string[]).includes(valeur);
}

/** Un catalogue plat : la clé porte sa hiérarchie, ce qui rend la recherche triviale. */
export type Catalogue = Record<string, string>;
