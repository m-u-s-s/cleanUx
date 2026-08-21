// LE PARCOURS DE COMMANDE, JOUÉ À LA MAIN — un métier à la fois.
//
// On entre par `/commander`, on choisit le mode, le métier, on répond aux
// questions, on pose une adresse et une date, et on va aussi loin que la
// plateforme le permet. À chaque étape on note ce que la page crache.
//
//   COMMANDE_METIER=Nettoyage node commande.mjs
//
import { chromium } from 'playwright';
import fs from 'fs';

const BASE = process.env.COMMANDE_BASE || 'http://127.0.0.1:8000';
const SECTEUR = process.env.COMMANDE_SECTEUR || 'Nettoyage';
const METIER = process.env.COMMANDE_METIER || 'Nettoyage à domicile';
const MODE = process.env.COMMANDE_MODE || 'Prendre rendez-vous';
const PAUSE = Number(process.env.COMMANDE_PAUSE || 1300);
const ETAPES_MAX = Number(process.env.COMMANDE_ETAPES || 12);
const SORTIE = process.env.COMMANDE_OUT || './out/commande.json';

const navigateur = await chromium.launch({
  headless: false,
  args: ['--window-size=1480,1000', `--window-position=${process.env.COMMANDE_X || 40},${process.env.COMMANDE_Y || 30}`],
});
const page = await (await navigateur.newContext({ viewport: { width: 1440, height: 900 }, locale: 'fr-BE' })).newPage();
page.on('dialog', (d) => d.dismiss().catch(() => {}));

let journal = { console: [], reseau: [], pageerror: [] };
const reset = () => { journal = { console: [], reseau: [], pageerror: [] }; };
page.on('console', (m) => { if (m.type() === 'error' && !/WebSocket|pusher/i.test(m.text())) journal.console.push(m.text().slice(0, 180)); });
page.on('pageerror', (e) => journal.pageerror.push(String(e).slice(0, 180)));
page.on('response', (r) => {
  if (r.status() >= 400 && r.url().startsWith(BASE) && !/images\/journey/.test(r.url())) {
    journal.reseau.push(`HTTP ${r.status()} ${r.url().replace(BASE, '').slice(0, 100)}`);
  }
});

async function etat() {
  return page.evaluate(() => {
    const t = document.body?.innerText || '';
    const s = [];
    if (/Whoops|QueryException|SQLSTATE|Undefined variable|Undefined array key/i.test(t)) s.push('exception_php');
    if (/Call to (undefined|a member function)/i.test(t)) s.push('appel_impossible');
    const d = document.documentElement;
    if (d.scrollWidth - d.clientWidth > 2) s.push(`debordement_${d.scrollWidth - d.clientWidth}px`);
    return {
      symptomes: s,
      titre: (document.querySelector('h1, h2')?.innerText || '').trim().slice(0, 70),
      prix: (t.match(/(\d[\d\s.,]*)\s*€/) || [])[0] || null,
      boutons: [...document.querySelectorAll('button, a[href], [role="button"]')]
        .filter((e) => { const r = e.getBoundingClientRect(); return r.width > 4 && r.height > 4; })
        .map((e) => (e.innerText || '').trim().replace(/\s+/g, ' ').slice(0, 44))
        .filter(Boolean).slice(0, 24),
    };
  }).catch(() => ({ symptomes: ['illisible'], titre: '', prix: null, boutons: [] }));
}

/** Remplit ce qui est vide et visible : le parcours n'avance pas sans réponses. */
async function repondreAuxQuestions() {
  return page.evaluate(() => {
    const faits = [];
    const visible = (e) => { const r = e.getBoundingClientRect(); return r.width > 4 && r.height > 4; };

    for (const s of document.querySelectorAll('select')) {
      if (!visible(s) || s.value) continue;
      const opt = [...s.options].find((o) => o.value && o.value !== '');
      if (opt) { s.value = opt.value; s.dispatchEvent(new Event('change', { bubbles: true })); faits.push('select:' + (opt.text || '').slice(0, 20)); }
    }
    for (const i of document.querySelectorAll('input[type="number"]')) {
      if (!visible(i) || i.value) continue;
      i.value = i.min && Number(i.min) > 0 ? i.min : '40';
      i.dispatchEvent(new Event('input', { bubbles: true }));
      faits.push('nombre:' + i.value);
    }
    for (const i of document.querySelectorAll('input[type="radio"]')) {
      if (!visible(i)) continue;
      const nom = i.name;
      if (nom && document.querySelector(`input[type="radio"][name="${nom}"]:checked`)) continue;
      i.click(); faits.push('choix:' + (i.value || '').slice(0, 20));
    }
    for (const i of document.querySelectorAll('textarea')) {
      if (!visible(i) || i.value) continue;
      i.value = 'Essai de parcours automatisé.';
      i.dispatchEvent(new Event('input', { bubbles: true }));
      faits.push('texte');
    }
    return faits;
  }).catch(() => []);
}

/**
 * Clique le premier libellé qui répond.
 *
 * L'EXACT D'ABORD : `has-text` attrape tout ce qui CONTIENT le mot, si bien qu'un clic
 * sur le service « Nettoyage à domicile » atterrissait sur le secteur « Nettoyage ».
 * On tente donc la correspondance exacte, puis seulement le contient.
 */
async function cliquer(libelles) {
  for (const l of libelles) {
    const exact = page.getByRole('button', { name: l, exact: true })
      .or(page.getByRole('link', { name: l, exact: true })).first();
    if (await exact.count().catch(() => 0)) {
      try { await exact.click({ timeout: 3500 }); await page.waitForTimeout(PAUSE); return l; } catch { /* on retombe sur le contient */ }
    }
    // Une carte de service affiche son nom PUIS sa description : on cible le DÉBUT
    // du libellé, l'égalité stricte ne peut pas correspondre.
    const debut = page.locator('button, a[href], [role="button"]')
      .filter({ hasText: new RegExp('^\s*' + l.replace(/[-/\^$*+?.()|[\]{}]/g, '\$&')) })
      .first();
    if (await debut.count().catch(() => 0)) {
      try { await debut.click({ timeout: 3500 }); await page.waitForTimeout(PAUSE); return l; } catch { /* on continue */ }
    }
    const proche = page.locator(`button:has-text("${l}"), a:has-text("${l}"), [role="button"]:has-text("${l}")`).last();
    if (await proche.count().catch(() => 0)) {
      try { await proche.click({ timeout: 3500 }); await page.waitForTimeout(PAUSE); return l; } catch { /* on essaie le suivant */ }
    }
  }
  return null;
}

/**
 * La bannière de cookies recouvre le bas de l'écran et AVALE les clics.
 * On décline les cookies optionnels — le choix le plus sobre — puis on avance.
 */
async function ecarterLaBanniere() {
  for (const l of ['Refuser optionnels', 'Refuser', 'Tout refuser']) {
    const b = page.getByRole('button', { name: l, exact: true }).first();
    if (await b.count().catch(() => 0)) {
      await b.click({ timeout: 2500 }).catch(() => {});
      await page.waitForTimeout(500);
      return l;
    }
  }
  return null;
}

const etapes = [];
reset();
await page.goto(`${BASE}/commander`, { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(PAUSE);
const banniere = await ecarterLaBanniere();
if (banniere) console.log(`  cookies   : ${banniere}`);

console.log(`\n═══ COMMANDE « ${METIER} » — mode « ${MODE} » ═══`);

// 1. le mode
let fait = await cliquer([MODE, 'Prendre rendez-vous', 'Tous les services']);
console.log(`  mode      : ${fait || '(aucun bouton de mode)'}`);

// 2. le secteur, PUIS le métier — le premier écran range par famille, pas par service.
fait = await cliquer([SECTEUR]);
console.log(`  secteur   : ${fait || 'INTROUVABLE — ' + SECTEUR}`);

fait = await cliquer([METIER]);
if (!fait) {
  await cliquer(['Tous les services', 'Parcourir le catalogue complet']);
  fait = await cliquer([METIER]);
}
console.log(`  métier    : ${fait || 'INTROUVABLE — ' + METIER}`);

// 3. on avance, en répondant à ce qui se présente
for (let n = 1; n <= ETAPES_MAX; n++) {
  const reponses = await repondreAuxQuestions();
  if (reponses.length) await page.waitForTimeout(700);

  const avant = await etat();
  const suite = await cliquer(['Continuer', 'Suivant', 'Valider', 'Étape suivante', 'Voir le prix', 'Poursuivre']);
  const apres = await etat();

  const pb = [...apres.symptomes, ...journal.pageerror, ...journal.console, ...journal.reseau];
  etapes.push({
    n, titre: avant.titre, reponses, bouton: suite, prix: apres.prix,
    symptomes: apres.symptomes, incidents: pb.slice(0, 5),
  });

  const marque = pb.length ? '✗' : '·';
  console.log(`  ${marque} étape ${String(n).padStart(2)} : ${(avant.titre || '(sans titre)').padEnd(46)} ${reponses.length ? reponses.length + ' réponse(s)' : ''} ${apres.prix ? '→ ' + apres.prix : ''}`);
  if (pb.length) pb.slice(0, 3).forEach((x) => console.log(`      ${x}`));

  reset();
  if (!suite) { console.log(`  ── plus de bouton pour avancer (étape ${n})`); break; }
}

const final = await etat();
console.log(`  arrivée   : ${page.url().replace(BASE, '')}`);
console.log(`  écran     : ${final.titre}`);
console.log(`  prix      : ${final.prix || '(aucun affiché)'}`);

fs.mkdirSync('./out', { recursive: true });
fs.writeFileSync(SORTIE, JSON.stringify({ secteur: SECTEUR, metier: METIER, mode: MODE, url: page.url(), final, etapes }, null, 2));
console.log(`  écrit     : ${SORTIE}`);
await page.waitForTimeout(2500);
await navigateur.close();
