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

    return entree.name.endsWith('.tsx') ? [p] : [];
  });
}

/** @returns les clés employées, avec le fichier qui les emploie */
function clesEmployees(): Map<string, string> {
  const trouvees = new Map<string, string>();

  for (const dossier of DOSSIERS) {
    for (const fichier of fichiers(dossier)) {
      const source = fs.readFileSync(path.join(RACINE, fichier), 'utf8');

      /*
       * DEUX FORMES, PAS UNE. `tr('cle')` est la plus courante, mais une constante de module range
       * la clé dans un littéral — `libelleCle: 'invoices.tous'` — et l'écran la traduit au rendu.
       * Ne chercher que la première déclarerait ces clés-là orphelines.
       */
      for (const m of source.matchAll(/\btr\('([^']+)'\)/g)) {
        if (!trouvees.has(m[1])) {
          trouvees.set(m[1], fichier);
        }
      }

      for (const m of source.matchAll(/(?:libelleCle|descriptionCle)\s*:\s*'([^']+)'/g)) {
        if (!trouvees.has(m[1])) {
          trouvees.set(m[1], fichier);
        }
      }
    }
  }

  return trouvees;
}

describe('les clés de traduction', () => {
  const employees = clesEmployees();

  it('sont toutes présentes dans le catalogue français', () => {
    const orphelines = [...employees.entries()]
      .filter(([cle]) => !(cle in fr))
      .map(([cle, fichier]) => `${cle}  (${fichier})`)
      .sort();

    expect(orphelines).toEqual([]);
  });

  /** LE TÉMOIN : le balayage trouve bien des clés — sinon il ne prouverait rien. */
  it('témoin : le balayage voit les écrans', () => {
    expect(employees.size).toBeGreaterThan(300);
  });

  /** Une clé du catalogue que plus aucun écran n'emploie est du poids mort — on la compte. */
  it('le catalogue ne dérive pas trop loin de ce que les écrans emploient', () => {
    const inutilisees = Object.keys(fr).filter(cle => !employees.has(cle));

    // Les clés du socle (`langue.*`, `commun.*`) sont employées par `t(`, pas `tr(`.
    expect(inutilisees.length).toBeLessThan(20);
  });
});
