/**
 * Le jumeau mobile du garde-fou anti-mensonge du serveur.
 *
 * Côté serveur, `ResourceRegistryTest` refuse qu'un module déclaré `descriptor` n'ait pas de
 * descripteur. Il ne peut rien dire des modules déclarés `screen` : l'écran vit ici. Sans ce
 * fichier, basculer un module sur `screen` suffirait à le rendre navigable — et il s'ouvrirait
 * sur la liste générique, donc sur un écran qui n'est pas celui qu'on a écrit, sans que rien ne
 * le signale.
 *
 * La mémoire de ce projet documente précisément ce mode d'échec : des écrans mobiles orphelins
 * que ni `tsc` ni `jest` ne repèrent, parce qu'aucun des deux ne sait rien de la JOIGNABILITÉ.
 */
import fs from 'fs';
import path from 'path';
import { NATIVE_ADMIN_SCREENS } from '@/admin/nativeScreens';

const racineProjet = path.join(__dirname, '..', '..', '..', '..');

interface ModuleRegistre {
  key: string;
  coverage: string;
}

/**
 * Lit le registre de couverture PHP.
 *
 * Le fichier est une liste de littéraux à une ligne par module : l'extraction par expression
 * régulière est fiable sans interpréteur PHP, et le test de garde ci-dessous refuse qu'elle
 * cesse de capturer.
 */
function lireRegistre(): ModuleRegistre[] {
  const source = fs.readFileSync(
    path.join(racineProjet, 'config', 'admin_console.php'),
    'utf8',
  );

  return [...source.matchAll(/'key'\s*=>\s*'([^']+)'[^\n]*'coverage'\s*=>\s*'([^']+)'/g)].map(
    (m) => ({ key: m[1]!, coverage: m[2]! }),
  );
}

describe('écrans natifs de la console admin', () => {
  it('le registre PHP se lit bien', () => {
    // Garde-fou du test lui-même : une expression régulière qui ne capture plus rien rendrait
    // toutes les assertions suivantes vraies pour une mauvaise raison.
    const registre = lireRegistre();

    expect(registre.length).toBeGreaterThan(50);
    expect(registre.every((m) => ['pending', 'descriptor', 'report', 'screen'].includes(m.coverage))).toBe(true);
  });

  it('tout module déclaré « screen » a un écran natif', () => {
    const manquants = lireRegistre()
      .filter((m) => m.coverage === 'screen')
      .filter((m) => !NATIVE_ADMIN_SCREENS[m.key])
      .map((m) => m.key);

    expect(manquants).toEqual([]);
  });

  it('tout écran natif déclaré correspond à un module connu', () => {
    const cles = lireRegistre().map((m) => m.key);
    const orphelins = Object.keys(NATIVE_ADMIN_SCREENS).filter((k) => !cles.includes(k));

    expect(orphelins).toEqual([]);
  });

  it('tout écran natif déclaré est annoncé « screen » dans le registre', () => {
    // L'inverse : un écran écrit mais laissé en `descriptor` resterait invisible — du travail
    // livré que personne ne voit, et deux rendus concurrents pour le même module.
    const couverture = new Map(lireRegistre().map((m) => [m.key, m.coverage]));
    const tus = Object.keys(NATIVE_ADMIN_SCREENS).filter((k) => couverture.get(k) !== 'screen');

    expect(tus).toEqual([]);
  });

  it('tout écran natif déclaré est monté dans la pile admin', () => {
    // Un écran déclaré et non enregistré fait tomber la navigation sur une route inconnue.
    const navigateur = fs.readFileSync(
      path.join(__dirname, '..', '..', 'src', 'navigation', 'RootNavigator.tsx'),
      'utf8',
    );

    for (const { screen } of Object.values(NATIVE_ADMIN_SCREENS)) {
      expect(navigateur).toContain(`name="${screen}"`);
    }
  });
});
