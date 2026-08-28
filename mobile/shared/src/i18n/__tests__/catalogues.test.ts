/**
 * Les catalogues se répondent : le français fait foi, les deux autres ne peuvent ni inventer
 * une clé ni laisser une chaîne vide.
 */
import { catalogues } from '../catalogues';
import { LANGUES } from '../types';

describe('les catalogues', () => {
  const clesFr = Object.keys(catalogues.fr).sort();

  it('le français n’est pas vide', () => {
    expect(clesFr.length).toBeGreaterThan(0);
  });

  it.each(LANGUES.filter(l => l !== 'fr'))('%s n’invente aucune clé absente du français', langue => {
    const inventees = Object.keys(catalogues[langue]).filter(c => !(c in catalogues.fr));

    expect(inventees).toEqual([]);
  });

  it.each(LANGUES)('%s ne contient aucune chaîne vide', langue => {
    const vides = Object.entries(catalogues[langue])
      .filter(([, valeur]) => valeur.trim() === '')
      .map(([cle]) => cle);

    expect(vides).toEqual([]);
  });

  /** Ce qui manque est TOLÉRÉ — le repli français couvre — mais reste compté et visible. */
  it.each(LANGUES.filter(l => l !== 'fr'))('%s : ce qui manque est nommé', langue => {
    const manquantes = clesFr.filter(c => !(c in catalogues[langue]));

    expect(manquantes).toEqual([]);
  });
});
