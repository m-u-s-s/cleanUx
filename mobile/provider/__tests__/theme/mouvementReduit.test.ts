/**
 * Une animation doit consulter le réglage « réduire les animations » du système.
 *
 * POURQUOI CE TEST EXISTE. Le web respecte `prefers-reduced-motion` dans onze feuilles de style.
 * Le natif avait le crochet — `useReducedMotion` dans `shared/src/ui/a11y.ts` — et sept fichiers
 * animaient sans jamais le consulter : cinq transitions d'écran, le bandeau hors-ligne, et le
 * secouement d'un champ en erreur, qui est exactement le mouvement qui gêne.
 *
 * CE QU'IL REFUSE : un fichier qui anime sans qu'aucun de ses chemins ne passe par le réglage.
 * CE QU'IL AUTORISE : les fichiers d'EXCEPTIONS, chacun avec sa raison écrite.
 */
import fs from 'fs';
import path from 'path';

const RACINE = path.join(__dirname, '..', '..', '..');

/** Fichiers autorisés à animer sans consulter le réglage, et pourquoi. */
const EXCEPTIONS: Record<string, string> = {
  'shared/src/ui/a11y.ts': 'le réglage lui-même',
};

/** Ce qui constitue une animation. */
const ANIME = /withTiming|withSpring|withSequence|withRepeat|entering=|exiting=|Animated\.timing|Animated\.spring/;

/** Ce qui prouve qu'elle est conditionnée. */
const CONSULTE = /useReducedMotion|useEntree|useDuree|reduceMotion|reducedMotion/;

function fichiersDe(dossier: string): string[] {
  if (!fs.existsSync(dossier)) {
    return [];
  }

  const sortie: string[] = [];

  for (const entree of fs.readdirSync(dossier, { withFileTypes: true })) {
    const complet = path.join(dossier, entree.name);

    if (entree.isDirectory()) {
      if (['node_modules', '__tests__', '__mocks__', '.expo', 'e2e'].includes(entree.name)) {
        continue;
      }
      sortie.push(...fichiersDe(complet));
    } else if (/\.tsx?$/.test(entree.name)) {
      sortie.push(complet);
    }
  }

  return sortie;
}

const CIBLES = ['provider/src', 'shared/src', 'client/src'].flatMap((d) =>
  fichiersDe(path.join(RACINE, d)),
);

function relatif(complet: string): string {
  return path.relative(RACINE, complet).split(path.sep).join('/');
}

describe('mouvement réduit', () => {
  it('le balayage couvre bien les trois arborescences', () => {
    // Un balayage vide rendrait l'assertion suivante vraie pour une mauvaise raison.
    expect(CIBLES.length).toBeGreaterThan(100);
  });

  it('témoin — il existe des fichiers qui animent, et qui consultent le réglage', () => {
    const animent = CIBLES.filter((f) => ANIME.test(fs.readFileSync(f, 'utf8')));
    const consultent = CIBLES.filter((f) => CONSULTE.test(fs.readFileSync(f, 'utf8')));

    // Sans ce témoin, une expression cassée ne trouverait plus rien et le test passerait au vert.
    expect(animent.length).toBeGreaterThan(10);
    expect(consultent.length).toBeGreaterThan(10);
  });

  it('aucun fichier n’anime sans consulter le réglage', () => {
    const fautifs: string[] = [];

    for (const fichier of CIBLES) {
      const nom = relatif(fichier);

      if (EXCEPTIONS[nom]) {
        continue;
      }

      const source = fs.readFileSync(fichier, 'utf8');

      if (ANIME.test(source) && !CONSULTE.test(source)) {
        fautifs.push(`${nom} : anime sans consulter useReducedMotion`);
      }
    }

    expect(fautifs).toEqual([]);
  });

  it('aucune exception n’est devenue inutile', () => {
    const perimees: string[] = [];

    for (const [nom, raison] of Object.entries(EXCEPTIONS)) {
      const complet = path.join(RACINE, nom);

      if (!fs.existsSync(complet)) {
        perimees.push(`${nom} : fichier absent — retirer l’exception (${raison})`);
      }
    }

    expect(perimees).toEqual([]);
  });
});
