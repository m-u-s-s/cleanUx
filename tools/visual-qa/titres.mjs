import { chromium } from 'playwright';
import { loginAs } from './check.mjs';
import { loadModules } from './modules.mjs';

const BASE = process.env.VQA_BASE ?? 'http://127.0.0.1:8000';

/*
 * UN TITRE ILLISIBLE SUR SON PROPRE FOND.
 *
 * `base.css` pose `h1..h4 { color }` DIRECTEMENT sur le titre : cette declaration bat le
 * `text-white` herite d'un conteneur sombre. Un heros a degrade nuit se retrouve avec une
 * encre sombre dessus.
 *
 * PIEGE DE MESURE, paye ici : le fond d'un degrade a `backgroundColor: transparent`. Remonter
 * les ancetres trouve alors le fond CLAIR de la page et rend un contraste faussement
 * rassurant. On lit donc les ARRETS du degrade, et on retient le pire.
 */
const MESURE = () => {
  const lum = ([r, g, b]) => {
    const c = [r, g, b].map((v) => { const s = v / 255; return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4; });
    return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
  };
  const rgb = (s) => (s.match(/[\d.]+/g) ?? []).slice(0, 3).map(Number);
  const avecAlpha = (s) => { const v = (s.match(/[\d.]+/g) ?? []).map(Number); return [v[0] ?? 0, v[1] ?? 0, v[2] ?? 0, v[3] === undefined ? 1 : v[3]]; };
  const ratio = (a, b) => { const [x, y] = [a, b].sort((p, q) => q - p); return Math.round(((x + 0.05) / (y + 0.05)) * 100) / 100; };

  /* Les couches TRANSLUCIDES se composent, du fond vers l'avant : du blanc a 10 % pose sur
     du bleu nuit n'est pas du blanc. Sans cette composition, un titre blanc parfaitement
     lisible ressort a 1:1. */
  const composer = (dessus, dessous) => dessus.map((v, i) => Math.round(v * dessus[3] + dessous[i] * (1 - dessus[3])));

  const fondsDe = (el) => {
    const couches = [];
    let noeud = el;

    for (let i = 0; i < 12 && noeud; i++) {
      const s = getComputedStyle(noeud);
      const arrets = s.backgroundImage && s.backgroundImage !== 'none'
        ? (s.backgroundImage.match(/rgba?\([^)]+\)/g) ?? [])
        : [];

      if (arrets.length) { couches.push(arrets.map(avecAlpha)); break; }

      const fond = s.backgroundColor;
      if (!/rgba\(0, 0, 0, 0\)|transparent/.test(fond)) {
        const c = avecAlpha(fond);
        couches.push([c]);
        if (c[3] >= 0.999) break;
      }

      noeud = noeud.parentElement;
    }

    if (!couches.length) return [];

    // De la couche la plus profonde vers la plus proche du texte.
    let resultat = couches[couches.length - 1].map((c) => c.slice(0, 3));
    for (let i = couches.length - 2; i >= 0; i--) {
      const dessus = couches[i];
      const suivant = [];
      for (const d of dessus) for (const base of resultat) suivant.push(composer(d, base));
      resultat = suivant;
    }

    return resultat;
  };

  const resultats = [];

  for (const titre of document.querySelectorAll('h1, h2, h3, h4')) {
    const r = titre.getBoundingClientRect();
    const s = getComputedStyle(titre);
    if (r.height < 8 || r.width < 20 || s.visibility === 'hidden' || s.display === 'none') continue;
    if (!titre.textContent.trim()) continue;

    const fonds = fondsDe(titre);
    if (!fonds.length) continue;

    const texte = lum(rgb(s.color));
    const pire = Math.min(...fonds.map((f) => ratio(texte, lum(f))));

    // Un titre est du GRAND texte : le seuil WCAG y est de 3:1.
    if (pire < 3) {
      resultats.push({
        texte: titre.textContent.trim().replace(/\s+/g, ' ').slice(0, 40),
        couleur: s.color,
        fonds: fonds.slice(0, 3).map((f) => `rgb(${f.join(',')})`),
        contraste: pire,
        // Un ancetre declare-t-il DEJA une couleur de texte claire ? C'est le signal qu'une
        // seule regle peut suivre, plutot qu'une liste de fonds sombres a tenir a la main.
        ancetreClair: (() => {
          let n = titre.parentElement;
          for (let i = 0; i < 8 && n; i++) {
            const cl = (n.className || '').toString();
            if (/text-(white|slate-50|slate-100|slate-200)/.test(cl)) return cl.match(/text-(white|slate-50|slate-100|slate-200)/)[0];
            n = n.parentElement;
          }
          return null;
        })(),
      });
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

for (const [cred, pages] of Object.entries(parCred)) {
  const context = await navigateur.newContext({ viewport: { width: 1280, height: 900 }, serviceWorkers: 'block' });
  if (cred !== 'public') await loginAs(context, BASE, cred).catch(() => {});
  const page = await context.newPage();

  for (const m of pages) {
    try {
      const r = await page.goto(`${BASE}${m.path}`, { waitUntil: 'domcontentloaded', timeout: 25000 });
      if (!r || r.status() >= 400) { injoignables.push(`${m.path} (HTTP ${r ? r.status() : 0})`); continue; }
      await page.waitForTimeout(450);
      const mauvais = await page.evaluate(MESURE);
      vues++;
      if (mauvais.length) touchees.push({ chemin: m.path, cred, mauvais });
    } catch (e) {
      injoignables.push(`${m.path} (${String(e.message).slice(0, 50)})`);
    }
  }

  await context.close();
}

await navigateur.close();

console.log(`\n=== TITRES LISIBLES — ${vues} pages mesurees ===\n`);
if (injoignables.length) {
  console.log(`Injoignables (${injoignables.length}) :`);
  for (const p of injoignables) console.log(`  ${p}`);
  console.log();
}
console.log(`Pages avec un titre sous 3:1 : ${touchees.length}\n`);
for (const t of touchees) {
  console.log(`${t.cred.padEnd(17)} ${t.chemin}`);
  for (const m of t.mauvais) console.log(`      ${String(m.contraste).padStart(5)}:1  « ${m.texte} »  ${m.couleur} sur ${m.fonds.join(' ')}  [ancetre clair : ${m.ancetreClair ?? 'AUCUN'}]`);
}

process.exit(touchees.length ? 1 : 0);
