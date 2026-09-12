/**
 * DEUX DÉFAUTS QUI NE SE VOIENT NI À LA COMPILATION NI À L'EXÉCUTION.
 *
 * 1. UNE TABLE DE MODULE QUI TRADUIT. `const LIBELLES = { owner: traduireMaintenant('…') }`
 *    s'évalue au CHARGEMENT du module : la langue s'y fige à la première ouverture, et le
 *    changement de langue ne la rattrape jamais. Neuf tables étaient dans ce cas.
 *    La forme juste : la table porte la CLÉ, et la lecture traduit.
 *
 * 2. UN APPEL COLLÉ À DU TEXTE. Un remplacement automatique a pris une sous-chaîne entre
 *    apostrophes À L'INTÉRIEUR d'une phrase : « Les consignes d'accès ne sont montrées qu'une
 *    fois » est devenu « Les consignes dtr('places.acces…')une fois ». Ça compile, ça s'affiche,
 *    et c'est illisible. Sept textes étaient cassés ainsi, dont les CGU.
 */
import fs from 'fs';
import path from 'path';

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

    return entree.name.endsWith('.tsx') || entree.name.endsWith('.ts') ? [p] : [];
  });
}

/**
 * Les déclarations de premier niveau qui traduisent SANS différer l'appel.
 *
 * Une flèche ou un `function` dans la déclaration suffit à la rendre paresseuse : elle se relit
 * alors à chaque appel, ce qui est la forme correcte.
 */
export function declarationsQuiFigentLaLangue(source: string): string[] {
  const lignes = source.split(/\r?\n/);
  const trouvees: string[] = [];

  for (let i = 0; i < lignes.length; i++) {
    const ligne = lignes[i] ?? '';

    if (!/^(export )?(const|let|var) /.test(ligne)) {
      continue;
    }

    let profondeur = 0;
    let bloc = '';
    let j = i;

    for (; j < lignes.length && j - i <= 120; j++) {
      const courante = lignes[j] ?? '';
      bloc += courante + '\n';

      for (const c of courante) {
        if ('({['.includes(c)) profondeur++;
        else if (')}]'.includes(c)) profondeur--;
      }

      if (profondeur <= 0 && /;\s*$/.test(courante)) break;
    }

    i = j;

    if (!/\btraduireMaintenant\(|\btr\(/.test(bloc)) continue;
    if (/=>|\bfunction\b/.test(bloc)) continue;

    trouvees.push(ligne.trim());
  }

  return trouvees;
}

/** Un appel au traducteur soudé au mot qui le précède : le texte a été coupé en deux. */
export function appelsCollesAuTexte(source: string): string[] {
  const sansCommentaires = source
    .replace(/\/\*[\s\S]*?\*\//g, m => m.replace(/[^\n]/g, ' '))
    .replace(/^\s*\/\/.*$/gm, m => ' '.repeat(m.length));

  return [...sansCommentaires.matchAll(/[A-Za-zÀ-ÿ0-9](?:tr|traduireMaintenant)\(['"]/g)]
    .map(m => m[0]);
}

describe('la langue ne se fige pas', () => {
  const sources = DOSSIERS.flatMap(fichiers).map(f => ({
    f,
    source: fs.readFileSync(path.join(RACINE, f), 'utf8'),
  }));

  it('témoin : le balayage voit les fichiers', () => {
    expect(sources.length).toBeGreaterThan(200);
  });

  it('aucune table de module ne traduit au chargement', () => {
    const coupables = sources
      .filter(({ f }) => !/lib[\\/]offlineQueue/.test(f))
      .flatMap(({ f, source }) =>
        declarationsQuiFigentLaLangue(source).map(d => `${f}  ${d}`)
      );

    expect(coupables).toEqual([]);
  });

  /** LE TÉMOIN : sans lui, un détecteur cassé rendrait ce test vert pour rien. */
  it('témoin : le détecteur reconnaît une table figée', () => {
    const figee = "const LIBELLES = {\n  owner: traduireMaintenant('roles.proprietaire'),\n};";
    const paresseuse = "const libelles = () => ({\n  owner: traduireMaintenant('roles.proprietaire'),\n});";

    expect(declarationsQuiFigentLaLangue(figee)).toHaveLength(1);
    expect(declarationsQuiFigentLaLangue(paresseuse)).toEqual([]);
  });

  it('aucun appel au traducteur n’est collé au milieu d’une phrase', () => {
    const coupables = sources.flatMap(({ f, source }) =>
      appelsCollesAuTexte(source).map(m => `${f}  …${m}`)
    );

    expect(coupables).toEqual([]);
  });

  /** LE TÉMOIN : le détecteur doit voir la coupure, et laisser passer un appel normal. */
  it('témoin : le détecteur reconnaît un texte coupé', () => {
    const casse = "Les consignes dtr('places.acces_ne_sont_montrees')une fois son arrivée.";
    const sain = "  <Text>{tr('places.consignes')}</Text>";

    expect(appelsCollesAuTexte(casse)).toHaveLength(1);
    expect(appelsCollesAuTexte(sain)).toEqual([]);
  });
});
