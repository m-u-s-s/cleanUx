import fs from 'fs';
import path from 'path';

/**
 * L'écran doit être ATTEIGNABLE.
 *
 * Ce module a produit cinq fois le même défaut : un service écrit, testé, vert — et aucune porte
 * pour l'atteindre. L'import JSON, la publication, l'export, les conditions, les statistiques
 * d'abandon. Les tests d'écran montent le composant directement ; ils ne disent rien de la
 * navigation.
 *
 * Ce test lit donc les fichiers de navigation, pas le composant.
 */
const SRC = path.join(__dirname, '..', '..', 'src');

const read = (rel: string) => fs.readFileSync(path.join(SRC, rel), 'utf8');

describe('Joignabilité des courses immédiates', () => {
  it('est déclaré dans le navigateur racine', () => {
    const source = read('navigation/RootNavigator.tsx');

    expect(source).toContain('AsapOffersScreen');
    expect(source).toMatch(/name="AsapOffers"/);
  });

  it('est typé dans les paramètres de navigation', () => {
    expect(read('navigation/types.ts')).toMatch(/AsapOffers\s*:/);
  });

  it('a une porte d’entrée depuis le tableau de bord', () => {
    expect(read('screens/components/DashboardActionsSheet.tsx')).toContain('AsapOffers');
  });
});
