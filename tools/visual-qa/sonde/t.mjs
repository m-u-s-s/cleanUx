import { chromium } from 'playwright';

const JOUR = { fond: '#ffffff', ink: '#1a2436' };   // les cartes fidelite sont bg-white
const NUIT = { fond: '#0a0e1a', ink: '#eaf0fb' };

// Les pires cas de chaque theme, plus des teintes plausibles pour un niveau.
const TEINTES = ['#000000', '#ffffff', '#1e293b', '#0a0e1a', '#fde68a', '#facc15',
                 '#dc2626', '#6366f1', '#cd7f32', '#c0c0c0', '#ffd700', '#e5e4e2'];

const nav = await chromium.launch();
const page = await nav.newPage();

// La toile convertit : elle connait color(), oklch(), lab(). Mon regex non.
const mesurer = (couleur, fond) => page.evaluate(([c, f]) => {
  const el = document.body;
  el.style.setProperty('--t', 'red');
  el.style.color = '';
  el.style.color = c;
  const rendu = getComputedStyle(el).color;
  const cv = document.createElement('canvas'); cv.width = cv.height = 1;
  const x = cv.getContext('2d');
  x.fillStyle = f; x.fillRect(0, 0, 1, 1);
  x.fillStyle = rendu; x.fillRect(0, 0, 1, 1);
  const [r, v, b] = x.getImageData(0, 0, 1, 1).data;
  x.fillStyle = f; x.fillRect(0, 0, 1, 1);
  const [fr, fv, fb] = x.getImageData(0, 0, 1, 1).data;
  const g = (n) => { n /= 255; return n <= 0.03928 ? n / 12.92 : Math.pow((n + 0.055) / 1.055, 2.4); };
  const L = 0.2126*g(r) + 0.7152*g(v) + 0.0722*g(b);
  const F = 0.2126*g(fr) + 0.7152*g(fv) + 0.0722*g(fb);
  return (Math.max(L, F) + 0.05) / (Math.min(L, F) + 0.05);
}, [couleur, fond]);

for (const [nomT, th, lmin, lmax] of [['JOUR', JOUR, 0, null], ['NUIT', NUIT, null, 1]]) {
  console.log(`\n  ${nomT}  fond ${th.fond}  ink ${th.ink}`);
  const bornes = nomT === 'JOUR' ? [0.60, 0.55, 0.50, 0.45] : [0.55, 0.60, 0.62, 0.66];
  const entete = bornes.map((b) => ('L' + b.toFixed(2)).padStart(7)).join('');
  console.log(`  teinte     brut ${entete}   mix40`);

  for (const t of TEINTES) {
    const cells = [];
    cells.push((await mesurer(t, th.fond)).toFixed(2).padStart(6));
    for (const b of bornes) {
      const a = nomT === 'JOUR' ? 0 : b;
      const z = nomT === 'JOUR' ? b : 1;
      const r = await mesurer(`oklch(from ${t} clamp(${a}, l, ${z}) c h)`, th.fond);
      cells.push((r < 4.5 ? '!' : ' ') + r.toFixed(2).padStart(6));
    }
    const m = await mesurer(`color-mix(in srgb, ${t} 40%, ${th.ink})`, th.fond);
    cells.push((m < 4.5 ? '!' : ' ') + m.toFixed(2).padStart(6));
    console.log(`  ${t} ${cells.join('')}`);
  }
}
await nav.close();
