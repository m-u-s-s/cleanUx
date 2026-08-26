/**
 * QUELLES PAGES N'ONT PAS DE TITRE DE NIVEAU 1 — mesuré dans le RENDU, pas dans les sources.
 *
 * Un `<h1>` peut venir d'un composant Livewire, d'un layout ou d'un partiel : chercher
 * `<h1` dans la vue routée ne prouve rien. Ce fichier ouvre chaque page comme le harnais,
 * avec la même connexion, et relève ce que le navigateur a réellement construit.
 *
 * Il rapporte aussi le PREMIER titre trouvé, quel que soit son niveau : la correction est
 * presque toujours de promouvoir un `<h2>` existant, pas d'inventer une phrase.
 *
 *   node tools/visual-qa/titres.mjs
 */
import { chromium } from 'playwright';
import { loadModules } from './modules.mjs';
import { loginAs } from './check.mjs';

const BASE = process.env.VQA_BASE ?? 'http://127.0.0.1:8000';

const releve = async (page) => page.evaluate(() => {
  const propre = (el) => (el?.textContent ?? '').replace(/\s+/g, ' ').trim().slice(0, 60);

  const h1 = [...document.querySelectorAll('h1')].filter((e) => e.offsetParent !== null);
  const premier = document.querySelector('h2, h3, [class*="titre"], [class*="title"]');

  return {
    h1: h1.length,
    texteH1: h1.length ? propre(h1[0]) : null,
    candidat: premier ? `${premier.tagName.toLowerCase()} · ${propre(premier)}` : null,
  };
});

const mods = loadModules().filter((m) => !m.deferred);
const parCompte = {};

for (const m of mods) {
  (parCompte[m.credKey ?? 'public'] ??= []).push(m);
}

const nav = await chromium.launch();
const sans = [];
let vues = 0;

for (const [cle, liste] of Object.entries(parCompte)) {
  const contexte = await nav.newContext({ viewport: { width: 1280, height: 900 } });

  // `loginAs` prend le CONTEXTE et ouvre sa propre page : la session vit dans le contexte,
  // pas dans l'onglet.
  if (cle !== 'public') {
    await loginAs(contexte, BASE, cle);
  }

  const page = await contexte.newPage();

  for (const m of liste) {
    try {
      await page.goto(BASE + m.path, { waitUntil: 'domcontentloaded', timeout: 45000 });
      await page.waitForTimeout(350);

      const r = await releve(page);
      vues++;

      if (r.h1 === 0) {
        sans.push({ cle: m.key, chemin: m.path, candidat: r.candidat });
      }
    } catch (e) {
      sans.push({ cle: m.key, chemin: m.path, candidat: `ERREUR ${String(e).slice(0, 40)}` });
    }
  }

  await contexte.close();
}

await nav.close();

console.log(`  ${vues} pages ouvertes, ${sans.length} sans titre de niveau 1\n`);

for (const s of sans) {
  console.log(`  ${s.cle.padEnd(34)} ${s.chemin}`);
  console.log(`      candidat: ${s.candidat ?? '— aucun titre du tout —'}`);
}
