// PARCOURS À LA MAIN — on clique, on remplit, on regarde ce qui casse.
//
// Le balayage HTTP disait « 200 » ; il ne disait rien des boutons. Ce harnais
// ouvre une vraie fenêtre, inventorie ce qui est cliquable sur chaque page, et
// essaie chaque élément un par un en notant tout ce que la page crache :
// exception PHP, erreur JavaScript, requête en échec, page blanche.
//
//   PARCOURS_ROLE=client PARCOURS_PAGES=/register node parcours.mjs
//
import { chromium } from 'playwright';
import fs from 'fs';

const BASE = process.env.PARCOURS_BASE || 'http://127.0.0.1:8000';
const PW = process.env.BRIO_SEED_PASSWORD || '12345678';
const PAUSE = Number(process.env.PARCOURS_PAUSE || 900);
const SORTIE = process.env.PARCOURS_OUT || './out/parcours.json';
// Combien d'éléments essayer par page. 0 = on visite sans cliquer : un premier passage
// large repère les pages malades, un second va les cliquer en profondeur.
const CLICS = process.env.PARCOURS_CLICS === undefined ? 999 : Number(process.env.PARCOURS_CLICS);

const COMPTES = {
  public: null,
  admin: 'admin@brio.test',
  provider_company: 'qa-provider-company@brio.test',
  client_company: 'dominique.monnier@example.org',
  provider: 'bsanchez@example.org',
  client: 'lemoine.gabrielle@example.net',
};

const role = process.env.PARCOURS_ROLE || 'public';
const pages = (process.env.PARCOURS_PAGES || '/register').split(',').map((s) => s.trim()).filter(Boolean);

// Ce qu'on ne clique JAMAIS : ça déconnecte, ça supprime, ça paie.
const INTERDIT = /(déconnex|deconnex|logout|supprim|delete|effacer|retirer|annuler ma|payer|confirmer la commande|valider le paiement)/i;

const navigateur = await chromium.launch({
  headless: false,
  args: ['--window-size=1500,1000', `--window-position=${process.env.PARCOURS_X || 30},${process.env.PARCOURS_Y || 30}`],
});
const contexte = await navigateur.newContext({ viewport: { width: 1460, height: 900 }, locale: 'fr-BE' });
const page = await contexte.newPage();

// Aucune boîte de dialogue ne doit bloquer la fenêtre.
page.on('dialog', (d) => d.dismiss().catch(() => {}));

let journal = { console: [], reseau: [], pageerror: [] };
const reset = () => { journal = { console: [], reseau: [], pageerror: [] }; };

page.on('console', (m) => {
  if (m.type() !== 'error') return;
  /*
     Le canal temps réel n'est pas démarré en développement : chaque page tente de s'y
     connecter et échoue. Ce n'est pas un défaut de la plateforme, et le compter noyait les
     vrais sous des dizaines de lignes identiques.
  */
  if (/WebSocket|pusher|brio-local-key/i.test(m.text())) return;
  journal.console.push(m.text().slice(0, 200));
});
page.on('pageerror', (e) => journal.pageerror.push(String(e).slice(0, 200)));
page.on('requestfailed', (r) => {
  const u = r.url();
  /*
     `presence/touch` est un battement de présence : quitter la page l'annule, et
     l'annulation n'est pas une panne. Le compter noyait les vrais défauts sous
     soixante-dix lignes identiques.
  */
  if (/fonts\.|analytics|sentry|presence\/touch/.test(u)) return;
  journal.reseau.push(`${r.failure()?.errorText || 'échec'} ${u.slice(0, 120)}`);
});
page.on('response', (r) => {
  if (r.status() >= 400 && r.url().startsWith(BASE)) journal.reseau.push(`HTTP ${r.status()} ${r.url().replace(BASE, '').slice(0, 110)}`);
});

/**
 * La bannière de cookies recouvre le bas de l'écran et AVALE les clics — y compris
 * celui de « Se connecter ». On décline les optionnels avant toute chose.
 */
async function ecarterLaBanniere() {
  for (const l of ['Refuser optionnels', 'Refuser', 'Tout refuser']) {
    const b = page.getByRole('button', { name: l, exact: true }).first();
    if (await b.count().catch(() => 0)) {
      await b.click({ timeout: 2500 }).catch(() => {});
      await page.waitForTimeout(400);
      return l;
    }
  }
  return null;
}

async function connexion(email) {
  await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(500);
  await ecarterLaBanniere();
  await page.fill('#email, input[name="email"], input[type="email"]', email);
  await page.fill('#password, input[name="password"], input[type="password"]', PW);
  await page.click('button[type="submit"]', { timeout: 10000 }).catch(() => {});
  await page.waitForLoadState('domcontentloaded').catch(() => {});
  await page.waitForTimeout(1500);
}

/** Le contenu de la page trahit-il une panne, même sous un code 200 ? */
async function symptomes() {
  return page.evaluate(() => {
    const t = document.body?.innerText || '';
    const s = [];
    if (/Whoops|QueryException|SQLSTATE|Undefined variable|Undefined array key/i.test(t)) s.push('exception_php');
    if (/Call to (undefined|a member function)/i.test(t)) s.push('appel_impossible');
    if (/419|Page Expired|CSRF/i.test(document.title || '')) s.push('jeton_expire');
    if (t.trim().length < 40) s.push('page_vide');
    const d = document.documentElement;
    if (d.scrollWidth - d.clientWidth > 2) s.push(`debordement_${d.scrollWidth - d.clientWidth}px`);
    return s;
  }).catch(() => ['illisible']);
}

/** Ce qui est cliquable et visible, avec un libellé lisible. */
async function inventaire() {
  return page.evaluate(() => {
    const vus = [];
    const el = [...document.querySelectorAll('a[href], button, [role="button"], input[type="submit"]')];
    for (const e of el) {
      const r = e.getBoundingClientRect();
      if (r.width < 4 || r.height < 4) continue;
      const style = getComputedStyle(e);
      if (style.visibility === 'hidden' || style.display === 'none') continue;
      const texte = (e.innerText || e.value || e.getAttribute('aria-label') || '').trim().replace(/\s+/g, ' ').slice(0, 60);
      if (!texte) continue;
      vus.push({
        texte,
        href: e.getAttribute('href') || null,
        type: e.tagName.toLowerCase(),
      });
    }
    // Dédoublonner sur le libellé : dix fois « Voir » ne valent pas dix essais.
    const seen = new Set();
    return vus.filter((v) => { const k = v.texte + '|' + (v.href || ''); if (seen.has(k)) return false; seen.add(k); return true; });
  }).catch(() => []);
}

const resultats = [];

if (COMPTES[role]) {
  await connexion(COMPTES[role]);
  const arrivee = page.url().replace(BASE, '');
  console.log(`connecté ${role} → ${arrivee}`);

  /*
     SANS CE CONTRÔLE, LE PARCOURS MESURE LA PAGE DE CONNEXION.
     Une connexion avalée renvoie sur /login, et chaque page « visitée » ensuite est en
     réalité le formulaire de connexion : vingt-cinq liens identiques, zéro problème, et
     un rapport qui ne prouve rien.
  */
  if (/\/login/.test(arrivee)) {
    console.log('!! CONNEXION ÉCHOUÉE — le parcours mesurerait la page de connexion. On arrête.');
    await navigateur.close();
    process.exit(1);
  }
}

for (const chemin of pages) {
  reset();
  let statut = 0;
  try {
    const rep = await page.goto(BASE + chemin, { waitUntil: 'domcontentloaded', timeout: 30000 });
    statut = rep ? rep.status() : 0;
  } catch (e) {
    journal.pageerror.push(`goto: ${String(e).slice(0, 150)}`);
  }
  await ecarterLaBanniere();
  await page.waitForTimeout(PAUSE);

  const symp = await symptomes();
  const elements = await inventaire();
  const urlDepart = page.url();

  console.log(`\n━━ ${chemin}  (HTTP ${statut})  ${symp.length ? '⚠ ' + symp.join(' ') : ''}`);
  console.log(`   ${elements.length} élément(s) cliquable(s)`);

  const essais = [];
  for (const el of elements.slice(0, CLICS)) {
    if (INTERDIT.test(el.texte) || (el.href && INTERDIT.test(el.href))) {
      essais.push({ ...el, verdict: 'écarté (action irréversible)' });
      continue;
    }
    reset();
    let avant = page.url();
    if (avant !== urlDepart) {
      await page.goto(urlDepart, { waitUntil: 'domcontentloaded' }).catch(() => {});
      await page.waitForTimeout(300);
    }
    try {
      const cible = page.locator(`a:has-text("${el.texte}"), button:has-text("${el.texte}")`).first();
      await cible.click({ timeout: 4000 });
      await page.waitForLoadState('domcontentloaded').catch(() => {});
      await page.waitForTimeout(PAUSE);
    } catch {
      essais.push({ ...el, verdict: 'inatteignable au clic' });
      continue;
    }
    const sympApres = await symptomes();
    const arrivee = page.url().replace(BASE, '');
    const pb = [...sympApres, ...journal.pageerror, ...journal.console, ...journal.reseau];
    essais.push({ ...el, arrivee, verdict: pb.length ? 'PROBLÈME' : 'ok', details: pb.slice(0, 5) });
    if (pb.length) console.log(`   ✗ « ${el.texte} » → ${arrivee}\n     ${pb.slice(0, 3).join('\n     ')}`);
  }

  const casses = essais.filter((e) => e.verdict === 'PROBLÈME').length;
  console.log(`   → ${casses} problème(s) sur ${essais.length} essai(s)`);
  resultats.push({ chemin, statut, symptomes: symp, essais });
}

fs.mkdirSync('./out', { recursive: true });
fs.writeFileSync(SORTIE, JSON.stringify(resultats, null, 2));
console.log(`\nécrit : ${SORTIE}`);
if (process.env.PARCOURS_KEEP === '1') await page.waitForTimeout(900000);
await navigateur.close();
