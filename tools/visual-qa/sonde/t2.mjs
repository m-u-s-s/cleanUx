import { chromium } from 'playwright';
import { readFileSync } from 'node:fs';

const css = ['tokens', 'composants'].map((f) => readFileSync(`../../../resources/css/${f}.css`, 'utf8')).join('\n');

// La palette canonique des niveaux, plus les pires cas de chaque theme.
const TEINTES = ['#e5e4e2', '#ffd700', '#c0c0c0', '#cd7f32', '#000000', '#ffffff', '#0a0e1a', '#1e293b', '#dc2626', '#6366f1'];

const nav = await chromium.launch();
const page = await nav.newPage();
await page.setContent(`<style>${css}</style><div id="z"><span id="s" class="brio-teinte">42</span></div>`);

const mesurer = (teinte, sombre) => page.evaluate(([t, dark]) => {
  document.documentElement.classList.toggle('dark', dark);
  const s = document.getElementById('s');
  s.style.setProperty('--teinte', t);
  const fond = dark ? '#0a0e1a' : '#ffffff';
  document.body.style.background = fond;

  const cv = document.createElement('canvas'); cv.width = cv.height = 1;
  const x = cv.getContext('2d');
  const lum = (couleur, sous) => {
    x.fillStyle = sous; x.fillRect(0, 0, 1, 1);
    x.fillStyle = couleur; x.fillRect(0, 0, 1, 1);
    const [r, v, b] = x.getImageData(0, 0, 1, 1).data;
    const g = (n) => { n /= 255; return n <= 0.03928 ? n / 12.92 : Math.pow((n + 0.055) / 1.055, 2.4); };
    return 0.2126 * g(r) + 0.7152 * g(v) + 0.0722 * g(b);
  };
  const L = lum(getComputedStyle(s).color, fond);
  const F = lum(fond, '#000');
  return { ratio: (Math.max(L, F) + 0.05) / (Math.min(L, F) + 0.05), rendu: getComputedStyle(s).color };
}, [teinte, sombre]);

console.log('  teinte     JOUR (sur blanc)      NUIT (sur #0a0e1a)');
let rates = 0;
for (const t of TEINTES) {
  const j = await mesurer(t, false);
  const n = await mesurer(t, true);
  if (j.ratio < 4.5) rates++;
  if (n.ratio < 4.5) rates++;
  console.log(`  ${t}  ${j.ratio.toFixed(2).padStart(5)}${j.ratio < 4.5 ? ' !' : '  '} ${j.rendu.padEnd(20)}  ${n.ratio.toFixed(2).padStart(5)}${n.ratio < 4.5 ? ' !' : '  '} ${n.rendu}`);
}
await nav.close();
console.log(`\n  ${TEINTES.length * 2 - rates}/${TEINTES.length * 2} au-dessus de 4,5`);
process.exit(rates ? 1 : 0);
