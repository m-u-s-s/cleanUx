import fs from 'fs';
import path from 'path';

/**
 * LES SUITES DE `shared/` NE DOIVENT PAS CESSER D'ÊTRE COLLECTÉES EN SILENCE.
 *
 * Le code partagé — `@/api`, `@/auth`, `@/ui`, `@/webview`, `@/finance`, `@/parity` — vit dans un
 * espace de travail SANS configuration Jest à lui. Ses tests co-localisés ne s'exécutent que parce
 * que CE workspace-ci les ramasse, via l'entrée `roots` de `jest.config.ts`.
 *
 * C'est un fil unique, et sa rupture est muette : retirer cette ligne ne casse rien, n'échoue
 * nulle part, ne fait pas baisser un compteur qu'on regarde — seize suites cessent simplement
 * d'exister. Le dépôt a déjà connu cet état, et il a laissé derrière lui une note affirmant que
 * `shared/` n'était pas testable : on a donc évité d'y écrire des tests, pour une raison qui
 * n'était plus vraie.
 *
 * D'où ce garde-fou. Il ne vérifie pas un texte de configuration mais le FAIT : la racine déclarée
 * existe, et elle contient bien des fichiers de test. Une réorganisation de `shared/` qui
 * déplacerait ses tests le dirait aussi.
 *
 * Lancer `npx jest` DEPUIS `mobile/shared` échoue, et c'est normal : cet espace n'a ni preset Babel
 * ni configuration Jest. La commande juste est `npm test --workspace client`.
 */
describe('les suites du workspace partagé', () => {
  const racinePartagee = path.resolve(__dirname, '../../shared/src');

  const fichiersDeTest = (repertoire: string): string[] => {
    if (!fs.existsSync(repertoire)) {
      return [];
    }

    return fs.readdirSync(repertoire, { withFileTypes: true }).flatMap((entree) => {
      const chemin = path.join(repertoire, entree.name);

      if (entree.isDirectory()) {
        return entree.name === 'node_modules' ? [] : fichiersDeTest(chemin);
      }

      return /\.test\.tsx?$/.test(entree.name) ? [chemin] : [];
    });
  };

  it('sont déclarées dans les racines de ce workspace', () => {
    // eslint-disable-next-line @typescript-eslint/no-var-requires
    const config = require('../jest.config').default as { roots?: string[] };

    const racines = (config.roots ?? []).map((racine) =>
      path.resolve(__dirname, '..', racine.replace('<rootDir>', '.')),
    );

    expect(racines).toContain(racinePartagee);
  });

  it('existent réellement à l’endroit déclaré', () => {
    const trouves = fichiersDeTest(racinePartagee);

    // Pas de compte figé : un nombre exact deviendrait faux au premier test ajouté, et on le
    // corrigerait sans réfléchir. Ce qui compte est qu'il y en ait, et qu'ils soient là.
    expect(trouves.length).toBeGreaterThan(0);
  });
});
