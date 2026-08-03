/**
 * Aucune couleur neutre codée en dur dans un composant.
 *
 * POURQUOI CE TEST EXISTE. Le mode sombre existait déjà et ne fonctionnait pas : sur 137 fichiers
 * d'interface, 7 consultaient le thème. Les autres écrivaient `color: colors.surface[900]` — du
 * quasi-noir, sur un fond devenu sombre. Personne ne l'a fait exprès : c'est ce qui arrive quand
 * rien ne s'y oppose. Ce fichier est ce qui s'y oppose.
 *
 * CE QU'IL AUTORISE :
 *   - les couleurs SÉMANTIQUES (succès, alerte, danger, marque, accent) : leur sens ne dépend pas
 *     du fond, et les thématiser les viderait de ce sens ;
 *   - les valeurs `transparent` et les couleurs déjà exprimées en `rgba(…)`, qui sont des voiles
 *     et non des aplats ;
 *   - les fichiers listés dans EXCEPTIONS, chacun avec sa raison écrite.
 *
 * CE QU'IL REFUSE : une couleur de la rampe neutre ou un hexadécimal figé sur une propriété de
 * couleur. C'est exactement le geste qui a produit la dette.
 */
import fs from 'fs';
import path from 'path';

const RACINE = path.join(__dirname, '..', '..', '..');

/** Fichiers autorisés à porter des couleurs en dur, et pourquoi. */
const EXCEPTIONS: Record<string, string> = {
  'shared/src/theme/colors.ts': 'la palette elle-même',
  'shared/src/theme/useThemeColors.ts': 'la fabrique de jetons',
  'shared/src/ui/authShell.tsx': 'écran d’accueil à identité visuelle propre, délibérément hors thème',
  'provider/src/screens/PresenceScanScreen.tsx':
    'fond de viseur caméra : le noir n’est pas une couleur d’interface mais l’absence d’image',
};

/**
 * Familles sémantiques : leur sens ne dépend pas du fond.
 *
 * SAUF les extrémités CLAIRES des rampes (50, 100) posées en FOND. `colors.brand[50]` est un
 * indigo quasi-blanc : il porte la marque sur fond clair, et rend le texte invisible sur fond
 * sombre. C'est un neutre déguisé en couleur sémantique — trouvé sur l'écran Apparence, où il
 * surlignait le mode sélectionné.
 */
const SEMANTIQUE = /colors\.(success|warning|danger|brand|accent)\b/;

const NEUTRE_DEGUISE =
  /(backgroundColor|borderColor)\s*:\s*colors\.(success|warning|danger|brand|accent)\[(50|100)\]/;

/** Une couleur neutre ou un hexadécimal figé sur une propriété de couleur. */
const INTERDIT =
  /(color|backgroundColor|borderColor|borderTopColor|borderBottomColor|borderLeftColor|borderRightColor|tintColor|shadowColor)\s*:\s*(colors\.surface\[|'#|"#|`#)/;

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

describe('couleurs codées en dur', () => {
  it('le balayage couvre bien les trois arborescences', () => {
    // Un balayage qui ne trouve plus rien rendrait l'assertion suivante vraie pour une mauvaise
    // raison — le mode d'échec le plus discret d'un test de ce genre.
    expect(CIBLES.length).toBeGreaterThan(100);
  });

  it('aucun composant ne fige une couleur neutre', () => {
    const fautifs: string[] = [];

    for (const chemin of CIBLES) {
      const relatif = path.relative(RACINE, chemin).split(path.sep).join('/');

      if (EXCEPTIONS[relatif]) {
        continue;
      }

      fs.readFileSync(chemin, 'utf8')
        .split('\n')
        .forEach((ligne, i) => {
          if (NEUTRE_DEGUISE.test(ligne)) {
            fautifs.push(`${relatif}:${i + 1}`);

            return;
          }
          if (SEMANTIQUE.test(ligne)) {
            return;
          }
          if (INTERDIT.test(ligne)) {
            fautifs.push(`${relatif}:${i + 1}`);
          }
        });
    }

    expect(fautifs).toEqual([]);
  });

  it('chaque exception porte une raison écrite', () => {
    for (const [fichier, raison] of Object.entries(EXCEPTIONS)) {
      // Une exception sans raison est une exception qu'on ne saura pas relire dans six mois.
      expect(raison.length).toBeGreaterThan(12);
      expect(fs.existsSync(path.join(RACINE, fichier))).toBe(true);
    }
  });
});
