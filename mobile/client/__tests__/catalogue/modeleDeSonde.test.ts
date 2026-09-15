import {
  HAUTEUR_DE_REPERE,
  cheminDeCommande,
  construireLaSonde,
  decalageDeIndex,
  indexDepuisDecalage,
} from '@/catalogue';
import type { SecteurDuCatalogue } from '@/catalogue';

const metier = (slug: string) => ({
  slug, name: slug, icon: null, short_description: null, floor_price_cents: null, hourly: false,
});

const secteurs: SecteurDuCatalogue[] = [
  { slug: 'batiment', name: 'Bâtiment', icon: 'hammer', trades: [metier('peinture'), metier('plomberie')] },
  { slug: 'nettoyage', name: 'Nettoyage', icon: 'sparkles', trades: [metier('vitres')] },
  { slug: 'verts', name: 'Espaces verts', icon: 'tree', trades: [metier('jardinage')] },
];

describe('construireLaSonde', () => {
  const sonde = construireLaSonde(secteurs);

  it('met tous les métiers bout à bout, avec leur rang sur le total', () => {
    expect(sonde.map(r => r.metier.slug)).toEqual(['peinture', 'plomberie', 'vitres', 'jardinage']);
    expect(sonde.map(r => `${r.rang}/${r.total}`)).toEqual(['1/4', '2/4', '3/4', '4/4']);
  });

  it('change de côté à chaque secteur', () => {
    expect(sonde.map(r => r.cote)).toEqual(['droite', 'droite', 'gauche', 'droite']);
  });

  it("témoin : deux métiers d'un même secteur restent du même côté", () => {
    expect(sonde[0]!.cote).toBe(sonde[1]!.cote);
  });

  it("porte l'étiquette du secteur sur son seul premier métier", () => {
    expect(sonde.map(r => r.etiquetteSecteur)).toEqual(['Bâtiment', null, 'Nettoyage', 'Espaces verts']);
  });

  it('une réponse vide donne une sonde vide', () => {
    expect(construireLaSonde([])).toEqual([]);
  });
});

describe('indexDepuisDecalage', () => {
  it('arrondit au repère le plus proche', () => {
    expect(indexDepuisDecalage(3 * HAUTEUR_DE_REPERE, 10)).toBe(3);
    expect(indexDepuisDecalage(3 * HAUTEUR_DE_REPERE + 43, 10)).toBe(3);
    expect(indexDepuisDecalage(3 * HAUTEUR_DE_REPERE + 45, 10)).toBe(4);
  });

  it('reste dans les bornes, même quand le rebond dépasse', () => {
    expect(indexDepuisDecalage(-120, 10)).toBe(0);
    expect(indexDepuisDecalage(99999, 10)).toBe(9);
    expect(indexDepuisDecalage(500, 0)).toBe(0);
  });

  it("est l'inverse de decalageDeIndex", () => {
    expect(indexDepuisDecalage(decalageDeIndex(7), 10)).toBe(7);
  });
});

describe('cheminDeCommande', () => {
  it('ouvre le moteur sur le secteur et le métier, avec le mode', () => {
    const [premier] = construireLaSonde(secteurs);
    expect(cheminDeCommande(premier!, 'asap')).toBe('/commander/batiment/peinture?mode=asap');
  });

  it("encode un slug inattendu plutôt que de casser l'URL", () => {
    const [bizarre] = construireLaSonde([{ ...secteurs[0]!, slug: 'a b', name: secteurs[0]!.name, trades: [metier('c/d')] }]);
    expect(cheminDeCommande(bizarre!, 'scheduled')).toBe('/commander/a%20b/c%2Fd?mode=scheduled');
  });
});
