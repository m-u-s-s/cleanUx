/**
 * Toute clé employée par un écran existe dans le catalogue.
 *
 * Une clé absente ne casse rien : `traduire` se rabat, et en dernier recours rend la clé nue.
 * C'est exactement ce qui rend ce test nécessaire — le défaut se voit à l'écran, pas à la
 * compilation. Vingt-quatre clés ont déjà été perdues ainsi, écartées par une comparaison de
 * sous-chaîne au moment de remplir le catalogue.
 */
import fs from 'fs';
import path from 'path';
import { fr } from '../catalogues/fr';

const RACINE = path.resolve(__dirname, '../../../../');
const DOSSIERS = ['client/src', 'provider/src', 'shared/src'];

/**
 * Les trois façons d'APPELER le traducteur avec une clé écrite en clair.
 *
 * On ne cherche pas « tout littéral qui ressemble à une clé » : `'agencies.manage'` est une
 * permission, pas une traduction, et la confondre inventerait des orphelines.
 */
const APPELS = [
  /\btr\('([^']+)'\)/g,
  /\btraduireMaintenant\('([^']+)'/g,
  /(?:libelleCle|descriptionCle)\s*:\s*'([^']+)'/g,
];

/** Pour la dérive, la question inverse : cette clé apparaît-elle QUELQUE PART dans les sources ? */
const CLE_EN_LITTERAL = /'([a-z_]+(?:\.[a-z0-9_]+)+)'/g;

function fichiers(dossier: string): string[] {
  const complet = path.join(RACINE, dossier);

  if (!fs.existsSync(complet)) {
    return [];
  }

  return fs.readdirSync(complet, { withFileTypes: true }).flatMap(entree => {
    const p = path.join(dossier, entree.name);

    if (entree.isDirectory()) {
      return entree.name === '__tests__' || entree.name === 'node_modules' ? [] : fichiers(p);
    }

    return entree.name.endsWith('.tsx') || entree.name.endsWith('.ts') ? [p] : [];
  });
}

function balayer(motifs: RegExp[]): Map<string, string> {
  const trouvees = new Map<string, string>();

  for (const dossier of DOSSIERS) {
    for (const fichier of fichiers(dossier)) {
      const source = fs.readFileSync(path.join(RACINE, fichier), 'utf8');

      for (const motif of motifs) {
        for (const m of source.matchAll(motif)) {
          if (!trouvees.has(m[1])) {
            trouvees.set(m[1], fichier);
          }
        }
      }
    }
  }

  return trouvees;
}

describe('les clés de traduction', () => {
  const appelees = balayer(APPELS);

  it('sont toutes présentes dans le catalogue français', () => {
    const orphelines = [...appelees.entries()]
      .filter(([cle]) => !(cle in fr))
      .map(([cle, fichier]) => `${cle}  (${fichier})`)
      .sort();

    expect(orphelines).toEqual([]);
  });

  /** LE TÉMOIN : le balayage trouve bien des clés — sinon il ne prouverait rien. */
  it('témoin : le balayage voit les écrans', () => {
    expect(appelees.size).toBeGreaterThan(300);
  });

  /**
   * Une clé du catalogue que plus rien n'emploie est du poids mort — on la compte.
   *
   * Ici la question est l'inverse de celle du premier test, donc le balayage aussi : une clé peut
   * être rangée dans une table (`pending: 'statut.en_attente'`) sans jamais apparaître dans un
   * appel. Un littéral trop large invente des orphelines ; ici il ne peut qu'éviter un faux
   * « inutilisée », ce qui est le bon sens de l'erreur.
   */
  it('le catalogue ne dérive pas trop loin de ce que les écrans emploient', () => {
    const citees = balayer([CLE_EN_LITTERAL]);
    const inutilisees = Object.keys(fr).filter(cle => !citees.has(cle));

    // Les clés du socle (`langue.*`, `commun.*`) sont employées par `t(`, pas `tr(`.
    expect(inutilisees.length).toBeLessThan(20);
  });
});
