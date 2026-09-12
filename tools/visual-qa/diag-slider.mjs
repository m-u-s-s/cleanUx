/** Diagnostic du carrousel epingle : course reelle, defilement a la molette, bascule. */
import { chromium } from 'playwright';
const nav = await chromium.launch();
const ctx = await nav.newContext({ viewport: { width: 1440, height: 900 } });
const p = await ctx.newPage();
const err = [];
p.on('pageerror', (e) => err.push(String(e).slice(0, 150)));
await p.goto('http://127.0.0.1:8000/', { waitUntil: 'domcontentloaded' });
await p.getByRole('button', { name: 'Refuser optionnels', exact: true }).first().click().catch(() => {});
await p.waitForTimeout(2600);

const g = await p.evaluate(() => {
  const s = document.querySelector('#metiers');
  const t = s.querySelector('[data-scroll-track]');
  const sp = s.closest('.pin-spacer') || s.parentElement;
  return {
    course: t.scrollWidth - s.clientWidth,
    pisteW: t.scrollWidth, sectionW: s.clientWidth,
    debutPin: Math.round(s.getBoundingClientRect().top + scrollY),
    spacerH: sp && sp.classList.contains('pin-spacer') ? Math.round(sp.offsetHeight) : 'pas de spacer',
    pageH: Math.round(document.body.scrollHeight),
  };
});
console.log('GEOMETRIE');
console.log('  piste ' + g.pisteW + 'px / ecran ' + g.sectionW + 'px  ->  course = ' + g.course + 'px de defilement');
console.log('  le pin commence a y=' + g.debutPin + '  | pin-spacer: ' + g.spacerH + '  | page: ' + g.pageH + 'px');

console.log('\nDEFILEMENT SUR LA COURSE REELLE');
let dernier = null, sauts = [];
for (const f of [0, 0.12, 0.25, 0.4, 0.55, 0.7, 0.85, 1]) {
  const y = g.debutPin + g.course * f;
  await p.evaluate((yy) => window.scrollTo({ top: yy, behavior: 'instant' }), y);
  await p.waitForTimeout(700);
  const m = await p.evaluate(() => {
    const s = document.querySelector('#metiers');
    const t = s.querySelector('[data-scroll-track]');
    const tr = getComputedStyle(t).transform;
    const x = tr.startsWith('matrix') ? Math.round(parseFloat(tr.split(',')[4])) : 0;
    const vus = [...s.querySelectorAll('[data-scroll-panel]')].map((e) => {
      const r = e.getBoundingClientRect();
      return r.left < innerWidth && r.right > 0 ? Math.round(r.left) : null;
    });
    return { x, top: Math.round(s.getBoundingClientRect().top), vus: vus.filter((v) => v !== null).length };
  });
  const attendu = Math.round(-g.course * f);
  const ecart = Math.abs(m.x - attendu);
  if (dernier !== null && Math.abs(m.x - dernier) > g.course * 0.3) sauts.push(f);
  dernier = m.x;
  console.log(`  ${String(Math.round(f * 100)).padStart(3)}%  translateX=${String(m.x).padStart(6)}  (attendu ${String(attendu).padStart(6)}, ecart ${String(ecart).padStart(4)})  sectionTop=${String(m.top).padStart(4)}  panneaux visibles=${m.vus}`);
}
console.log('  sauts brusques :', sauts.length ? sauts.join(', ') : 'aucun');

console.log('\nDEFILEMENT A LA MOLETTE (comme un vrai visiteur)');
await p.evaluate((y) => window.scrollTo({ top: y, behavior: 'instant' }), g.debutPin - 400);
await p.waitForTimeout(800);
const suivi = [];
for (let i = 0; i < 26; i++) {
  await p.mouse.wheel(0, 400);
  await p.waitForTimeout(130);
  suivi.push(await p.evaluate(() => {
    const s = document.querySelector('#metiers');
    const t = s.querySelector('[data-scroll-track]');
    const tr = getComputedStyle(t).transform;
    return { y: Math.round(scrollY), top: Math.round(s.getBoundingClientRect().top),
             x: tr.startsWith('matrix') ? Math.round(parseFloat(tr.split(',')[4])) : 0 };
  }));
}
let reculs = 0, bloque = 0;
suivi.forEach((m, i) => {
  if (i === 0) return;
  if (m.x > suivi[i - 1].x + 5) reculs++;
  if (Math.abs(m.y - suivi[i - 1].y) < 5) bloque++;
});
console.log('  translateX au fil de la molette :', suivi.filter((_, i) => i % 3 === 0).map((m) => m.x).join(' '));
console.log('  reculs du rail  :', reculs, reculs ? '— LE RAIL PART EN ARRIERE' : '');
console.log('  scroll bloque   :', bloque, 'fois sur 25');
console.log('  sectionTop      :', [...new Set(suivi.map((m) => m.top))].slice(0, 8).join(' '));

console.log('\nBASCULE PENDANT LE PIN');
await p.evaluate((y) => window.scrollTo({ top: y, behavior: 'instant' }), g.debutPin + g.course * 0.5);
await p.waitForTimeout(900);
const a = await p.evaluate(() => {
  const t = document.querySelector('#metiers [data-scroll-track]');
  const tr = getComputedStyle(t).transform;
  return { y: Math.round(scrollY), x: tr.startsWith('matrix') ? Math.round(parseFloat(tr.split(',')[4])) : 0 };
});
await p.evaluate(() => document.querySelectorAll('.cx-bascule__onglet')[1].click());
await p.waitForTimeout(1900);
const b = await p.evaluate(() => {
  const t = document.querySelector('#metiers [data-scroll-track]');
  const tr = getComputedStyle(t).transform;
  return { y: Math.round(scrollY), x: tr.startsWith('matrix') ? Math.round(parseFloat(tr.split(',')[4])) : 0 };
});
console.log('  avant : y=' + a.y + ' translateX=' + a.x);
console.log('  apres : y=' + b.y + ' translateX=' + b.x);
console.log('  saut vertical : ' + Math.abs(b.y - a.y) + 'px   saut horizontal : ' + Math.abs(b.x - a.x) + 'px');

console.log('\nerreurs JS :', err.length ? err.join(' | ') : 'aucune');
await nav.close();
