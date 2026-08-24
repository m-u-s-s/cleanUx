// tools/visual-qa/theme.mjs
//
// Le thème en vue mobile : pas d'éclair au chargement, un réglage atteignable, des boutons nommés.
// Trois défauts mesurés ici avaient tous échappé aux cinq critères de `check.mjs`.

import { chromium } from 'playwright';

const BASE = process.env.VQA_BASE ?? 'http://127.0.0.1:8000';
const MOBILE = { width: 390, height: 844 };

const PAGES = [
  '/', '/services', '/commander', '/pricing', '/blog', '/aide',
  '/login', '/register', '/legal/cookies', '/legal/mentions-legales',
];

/** Compte QA client. Le mot de passe suit le seeder. */
const COMPTE = process.env.VQA_CLIENT ?? 'lemoine.gabrielle@example.net';
const MOT_DE_PASSE = process.env.BRIO_SEED_PASSWORD ?? '12345678';

const ecarts = [];

/**
 * Le fond change-t-il entre la première peinture et l'état final ?
 * Un changement, c'est un éclair de la mauvaise couleur sous les yeux du visiteur.
 */
async function eclairDeTheme(ctx) {
  console.log('\n— Éclair de thème (préférence système : sombre)');

  for (const chemin of PAGES) {
    const page = await ctx.newPage();

    await page.addInitScript(() => {
      window.__r = {};
      document.addEventListener('DOMContentLoaded', () => {
        window.__r.tot = getComputedStyle(document.body).backgroundColor;
      });
    });

    await page.goto(BASE + chemin, { waitUntil: 'networkidle' });
    const r = await page.evaluate(() => ({
      tot: window.__r.tot,
      tard: getComputedStyle(document.body).backgroundColor,
    }));

    const bouge = r.tot !== r.tard;
    if (bouge) ecarts.push(`${chemin} : le fond passe de ${r.tot} à ${r.tard} après la peinture`);
    console.log(`  ${bouge ? 'ÉCLAIR' : 'stable'}  ${chemin.padEnd(26)} ${r.tot} -> ${r.tard}`);
    await page.close();
  }
}

/** Un bouton sans nom accessible est muet pour un lecteur d'écran. */
async function boutonsMuets(ctx) {
  console.log('\n— Boutons sans nom accessible');
  let total = 0;

  for (const chemin of PAGES) {
    const page = await ctx.newPage();
    await page.goto(BASE + chemin, { waitUntil: 'load' });
    await page.waitForTimeout(300);

    const muets = await page.evaluate(() => {
      const sortie = [];
      for (const b of document.querySelectorAll('button, [role="button"]')) {
        const nom = (b.getAttribute('aria-label') || b.getAttribute('title') || b.innerText || '').trim();
        if (nom || b.getAttribute('aria-labelledby')) continue;
        const r = b.getBoundingClientRect();
        if (r.width === 0 && r.height === 0) continue;
        sortie.push((b.className || '').toString().split(' ').slice(0, 3).join(' ') || b.tagName);
      }
      return sortie;
    });

    if (muets.length) {
      total += muets.length;
      ecarts.push(`${chemin} : ${muets.length} bouton(s) sans nom — ${muets.slice(0, 3).join(' | ')}`);
      console.log(`  ${String(muets.length).padStart(2)}  ${chemin}`);
    }
    await page.close();
  }

  if (!total) console.log('  aucun');
}

/** Le thème est-il réglable sous 640 px ? Il ne vivait que dans un conteneur `hidden sm:flex`. */
async function reglageEnVueMobile(nav) {
  console.log('\n— Réglage du thème en vue mobile');
  const ctx = await nav.newContext({ viewport: MOBILE, colorScheme: 'light' });

  const co = await ctx.newPage();
  await co.goto(BASE + '/login', { waitUntil: 'domcontentloaded' });
  await co.fill('input[name="email"]', COMPTE);
  await co.fill('input[name="password"]', MOT_DE_PASSE);
  await co.click('button[type="submit"]');

  try {
    await co.waitForURL((u) => !u.pathname.endsWith('/login'), { timeout: 20000 });
  } catch {
    console.log(`  IGNORÉ — connexion impossible avec ${COMPTE} (compte de seeder renouvelé ?)`);
    await ctx.close();
    return;
  }

  const destination = co.url();
  await co.close();

  const page = await ctx.newPage();
  await page.goto(destination, { waitUntil: 'load' });
  await page.waitForTimeout(500);

  // Le chemin normal de l'utilisateur : ouvrir le menu, puis régler.
  const burger = page.locator('button:has(path[d="M4 6h16M4 12h16M4 18h16"])').first();
  if (await burger.count()) { await burger.click(); await page.waitForTimeout(400); }

  const btn = page.locator('button[aria-label="Changer le thème"]:visible').first();

  if (await btn.count() === 0) {
    ecarts.push('vue mobile : le thème n’est réglable nulle part sous 640 px');
    console.log('  INTROUVABLE');
    await ctx.close();
    return;
  }

  const b = await btn.boundingBox();
  const avant = await page.evaluate(() => document.documentElement.className);
  await btn.click();
  await page.waitForTimeout(300);
  const apres = await page.evaluate(() => document.documentElement.className);

  if (b.height < 44 || b.width < 44) ecarts.push(`vue mobile : cible ${Math.round(b.width)}×${Math.round(b.height)}, sous les 44 px exigés`);
  if (avant === apres) ecarts.push('vue mobile : le bouton de thème est sans effet');

  // Le choix doit remonter au serveur, sinon il se perd d'un appareil à l'autre.
  await page.reload({ waitUntil: 'load' });
  const persiste = await page.evaluate(() => document.documentElement.className);
  if (persiste !== apres) ecarts.push('le choix de thème ne survit pas au rechargement');

  console.log(`  cible ${Math.round(b.width)}×${Math.round(b.height)}  "${avant}" -> "${apres}"  rechargement: "${persiste}"`);
  await ctx.close();
}

const nav = await chromium.launch();
const sombre = await nav.newContext({ viewport: MOBILE, colorScheme: 'dark' });
await eclairDeTheme(sombre);
await boutonsMuets(sombre);
await sombre.close();
await reglageEnVueMobile(nav);
await nav.close();

console.log(ecarts.length ? `\n${ecarts.length} écart(s) :\n  ` + ecarts.join('\n  ') : '\nAucun écart.');
process.exitCode = ecarts.length ? 1 : 0;
