import fs from 'fs';
import path from 'path';

/**
 * TROIS TABLES DÉCRIVENT LES MÊMES CHEMINS SANS SE VÉRIFIER.
 *
 * L'application prestataire tenait ce garde-fou depuis longtemps ; la cliente ne l'avait pas — et
 * c'est chez elle que la divergence s'est installée. `@brio/shared` était déclaré dans le tsconfig
 * et ABSENT de `babel.config.js` comme de `jest.config.ts`. Rien ne cassait : le lien symbolique
 * de l'espace de travail résout le paquet par son nom, `tsc` reste vert, l'application démarre.
 *
 * Ce que ce lien ne résout PAS, ce sont les sous-chemins. `@brio/shared/format` — importé depuis
 * `src/lib/format.ts` — pointait vers `mobile/shared/format`, un dossier qui n'existe pas. Le
 * défaut n'attendait que le premier test qui traverse ce fichier.
 *
 * Ce test refuse la divergence dans les deux applications, pour les deux formes d'alias.
 */
describe('alias de modules partagés', () => {
  const root = path.join(__dirname, '..', '..');
  const read = (p: string) => fs.readFileSync(path.join(root, p), 'utf8');

  /** Les alias `@/x` ET le paquet `@brio/shared` que le tsconfig fait pointer vers `../shared`. */
  const sharedAliases = (): string[] => {
    const tsconfig = read('tsconfig.json');
    const dossiers = [...tsconfig.matchAll(/"(@\/[a-zA-Z]+)":\s*\["\.\.\/shared/g)].map((m) => m[1]!);
    const paquet = [...tsconfig.matchAll(/"(@brio\/shared)(?:\/\*)?":\s*\["\.\.\/shared/g)].map((m) => m[1]!);

    return [...new Set([...dossiers, ...paquet])];
  };

  it('le tsconfig déclare bien une famille d’alias partagés', () => {
    // Garde-fou du test lui-même : une expression régulière qui ne capture plus rien rendrait les
    // assertions suivantes vraies pour une mauvaise raison.
    expect(sharedAliases().length).toBeGreaterThan(10);
  });

  it('le paquet partagé est déclaré par son nom', () => {
    // C'est l'entrée précise qui manquait, et que l'ancienne expression régulière ne voyait pas.
    expect(sharedAliases()).toContain('@brio/shared');
  });

  it('babel résout tous les alias partagés du tsconfig', () => {
    const babel = read('babel.config.js');

    for (const alias of sharedAliases()) {
      expect(babel).toContain(`'${alias}'`);
    }
  });

  it('jest résout tous les alias partagés du tsconfig', () => {
    const jestConfig = read('jest.config.ts');

    for (const alias of sharedAliases()) {
      // Le motif générique `^@/(.*)$` renvoie vers src/ : il ne compte pas comme une résolution
      // vers le paquet partagé. On exige une entrée nommée.
      expect(jestConfig).toContain(`'^${alias}`);
    }
  });
});
