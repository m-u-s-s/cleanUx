// Diagnostic du verre sur les pages OUTIL : quels elements restent clairs sur la nuit,
// et quelles CLASSES les portent. Le contraste seul ne suffit pas — il faut le coupable.
import { chromium } from 'playwright';
import { loginAs } from './check.mjs';

const BASE = process.env.VQA_BASE ?? 'http://127.0.0.1:8000';

const CIBLES = [
  { cred: 'client', chemin: '/dashboard/client' },
  { cred: 'provider', chemin: '/dashboard/employe' },
  { cred: 'admin', chemin: '/admin/audit' },
];

const luminance = (r, g, b) => {
  const f = (c) => {
    c /= 255;
    return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
  };
  return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b);
};

const run = async () => {
  const navigateur = await chromium.launch();
  const coupables = new Map();

  for (const cible of CIBLES) {
    const contexte = await navigateur.newContext({ viewport: { width: 1440, height: 900 } });
    try {
      await loginAs(contexte, BASE, cible.cred);
      const page = await contexte.newPage();
      await page.addInitScript(() => { try { localStorage.setItem('theme', 'dark'); } catch (e) {} });
      await page.goto(BASE + cible.chemin, { waitUntil: 'domcontentloaded', timeout: 30000 });
      await page.waitForTimeout(1600);

      const trouves = await page.evaluate(() => {
        const lum = (r, g, b) => {
          const f = (c) => { c /= 255; return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4); };
          return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b);
        };
        const rgb = (s) => (s.match(/\d+/g) || []).slice(0, 3).map(Number);
        const sortie = [];

        for (const el of document.querySelectorAll('body:not(.cx-shell) *')) {
          const st = getComputedStyle(el);
          const fond = rgb(st.backgroundColor);
          const alpha = parseFloat((st.backgroundColor.match(/[\d.]+\)$/) || ['1'])[0]) || 1;

          // Une SURFACE claire opaque posee sur la nuit : c'est le defaut qu'on cherche.
          if (fond.length === 3 && alpha > 0.55 && lum(...fond) > 0.55) {
            const r = el.getBoundingClientRect();
            if (r.width > 80 && r.height > 40) {
              sortie.push({ type: 'surface', cls: el.className.toString().slice(0, 190), tag: el.tagName });
            }
          }

          // Un TEXTE sombre sur la nuit.
          const txt = (el.textContent || '').trim();
          if (txt && txt.length < 90 && el.children.length === 0) {
            const c = rgb(st.color);
            if (c.length === 3 && lum(...c) < 0.22) {
              sortie.push({ type: 'texte', cls: el.className.toString().slice(0, 190), tag: el.tagName, txt: txt.slice(0, 40) });
            }
          }
        }
        return sortie;
      });

      for (const t of trouves) {
        for (const c of t.cls.split(/\s+/).filter(Boolean)) {
          const k = `${t.type}  ${c}`;
          coupables.set(k, (coupables.get(k) ?? 0) + 1);
        }
      }
      console.log(`${cible.chemin} : ${trouves.length} element(s) fautif(s)`);
    } catch (e) {
      console.log(`ERR ${cible.chemin} : ${e.message.split('\n')[0]}`);
    }
    await contexte.close();
  }

  await navigateur.close();

  console.log('\n— Les classes les plus souvent en cause —');
  for (const [k, n] of [...coupables.entries()].sort((a, b) => b[1] - a[1]).slice(0, 40)) {
    console.log(`  ${String(n).padStart(4)}  ${k}`);
  }
};

run();
