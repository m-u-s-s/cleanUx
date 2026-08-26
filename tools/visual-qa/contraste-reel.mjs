// LE CONTRASTE, MESURE SUR LES PIXELS RENDUS.
//
// La mesure CSS remonte les ancetres pour deviner le fond : elle se trompe des qu'une couche
// lui echappe, et elle ABANDONNE sur un degrade. Ici on photographie la page ENTIERE, puis on
// lit les pixels de chaque zone de texte par coordonnees.
//
// PAS DE CAPTURE PAR DECOUPE : une premiere version photographiait chaque element apres un
// `scrollIntoView`, et le rectangle mesure AVANT le defilement ne designait plus la meme
// zone. Elle lisait donc du fond vide et rendait « 1,07:1 » sur du texte parfaitement lisible.

import { chromium } from 'playwright';
import { PNG } from 'pngjs';

const BASE = process.env.VQA_BASE ?? 'http://127.0.0.1:8000';
const PAGES = ['/', '/services', '/commander', '/pricing', '/blog', '/aide', '/login', '/register', '/legal/cookies', '/legal/mentions-legales'];

const lum = ([r, g, b]) => {
  const f = (c) => { c /= 255; return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4); };
  return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b);
};
const ratio = (a, b) => { const [x, y] = [lum(a), lum(b)].sort((p, q) => q - p); return (x + 0.05) / (y + 0.05); };

const sombre = process.argv.includes('--clair') ? 'light' : 'dark';
const nav = await chromium.launch();
const ctx = await nav.newContext({ viewport: { width: 390, height: 844 }, colorScheme: sombre });

let total = 0;
console.log('— Contraste REEL (pixels), mode ' + (sombre === 'dark' ? 'sombre' : 'clair'));

for (const chemin of PAGES) {
  const page = await ctx.newPage();
  try {
    await page.goto(BASE + chemin, { waitUntil: 'load', timeout: 25000 });
  } catch { await page.close(); continue; }
  await page.waitForTimeout(2200);

  let zones = [];
  try {
  zones = await page.evaluate(() => {
    /*
     * « VISIBLE » NE SUFFIT PAS : IL FAUT ETRE DESSUS.
     *
     * Les liens de l'en-tete mobile existent dans un tiroir hors-champ ou sous un voile.
     * `visibility`, `display` et `opacity` les declarent visibles ; les pixels a leurs
     * coordonnees appartiennent pourtant a ce qui les recouvre. La mesure retenait alors
     * deux nuances de fond — « Brio » et « Reserver » a 1,05:1 sur DIX pages, toutes les
     * memes mots. `elementFromPoint` tranche : si ce n'est pas nous au centre, on passe.
     */
    const visible = (e) => {
      const s = getComputedStyle(e);
      if (s.visibility === 'hidden' || s.display === 'none' || parseFloat(s.opacity || '1') <= 0.05) return false;

      const r = e.getBoundingClientRect();
      if (r.width < 1 || r.height < 1) return false;

      const dessus = document.elementFromPoint(r.x + r.width / 2, r.y + r.height / 2);

      return dessus !== null && (dessus === e || e.contains(dessus) || dessus.contains(e));
    };
    return [...document.querySelectorAll('p, h1, h2, h3, h4, span, a, li, td, th, label, button')]
      .filter((e) => e.children.length === 0 && (e.textContent || '').trim().length > 3 && visible(e))
      .map((e) => { const r = e.getBoundingClientRect(); return { x: Math.round(r.x), y: Math.round(r.y), w: Math.round(r.width), h: Math.round(r.height), t: (e.textContent || '').trim().slice(0, 34), gros: parseFloat(getComputedStyle(e).fontSize) >= 24 }; })
      .filter((z) => z.w > 8 && z.h > 6 && z.y >= 0);
  });
  } catch { await page.close(); continue; }
  if (!zones.length) { await page.close(); continue; }

  const png = PNG.sync.read(await page.screenshot({ fullPage: false }));
  const lire = (x, y) => { const i = (png.width * y + x) << 2; return [png.data[i], png.data[i + 1], png.data[i + 2]]; };

  const fautes = [];

  for (const z of zones) {
    if (z.y + z.h > png.height || z.x + z.w > png.width) continue;

    const compte = new Map();
    for (let y = z.y; y < z.y + z.h; y++) {
      for (let x = z.x; x < z.x + z.w; x++) {
        const k = lire(x, y).join(',');
        compte.set(k, (compte.get(k) || 0) + 1);
      }
    }
    if (compte.size < 2) continue;

    const tri = [...compte.entries()].sort((a, b) => b[1] - a[1]);
    const fond = tri[0][0].split(',').map(Number);

    /*
     * LE TEXTE EST L'EXTREME, PAS LA MAJORITE.
     *
     * Un seuil a 3 % des pixels ecartait les glyphes fins : le mot « Brio » en Allura sur
     * une boite large n'atteint pas 3 %, et la mesure retenait alors une seconde nuance de
     * FOND — d'ou « 1,01:1 » sur dix pages, toutes sur le meme mot. La signature d'un
     * artefact, pas d'un defaut.
     *
     * L'anticrenelage produit des teintes INTERMEDIAIRES entre le texte et le fond : la
     * couleur la plus eloignee du fond est donc le texte lui-meme. Un plancher de quelques
     * pixels suffit a ecarter le bruit de compression.
     */
    const seuil = Math.max(4, (z.w * z.h) * 0.004);
    let texte = null, pire = 0;
    for (const [k, n] of tri.slice(1, 400)) {
      if (n < seuil) continue;
      const c = k.split(',').map(Number);
      const d = ratio(c, fond);
      if (d > pire) { pire = d; texte = c; }
    }
    if (!texte) continue;

    const exige = z.gros ? 3 : 4.5;
    if (pire < exige) fautes.push({ t: z.t, r: pire, exige, fond, texte });
  }

  if (fautes.length) {
    total += fautes.length;
    console.log('  ' + String(fautes.length).padStart(3) + '  ' + chemin.padEnd(26) + fautes[0].r.toFixed(2) + ':1 (exige ' + fautes[0].exige + ') « ' + fautes[0].t + ' »');
  }
  await page.close();
}

console.log('  ' + total + ' au total — mesure sur pixels rendus, degrades compris');
await nav.close();
