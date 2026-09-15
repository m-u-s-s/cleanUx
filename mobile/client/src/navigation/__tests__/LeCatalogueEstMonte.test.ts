import { readFileSync } from 'node:fs';
import { join } from 'node:path';

const RACINE = join(__dirname, '..', '..');
const lire = (chemin: string): string => readFileSync(join(RACINE, chemin), 'utf8');

/** La pile personnelle commence à ses onglets : tout ce qui suit y est monté. */
const racine = lire('navigation/RootNavigator.tsx');
const pilePersonnelle = racine.slice(racine.indexOf('component={TabNavigator}'));
const montees = new Set([...pilePersonnelle.matchAll(/name="([A-Za-z]+)"/g)].map(m => m[1]));

describe('le catalogue natif est joignable depuis la pile personnelle', () => {
  it('témoin : le découpage voit la pile personnelle', () => {
    expect(racine.indexOf('component={TabNavigator}')).toBeGreaterThan(-1);
    expect(montees.has('Modules')).toBe(true);
    expect(montees.has('EmbeddedModule')).toBe(true);
  });

  it('la route Catalogue y est montée', () => {
    expect(montees.has('Catalogue')).toBe(true);
  });

  it("la feuille d'accueil y mène pour l'immédiat et le rendez-vous", () => {
    const feuille = lire('screens/components/HomeActionsSheet.tsx');
    expect(feuille).toContain("go('Catalogue', { mode: 'asap' })");
    expect(feuille).toContain("go('Catalogue', { mode: 'scheduled' })");
  });

  it('le catalogue ne vise que des routes montées', () => {
    const ecran = lire('screens/catalogue/CatalogueScreen.tsx');
    const cibles = [...ecran.matchAll(/\.(?:navigate|replace)\(\s*'([A-Za-z]+)'/g)].map(m => m[1]);

    expect(cibles.length).toBeGreaterThan(0);
    expect(cibles.filter(c => !montees.has(c))).toEqual([]);
  });
});
