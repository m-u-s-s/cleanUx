import type { MetierDuCatalogue, ModeCatalogue, SecteurDuCatalogue } from './types';

/** La hauteur d'un repère : le pas de l'aimantation et de la sélection. */
export const HAUTEUR_DE_REPERE = 88;

export type Cote = 'droite' | 'gauche';

export interface RepereDeSonde {
  cle: string;
  secteur: SecteurDuCatalogue;
  metier: MetierDuCatalogue;
  /** Rang dans la liste complète du mode, à partir de 1. */
  rang: number;
  total: number;
  /** Les métiers d'un secteur de rang pair à droite, impair à gauche. */
  cote: Cote;
  /** Le nom du secteur sur son premier métier seulement. */
  etiquetteSecteur: string | null;
}

export function construireLaSonde(secteurs: SecteurDuCatalogue[]): RepereDeSonde[] {
  const total = secteurs.reduce((somme, secteur) => somme + secteur.trades.length, 0);
  const reperes: RepereDeSonde[] = [];

  secteurs.forEach((secteur, rangSecteur) => {
    secteur.trades.forEach((metier, rangMetier) => {
      reperes.push({
        cle: `${secteur.slug}/${metier.slug}`,
        secteur,
        metier,
        rang: reperes.length + 1,
        total,
        cote: rangSecteur % 2 === 0 ? 'droite' : 'gauche',
        etiquetteSecteur: rangMetier === 0 ? secteur.name : null,
      });
    });
  });

  return reperes;
}

/** Le repère centré pour un décalage de défilement — borné, le rebond dépasse souvent. */
export function indexDepuisDecalage(decalageY: number, total: number): number {
  if (total <= 0) {
    return 0;
  }

  return Math.min(Math.max(Math.round(decalageY / HAUTEUR_DE_REPERE), 0), total - 1);
}

export function decalageDeIndex(index: number): number {
  return index * HAUTEUR_DE_REPERE;
}

/** Un slug tel que le serveur les forge : minuscules, chiffres et tirets. */
const SLUG = /^[a-z0-9-]+$/;

/**
 * Le moteur de commande web, ouvert sur le métier : `OrderJourney::mount($sector, $trade)` lit ces slugs.
 *
 * Un slug inattendu — vide, `..`, espace, barre oblique — n'entre pas dans le chemin : le moteur
 * s'ouvre alors sans présélection, dans le bon mode, plutôt que sur une URL dont on ne sait pas où
 * elle mène.
 */
export function cheminDeCommande(repere: RepereDeSonde, mode: ModeCatalogue): string {
  const { secteur, metier } = repere;

  if (!SLUG.test(secteur.slug) || !SLUG.test(metier.slug)) {
    return `/commander?mode=${mode}`;
  }

  return `/commander/${secteur.slug}/${metier.slug}?mode=${mode}`;
}
