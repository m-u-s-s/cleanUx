/*
 * LE SOCLE DES PARCOURS.
 *
 * Chaque parcours est une fonction qui recoit un contexte de navigateur et rend
 * `{ ok, etape, detail }`. Le NOM DE L'ETAPE est ce qui compte : « échec » sans dire où
 * oblige a tout rejouer a la main.
 */
import { loginAs } from '../check.mjs';

export const BASE = process.env.BASE ?? 'http://127.0.0.1:8000';

/** Un identifiant unique par execution : deux passages ne se marchent pas dessus. */
export const jeton = () => Date.now().toString(36) + Math.random().toString(36).slice(2, 6);

/** Ouvre une page, et echoue si elle ne repond pas 200. */
export async function ouvrir(page, chemin, etape) {
  const r = await page.goto(BASE + chemin, { waitUntil: 'domcontentloaded', timeout: 45000 });
  const statut = r ? r.status() : 0;

  if (statut !== 200) {
    throw new EchecDEtape(etape, `${chemin} rend HTTP ${statut}`);
  }

  await page.waitForTimeout(600);
}

export class EchecDEtape extends Error {
  constructor(etape, detail) {
    super(`${etape} : ${detail}`);
    this.etape = etape;
    this.detail = detail;
  }
}

/** Attend qu'un texte apparaisse ; sinon, dit ce qu'il y avait a la place. */
export async function attendreLeTexte(page, texte, etape, delai = 8000) {
  try {
    await page.waitForFunction(
      (t) => document.body.innerText.includes(t),
      texte,
      { timeout: delai },
    );
  } catch (e) {
    const apercu = await page.evaluate(() => document.body.innerText.slice(0, 260).replace(/\s+/g, ' '));
    throw new EchecDEtape(etape, `« ${texte} » introuvable. La page dit : « ${apercu} »`);
  }
}

/** Clique le premier selecteur qui existe ; sinon dit lesquels ont ete essayes. */
export async function cliquer(page, selecteurs, etape) {
  for (const s of [].concat(selecteurs)) {
    const el = await page.$(s);

    if (el) {
      await el.click();
      await page.waitForTimeout(700);

      return s;
    }
  }

  throw new EchecDEtape(etape, `aucun de ces éléments : ${[].concat(selecteurs).join(' | ')}`);
}

export async function remplir(page, selecteur, valeur, etape) {
  const el = await page.$(selecteur);

  if (!el) {
    throw new EchecDEtape(etape, `champ introuvable : ${selecteur}`);
  }

  await el.fill(String(valeur));
}

/*
 * UNE CONNEXION PAR ROLE, REUTILISEE.
 *
 * Fortify limite a cinq tentatives par minute. Vingt-trois parcours qui se connectent chacun
 * de leur cote se font refuser au sixieme, et le harnais accuse le produit d'un defaut qui
 * n'est que sa propre impatience. On se connecte une fois, on garde l'etat de session.
 */
const sessions = new Map();

export async function contexteConnecte(navigateur, cred) {
  if (!cred) {
    return navigateur.newContext({ viewport: { width: 1440, height: 1000 }, serviceWorkers: 'block' });
  }

  if (!sessions.has(cred)) {
    const premier = await navigateur.newContext({ viewport: { width: 1440, height: 1000 }, serviceWorkers: 'block' });

    await loginAs(premier, BASE, cred);
    sessions.set(cred, await premier.storageState());

    await premier.close();
  }

  return navigateur.newContext({
    viewport: { width: 1440, height: 1000 },
    serviceWorkers: 'block',
    storageState: sessions.get(cred),
  });
}

/** Joue un parcours et rend son verdict, sans jamais laisser une exception filer. */
export async function jouer(nom, fonction) {
  try {
    const detail = await fonction();

    return { nom, ok: true, detail: detail ?? '' };
  } catch (e) {
    return {
      nom,
      ok: false,
      etape: e.etape ?? 'inattendu',
      detail: e.detail ?? String(e.message).split('\n')[0].slice(0, 200),
    };
  }
}
