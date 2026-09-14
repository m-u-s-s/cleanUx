import { readFileSync } from 'fs';
import { join } from 'path';

import { linking } from '../../src/navigation/linking';

/**
 * UN ONGLET SANS LIEN PROFOND EST UNE PORTE MURÉE — et le défaut ne se voit pas : la cible
 * inconnue retombe simplement sur l'accueil.
 *
 * `Explore` a cédé sa place à `Location` quand la barre est passée à cinq portes ; l'entrée du
 * lien était restée, `Location` et `Messages` n'en avaient aucune. Ni `tsc` ni Jest ne l'ont dit :
 * la carte des liens est un objet de configuration, pas un appel typé sur `TabParamList`.
 */
describe('les liens profonds suivent la barre d’onglets', () => {
  const ongletsDeclares = (): string[] => {
    const source = readFileSync(
      join(__dirname, '..', '..', 'src', 'navigation', 'types.ts'),
      'utf8',
    );
    const bloc = source.match(/export type TabParamList = \{([\s\S]*?)\};/);
    const corps = bloc?.[1];

    if (!corps) {
      throw new Error('TabParamList introuvable : le test ne mesure plus rien.');
    }

    return [...corps.matchAll(/^\s{2}(\w+):/gm)]
      .map(m => m[1])
      .filter((nom): nom is string => typeof nom === 'string');
  };

  it('mesure bien quelque chose', () => {
    // Le témoin anti-vacuité : une extraction qui ne capture plus rien rendrait tout vert.
    expect(ongletsDeclares().length).toBeGreaterThanOrEqual(5);
  });

  it('chaque onglet a son chemin', () => {
    const mainTabs = linking.config?.screens?.MainTabs as { screens?: Record<string, string> };
    const chemins = Object.keys(mainTabs?.screens ?? {});

    expect([...chemins].sort()).toEqual([...ongletsDeclares()].sort());
  });

  it('aucun chemin ne vise un onglet disparu', () => {
    const mainTabs = linking.config?.screens?.MainTabs as { screens?: Record<string, string> };

    for (const nom of Object.keys(mainTabs?.screens ?? {})) {
      expect(ongletsDeclares()).toContain(nom);
    }
  });
});
