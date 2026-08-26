// LES DEUX GRAPHIQUES SE DESSINENT-ILS VRAIMENT ?
//
// Aucun test PHP ne peut le dire : il verifie que le balisage porte `dessinerActivite`, pas
// qu'ApexCharts est charge ni qu'il rend un SVG. Le module n'est PAS dans le paquet global —
// c'est une entree Vite dediee. Sans la pile de scripts, la fonction n'existe pas et les
// deux cadres restent vides, sans erreur, sans rien qui le dise.

import { chromium } from 'playwright';
import { loginAs } from './check.mjs';

const BASE = process.env.VQA_BASE ?? 'http://127.0.0.1:8000';
const nav = await chromium.launch();
const ctx = await nav.newContext({ viewport: { width: 390, height: 844 } });
await loginAs(ctx, BASE, 'client');

const page = await ctx.newPage();
const erreurs = [];
page.on('pageerror', (e) => erreurs.push(e.message));

await page.goto(BASE + '/dashboard/client/fidelite', { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(3500);

const r = await page.evaluate(() => ({
  url: location.pathname,
  moteur: typeof window.dessinerActivite + ' / ' + typeof window.dessinerRepartition,
  apex: typeof window.ApexCharts,
  cadres: document.querySelectorAll('.brio-graphique').length,
  svg: document.querySelectorAll('.brio-graphique svg').length,
  barres: document.querySelectorAll('.brio-graphique .apexcharts-bar-series path').length,
  parts: document.querySelectorAll('.brio-graphique .apexcharts-pie-series path').length,
  codes: document.body.textContent.includes('earn_booking'),
}));

console.log(JSON.stringify(r, null, 2));
if (erreurs.length) console.log('erreurs JS : ' + JSON.stringify(erreurs.slice(0, 3)));

/*
 * LES DEUX ETATS SONT LEGITIMES, et chacun a sa preuve.
 *
 * Avec des points : deux cadres, deux dessins. Sans : AUCUN cadre, et surtout le moteur de
 * 565 Ko qui n'est PAS charge — c'est le but de la pile conditionnelle. Un script qui
 * n'accepterait que le premier etat crierait a l'echec sur un compte neuf, c'est-a-dire sur
 * le cas le plus frequent.
 */
const avecDonnees = r.cadres > 0;

const tenu = avecDonnees
  ? (r.cadres === 2 && r.svg >= 2 && r.barres > 0 && r.parts > 0 && r.codes === false && erreurs.length === 0)
  : (r.apex === 'undefined' && r.svg === 0 && erreurs.length === 0);

console.log(avecDonnees
  ? (tenu ? 'OK — les deux graphiques sont dessines.' : 'ECHEC — donnees presentes, dessin absent.')
  : (tenu ? "OK — sans point, le moteur de 565 Ko n'est pas charge." : 'ECHEC — le moteur est charge pour rien.'));

await nav.close();
process.exit(tenu ? 0 : 1);
