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

/**
 * Le texte se lit-il sur son fond ? WCAG AA demande 4,5:1, ou 3:1 au-delà de 24 px
 * (ou 18,66 px en gras). Un thème peut se poser correctement et rester illisible.
 */
async function contraste(ctx, mode) {
  console.log(`
— Contraste du texte (mode ${mode})`);
  let total = 0;

  for (const chemin of PAGES) {
    const page = await ctx.newPage();
    await page.goto(BASE + chemin, { waitUntil: 'load' });
    await page.waitForTimeout(300);

    const faibles = await page.evaluate(() => {
      /*
       * LA NOTATION MODERNE N'EST PAS DU RGB.
       *
       * `color-mix()` rend `color(srgb 0.917 0.941 0.984 / 0.88)` : des composantes de 0 a 1,
       * la ou `rgb()` en donne de 0 a 255. Le parseur les prenait telles quelles puis les
       * divisait encore par 255 — un texte blanc casse etait compte comme NOIR, et
       * dix-neuf cellules parfaitement lisibles remontaient a 1,22:1.
       */
      const composantes = (c) => {
        const m = c.match(/[0-9.]+/g);

        if (!m) return null;

        const n = m.slice(0, 4).map(Number);
        const estUnitaire = /^color\(/i.test(c.trim());
        const rvb = estUnitaire ? n.slice(0, 3).map((x) => x * 255) : n.slice(0, 3);

        return [rvb[0], rvb[1], rvb[2], n[3] === undefined ? 1 : n[3]];
      };

      // Luminance relative, définition WCAG.
      const lum = ([r, v, b]) => {
        const f = (x) => {
          const s = x / 255;

          return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * f(r) + 0.7152 * f(v) + 0.0722 * f(b);
      };

      /*
       * Le fond effectif : on remonte les ancetres jusqu'au premier opaque.
       *
       * On REND `null` des qu'un degrade ou une image est rencontre en chemin. Sans cela, un
       * bouton ambre a fond `linear-gradient` etait mesure contre le fond nuit de la page, et
       * son texte quasi-noir ressortait a 1,06:1 — un defaut qui n'existe pas.
       */
      const fondDe = (el) => {
        /*
         * Les couches translucides se COMPOSENT, elles ne s'ignorent pas. `.brio-glass` vaut
         * rgba(255,255,255,.7) : une surface claire. La sauter faisait remonter jusqu'au fond
         * nuit, et le texte y paraissait lisible alors qu'il ne l'etait pas.
         */
        const couches = [];

        for (let n = el; n && n !== document.documentElement; n = n.parentElement) {
          const s = getComputedStyle(n);
          if (s.backgroundImage && s.backgroundImage !== 'none') return null;

          const c = composantes(s.backgroundColor);
          if (!c) continue;

          const a = c[3] === undefined ? 1 : c[3];
          if (a <= 0.01) continue;

          couches.push([c[0], c[1], c[2], a]);
          if (a >= 0.99) break;
        }

        const s = getComputedStyle(document.body);
        if (s.backgroundImage && s.backgroundImage !== 'none') return null;

        const socle = composantes(s.backgroundColor) ?? [255, 255, 255];
        let fond = [socle[0], socle[1], socle[2]];

        // Du plus profond vers le plus proche : chaque couche s'applique sur le resultat.
        for (const [r, v, b, a] of couches.reverse()) {
          fond = [r * a + fond[0] * (1 - a), v * a + fond[1] * (1 - a), b * a + fond[2] * (1 - a)];
        }

        return fond;
      };

      const sortie = [];
      const vus = new Set();

      for (const el of document.querySelectorAll('p, span, a, h1, h2, h3, h4, li, td, th, label, button')) {
        const texte = (el.textContent ?? '').trim();
        if (!texte || el.children.length > 0) continue;

        const r = el.getBoundingClientRect();

        /*
         * Un element trop petit ou pousse hors de l'ecran n'est pas lu. Le lien « Aller au
         * contenu » vit a `left: -9999px` en 1x1 px, et le selecteur de langue du bureau est
         * a 0x0 sous 640 px : les compter en faisait des defauts qui n'existent pas.
         */
        if (r.width < 4 || r.height < 4) continue;
        if (r.right < 0 || r.bottom < 0 || r.left > window.innerWidth) continue;

        /*
         * Un emoji porte SES PROPRES couleurs : la propriete `color` ne s'y applique pas,
         * sauf si la police de secours le rend en monochrome. Le mesurer contre son fond
         * annonce des defauts qui n'existent pas — `⚡` sur un bouton clair a « 1,23:1 ».
         */
        if (/\p{Extended_Pictographic}/u.test(texte)) continue;

        const s = getComputedStyle(el);
        if (s.visibility === 'hidden' || Number(s.opacity) < 0.5) continue;

        const avant = composantes(s.color);
        if (!avant || (avant[3] !== undefined && avant[3] < 0.5)) continue;

        const fond = fondDe(el);
        if (fond === null) continue;

        const clair = Math.max(lum(avant), lum(fond)) + 0.05;
        const sombre = Math.min(lum(avant), lum(fond)) + 0.05;
        const rapport = clair / sombre;

        const px = parseFloat(s.fontSize);
        const gras = Number(s.fontWeight) >= 700;
        const exige = px >= 24 || (gras && px >= 18.66) ? 3 : 4.5;

        if (rapport < exige) {
          const cle = `${texte.slice(0, 24)}|${Math.round(rapport * 10)}`;
          if (vus.has(cle)) continue;
          vus.add(cle);
          sortie.push(`${rapport.toFixed(2)}:1 (exigé ${exige}) « ${texte.slice(0, 34)} »`);
        }
      }

      return sortie;
    });

    if (faibles.length) {
      total += faibles.length;
      console.log(`  ${String(faibles.length).padStart(2)}  ${chemin.padEnd(26)} ${faibles[0]}`);
    }
    await page.close();
  }

  if (!total) console.log('  aucun');
  else console.log(`  ${total} au total — INDICATIF : cette mesure ne compose pas les degrades`);
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
  const ctx = await nav.newContext({ serviceWorkers: 'block', viewport: MOBILE, colorScheme: 'light' });

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
const sombre = await nav.newContext({ serviceWorkers: 'block', viewport: MOBILE, colorScheme: 'dark' });
await eclairDeTheme(sombre);
await boutonsMuets(sombre);
await contraste(sombre, 'sombre');
await sombre.close();

const clair = await nav.newContext({ serviceWorkers: 'block', viewport: MOBILE, colorScheme: 'light' });
await contraste(clair, 'clair');
await clair.close();
await reglageEnVueMobile(nav);
await nav.close();

console.log(ecarts.length ? `\n${ecarts.length} écart(s) :\n  ` + ecarts.join('\n  ') : '\nAucun écart.');
process.exitCode = ecarts.length ? 1 : 0;
