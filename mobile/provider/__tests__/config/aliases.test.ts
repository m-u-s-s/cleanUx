import fs from 'fs';
import path from 'path';

/**
 * Le tsconfig déclarait des alias que le résolveur Babel ignorait.
 *
 * `tsc` passait, l'application plantait à l'import — un mode d'échec qu'aucun typage ne signale,
 * et que Jest ne signale pas non plus puisqu'il résout par sa propre table. Trois tables décrivent
 * la même chose ; ce test refuse qu'elles divergent.
 */
describe('alias de modules partagés', () => {
  const root = path.join(__dirname, '..', '..');
  const read = (p: string) => fs.readFileSync(path.join(root, p), 'utf8');

  /** Les alias `@/x` que le tsconfig fait pointer vers le paquet partagé. */
  const sharedAliases = (): string[] => {
    const tsconfig = read('tsconfig.json');
    const found = [...tsconfig.matchAll(/"(@\/[a-zA-Z]+)":\s*\["\.\.\/shared/g)].map((m) => m[1]!);

    return [...new Set(found)];
  };

  it('le tsconfig déclare bien une famille d’alias partagés', () => {
    // Garde-fou du test lui-même : une expression régulière qui ne capture plus rien rendrait
    // les deux assertions suivantes vraies pour une mauvaise raison.
    expect(sharedAliases().length).toBeGreaterThan(10);
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
