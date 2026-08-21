// Visualiseur en direct — parcourt les pages dans une fenêtre visible.
// Sert deux fois : l'utilisateur suit le travail, et on capte les erreurs
// console et les requêtes en échec qu'un balayage HTTP ne peut pas voir.
//
//   DIRECT_ROLE=client DIRECT_PAGES=/dashboard,/commander node direct.mjs
//
import { chromium } from 'playwright';
import fs from 'fs';

const BASE = process.env.DIRECT_BASE || 'http://127.0.0.1:8001';
const PW = process.env.BRIO_SEED_PASSWORD || '12345678';
const PAUSE = Number(process.env.DIRECT_PAUSE || 1200);
const LARGEUR = Number(process.env.DIRECT_WIDTH || 1440);
const HAUTEUR = Number(process.env.DIRECT_HEIGHT || 900);
const SORTIE = process.env.DIRECT_OUT || './out/direct.json';

const COMPTES = {
  public: null,
  admin: 'admin@brio.test',
  provider_company: 'qa-provider-company@brio.test',
  client_company: 'dominique.monnier@example.org',
  provider: 'bsanchez@example.org',
  client: 'lemoine.gabrielle@example.net',
};

const role = process.env.DIRECT_ROLE || 'admin';
const pages = (process.env.DIRECT_PAGES || '/dashboard').split(',').map((s) => s.trim()).filter(Boolean);

const navigateur = await chromium.launch({
  headless: false,
  args: [`--window-size=${LARGEUR},${HAUTEUR + 120}`, `--window-position=${process.env.DIRECT_X || 40},${process.env.DIRECT_Y || 40}`],
});
const contexte = await navigateur.newContext({
  viewport: { width: LARGEUR, height: HAUTEUR },
  locale: 'fr-BE',
});
const page = await contexte.newPage();

// Tout ce que la page crache, on le garde.
let journal = { console: [], reseau: [], pageerror: [] };
page.on('console', (m) => {
  if (m.type() === 'error' || m.type() === 'warning') {
    journal.console.push({ type: m.type(), texte: m.text().slice(0, 300) });
  }
});
page.on('pageerror', (e) => journal.pageerror.push(String(e).slice(0, 300)));
page.on('requestfailed', (r) =>
  journal.reseau.push({ url: r.url().slice(0, 200), erreur: r.failure()?.errorText || '?' }));
page.on('response', (r) => {
  if (r.status() >= 400) journal.reseau.push({ url: r.url().slice(0, 200), statut: r.status() });
});

async function connexion(email) {
  await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
  // Sélecteurs élargis : selon l'écran, le champ porte un id, un name ou un type.
  await page.fill('#email, input[name="email"], input[type="email"]', email);
  await page.fill('#password, input[name="password"], input[type="password"]', PW);
  // Le clic et l'attente de navigation ne sont PAS mis en course : sur les
  // écrans qui planifient leur propre navigation, `Promise.all` restait bloqué
  // « waiting for scheduled navigations to finish » jusqu'au délai maximal.
  await page.click('button[type="submit"]', { timeout: 10000 }).catch(() => {});
  await page.waitForLoadState('domcontentloaded').catch(() => {});
  await page.waitForTimeout(1500);
  return page.url();
}

const resultats = [];
if (COMPTES[role]) {
  const apres = await connexion(COMPTES[role]);
  console.log(`connecté ${role} → ${apres}`);
  if (apres.includes('/login')) {
    console.log('!! la connexion a échoué, on continue en visiteur');
  }
}
journal = { console: [], reseau: [], pageerror: [] };

for (const chemin of pages) {
  const t0 = Date.now();
  let statut = 0;
  try {
    const rep = await page.goto(BASE + chemin, { waitUntil: 'domcontentloaded', timeout: 30000 });
    statut = rep ? rep.status() : 0;
    await page.waitForTimeout(PAUSE);
  } catch (e) {
    journal.pageerror.push(`goto ${chemin}: ${String(e).slice(0, 200)}`);
  }
  // Débordement horizontal : le défaut d'affichage le plus fréquent.
  const debordement = await page.evaluate(() => {
    const d = document.documentElement;
    return { doc: d.scrollWidth - d.clientWidth, largeur: d.clientWidth };
  }).catch(() => ({ doc: -1, largeur: -1 }));

  resultats.push({
    role, chemin, statut,
    urlFinale: page.url().replace(BASE, ''),
    ms: Date.now() - t0,
    debordementPx: debordement.doc,
    console: journal.console.slice(0, 12),
    pageerror: journal.pageerror.slice(0, 6),
    reseau: journal.reseau.slice(0, 12),
  });
  const pb = journal.console.length + journal.pageerror.length + journal.reseau.length;
  console.log(
    `${String(statut).padEnd(4)} ${chemin.padEnd(46)} ${debordement.doc > 2 ? `DEBORD ${debordement.doc}px` : 'ok'} ${pb ? `· ${pb} signal(aux)` : ''}`
  );
  journal = { console: [], reseau: [], pageerror: [] };
}

fs.mkdirSync('./out', { recursive: true });
fs.writeFileSync(SORTIE, JSON.stringify(resultats, null, 2));
console.log(`\nécrit : ${SORTIE}`);
// Mode vitrine : la fenêtre reste ouverte et reboucle sur la liste, pour
// qu'on puisse suivre le travail en direct.
if (process.env.DIRECT_LOOP === '1') {
  console.log('mode vitrine : la fenêtre reste ouverte et reboucle');
  for (;;) {
    for (const chemin of pages) {
      try {
        await page.goto(BASE + chemin, { waitUntil: 'domcontentloaded', timeout: 30000 });
        await page.waitForTimeout(PAUSE);
      } catch { /* la vitrine ne s'arrête pas sur un incident */ }
    }
  }
}
if (process.env.DIRECT_KEEP === '1') await page.waitForTimeout(600000);
await navigateur.close();
