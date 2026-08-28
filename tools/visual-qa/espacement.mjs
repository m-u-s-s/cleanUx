import { chromium } from 'playwright';
import { loginAs } from './check.mjs';
import { loadModules } from './modules.mjs';

const BASE = process.env.VQA_BASE ?? 'http://127.0.0.1:8000';
const SEUIL = 8; // px : en dessous, deux cartes se touchent.

/* Deux blocs de premier niveau qui se touchent DANS le slot d'une coquille de page. */
const MESURE = (seuil) => {
  const style = (el) => getComputedStyle(el);

  const visible = (el) => {
    const s = style(el);
    const r = el.getBoundingClientRect();
    return s.display !== 'none' && s.visibility !== 'hidden'
      && s.position !== 'fixed' && s.position !== 'absolute'
      && r.height > 40 && r.width > 200;
  };

  /* Le conteneur de contenu : on descend tant qu'un seul enfant enveloppe le reste. */
  const conteneurs = [];
  const racine = document.querySelector('main#main-content') ?? document.querySelector('main') ?? document.body;

  let noeud = racine;
  for (let i = 0; i < 6; i++) {
    const enfants = [...noeud.children].filter(visible);
    if (enfants.length === 1) { noeud = enfants[0]; continue; }
    if (enfants.length > 1) { conteneurs.push(noeud); }
    break;
  }

  // Le slot d'une coquille de page, quand il y en a une.
  for (const hero of document.querySelectorAll('.brio-hero')) {
    for (const slot of hero.querySelectorAll(':scope > .relative')) {
      let enfants = [...slot.children].filter(visible);
      while (enfants.length === 1 && enfants[0].children.length > 1) {
        enfants = [...enfants[0].children].filter(visible);
      }
      if (enfants.length > 1) conteneurs.push(enfants[0].parentElement);
    }
  }

  const resultats = [];
  const vus = new Set();

  for (const conteneur of conteneurs) {
    if (vus.has(conteneur)) continue;
    vus.add(conteneur);

    const enfants = [...conteneur.children].filter(visible);

    for (let i = 1; i < enfants.length; i++) {
      const avant = enfants[i - 1];
      const apres = enfants[i];
      const a = avant.getBoundingClientRect();
      const b = apres.getBoundingClientRect();
      const ecart = Math.round((b.top - a.bottom) * 10) / 10;

      if (ecart >= 0 && ecart < seuil) {
        resultats.push({
          ecart,
          avant: (avant.className || '').toString().slice(0, 40),
          apres: (apres.className || '').toString().slice(0, 40),
        });
      }
    }
  }

  return resultats;
};

const modules = loadModules().filter((m) => !m.deferred);
const parCred = {};
for (const m of modules) (parCred[m.credKey ?? 'public'] ??= []).push(m);

const navigateur = await chromium.launch();
const touchees = [];
const injoignables = [];
let vues = 0;
let avecCoquille = 0;

for (const [cred, pages] of Object.entries(parCred)) {
  const context = await navigateur.newContext({ viewport: { width: 1280, height: 900 }, serviceWorkers: 'block' });
  if (cred !== 'public') await loginAs(context, BASE, cred).catch(() => {});

  const page = await context.newPage();

  for (const m of pages) {
    try {
      const r = await page.goto(`${BASE}${m.path}`, { waitUntil: 'domcontentloaded', timeout: 25000 });
      if (!r || r.status() >= 400) {
        injoignables.push(`${m.path} (HTTP ${r ? r.status() : 0})`);
        continue;
      }
      await page.waitForTimeout(450);
      const colles = await page.evaluate(MESURE, SEUIL);
      vues++;
      if (await page.locator('.brio-hero').count()) avecCoquille++;
      if (colles.length) touchees.push({ cle: m.key, chemin: m.path, cred, colles });
    } catch (e) {
      injoignables.push(`${m.path} (${String(e.message).slice(0, 60)})`);
    }
  }

  await context.close();
}

await navigateur.close();

console.log(`
=== ESPACEMENT DES BLOCS — ${vues} pages mesurees, dont ${avecCoquille} avec une coquille de page ===
`);

if (injoignables.length) {
  console.log(`Injoignables (${injoignables.length}) :`);
  for (const p of injoignables) console.log(`  ${p}`);
  console.log();
}

console.log(`Pages ou deux blocs de premier niveau se touchent : ${touchees.length}
`);

for (const t of touchees) {
  console.log(`${t.cred.padEnd(17)} ${t.chemin}`);
  for (const c of t.colles) {
    console.log(`      ${String(c.ecart).padStart(5)} px   ${c.avant}  ||  ${c.apres}`);
  }
}

process.exit(touchees.length ? 1 : 0);
