/**
 * LE FRANÇAIS QUI RESTE EN DUR — et les trois formes qui ont échappé à trois balayages.
 *
 * 1. `"..."` en guillemets doubles. Le premier plan ne lisait que `'...'` et `attribut="..."` :
 *    une chaîne écrite en doubles PARCE QU'ELLE CONTIENT UNE APOSTROPHE FRANÇAISE — donc les
 *    phrases les plus françaises du dépôt — n'a jamais été vue. 27 textes.
 *
 * 2. Le texte JSX autour d'une expression. `Bonjour{user?.name ? …}` : le balayage raisonnait
 *    par ligne et sautait toute ligne portant une apostrophe. La salutation des DEUX accueils.
 *
 * 3. Le texte JSX sur plusieurs lignes, qu'aucune regex sur les littéraux n'atteint. 132 textes.
 *
 * Ce que ce test NE couvre pas : les libellés courts sans accent ni mot-outil (« Profil »).
 * Aucune heuristique ne les distingue d'un identifiant ; ils se rattrapent à la lecture.
 */
import fs from 'fs';
import path from 'path';

const RACINE = path.resolve(__dirname, '../../../../');
const DOSSIERS = ['client/src', 'provider/src', 'shared/src'];

/**
 * CE QUI RESTE EN FRANÇAIS EXPRÈS.
 *
 * Les deux documents légaux et le contrat prestataire par défaut : un texte contractuel ne se
 * traduit pas à la volée, et le catalogue d'interface n'est pas le foyer d'un document.
 */
const VOLONTAIRE = [
  'client/src/screens/LegalScreen.tsx',
  'provider/src/screens/LegalScreen.tsx',
  'provider/src/screens/onboarding/steps.tsx',
];

const ACCENT = /[àâäéèêëîïôöùûüçœÀÂÉÈÊËÎÔÙÛÇ]/;
const MOTS = /\b(le|la|les|un|une|des|du|de|vous|votre|vos|est|sont|pour|dans|avec|sur|pas|plus|que|qui|ce|cette|aucun|aucune|autre|sans|chez|leur|nos|notre|bonjour|bonsoir|merci|mes|ma|mon)\b/i;

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

/** Commentaires et imports retirés : ce qui reste est ce qui s'exécute. */
function nu(source: string): string {
  return source
    .replace(/\/\*[\s\S]*?\*\//g, m => m.replace(/[^\n]/g, ' '))
    .replace(/^\s*\/\/.*$/gm, m => ' '.repeat(m.length))
    .replace(/^import .*$/gm, m => ' '.repeat(m.length));
}

const duFrancais = (v: string) =>
  (ACCENT.test(v) || MOTS.test(v)) && /[a-zà-ÿ]{3}/i.test(v) && !/^[a-z0-9_-]+$/.test(v);

/** Les chaînes en guillemets doubles qui portent du français, hors attribut JSX. */
export function chainesEnGuillemetsDoubles(source: string): string[] {
  const s = nu(source);

  return [...s.matchAll(/"([^"\n]{4,})"/g)]
    .filter(m => duFrancais(m[1] ?? ''))
    .filter(m => !/\b[a-zA-Z]+=$/.test(s.slice(Math.max(0, m.index - 30), m.index)))
    .map(m => m[1] ?? '');
}

/** Retire les groupes `{...}` en respectant l'imbrication : reste le texte que l'écran affiche. */
function sansExpressions(s: string): string {
  let sortie = '';
  let profondeur = 0;

  for (const c of s) {
    if (c === '{') profondeur++;
    else if (c === '}') { if (profondeur > 0) profondeur--; }
    else if (profondeur === 0) sortie += c;
  }

  return sortie;
}

/** Le texte JSX affiché tel quel, expressions retirées. */
export function texteJsxEnDur(source: string): string[] {
  const s = nu(source);
  const trouves: string[] = [];

  for (const m of s.matchAll(/>([^<>]*)</g)) {
    const brut = m[1] ?? '';

    /* Un `>` de generique ou de flechee ouvre un faux segment : ce qui suit est du
       code. Le point-virgule suivi d'une espace ne se rencontre pas dans un libelle. */
    if (/=>|\binterface\b|\bexport\b|\bunknown\b|React\.|;\s/.test(brut)) continue;

    const texte = sansExpressions(brut).replace(/\s+/g, ' ').trim();

    if (texte.length >= 3 && duFrancais(texte)) {
      trouves.push(texte);
    }
  }

  return trouves;
}

describe('plus de français en dur', () => {
  const sources = DOSSIERS.flatMap(fichiers)
    .filter(f => !VOLONTAIRE.includes(f.replace(/\\/g, '/')))
    .filter(f => !/i18n[\\/]catalogues/.test(f))
    .map(f => ({ f, source: fs.readFileSync(path.join(RACINE, f), 'utf8') }));

  it('témoin : le balayage voit les fichiers', () => {
    expect(sources.length).toBeGreaterThan(200);
  });

  it('aucune chaîne française en guillemets doubles', () => {
    const restes = sources.flatMap(({ f, source }) =>
      chainesEnGuillemetsDoubles(source).map(v => `${f}  "${v}"`)
    );

    expect(restes).toEqual([]);
  });

  it('aucun texte français écrit dans le JSX', () => {
    const restes = sources.flatMap(({ f, source }) =>
      texteJsxEnDur(source).map(v => `${f}  « ${v} »`)
    );

    expect(restes).toEqual([]);
  });

  /** LE TÉMOIN : sans lui, un détecteur cassé rendrait les deux tests verts pour rien. */
  it('témoin : les détecteurs reconnaissent ce qu’ils cherchent', () => {
    expect(chainesEnGuillemetsDoubles('const m = "Impossible d\'envoyer le lien.";')).toHaveLength(1);
    expect(chainesEnGuillemetsDoubles('const m = "provider_map.position";')).toEqual([]);
    expect(chainesEnGuillemetsDoubles('<Text label="Une réponse" />')).toEqual([]);

    expect(texteJsxEnDur("<Text>Bonjour{user?.name ? `, ${p}` : ''}</Text>")).toHaveLength(1);
    expect(texteJsxEnDur("<Text>{tr('commun.bonjour')}</Text>")).toEqual([]);
  });
});
