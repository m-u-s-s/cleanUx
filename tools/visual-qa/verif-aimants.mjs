/** Les boutons aimantes suivent-ils le curseur, et les boutons clairs restent-ils immobiles ? */
import { chromium } from 'playwright';
const nav = await chromium.launch();
const ctx = await nav.newContext({ viewport: { width: 1440, height: 900 }, hasTouch: false });
const p = await ctx.newPage();
await p.goto('http://127.0.0.1:8000/', { waitUntil: 'domcontentloaded' });
await p.getByRole('button', { name: 'Refuser optionnels', exact: true }).first().click().catch(() => {});
await p.waitForTimeout(2600);

async function teste(nom, selecteur, doitBouger) {
  const el = p.locator(selecteur).first();
  if (!(await el.count())) { console.log('  ' + nom.padEnd(34) + 'ABSENT'); return; }
  await el.scrollIntoViewIfNeeded();
  await p.waitForTimeout(700);
  const b = await el.boundingBox();
  if (!b) { console.log('  ' + nom.padEnd(34) + 'hors ecran'); return; }
  // On vise un coin du bouton : un aimant y repond le plus fort.
  await p.mouse.move(b.x + b.width * 0.9, b.y + b.height * 0.85);
  await p.waitForTimeout(420);
  const t = await el.evaluate((e) => {
    const w = e.closest('.cx-magnetic') || e;
    const tr = getComputedStyle(w).transform;
    if (!tr.startsWith('matrix')) return { x: 0, y: 0, aimante: !!e.closest('.cx-magnetic') };
    const v = tr.split(',');
    return { x: Math.round(parseFloat(v[4])), y: Math.round(parseFloat(v[5])), aimante: !!e.closest('.cx-magnetic') };
  });
  const bouge = Math.abs(t.x) + Math.abs(t.y) > 2;
  const ok = bouge === doitBouger;
  console.log('  ' + nom.padEnd(34) + (t.aimante ? 'aimante ' : 'libre   ')
    + 'deplacement ' + String(t.x).padStart(4) + ',' + String(t.y).padStart(4)
    + '   ' + (ok ? 'conforme' : '— ATTENDU : ' + (doitBouger ? 'doit bouger' : 'doit rester fixe')));
  await p.mouse.move(10, 10);
  await p.waitForTimeout(200);
}

console.log('DOIVENT SUIVRE LE CURSEUR (fond plein)');
await teste('carrousel', '.cx-metier__bouton', true);
await teste('fin de bloc', '#cx-cote-client .cx-arg + div .cx-magnetic a, #cx-cote-client section .mt-12 a', true);
await teste('appel final du cote', '#cx-cote-client .cx-cote__final a', true);

console.log('\nDOIVENT RESTER FIXES (fond clair)');
await teste('« gagner de l\u2019argent »', '.cx-cote__bascule-lien', false);
await teste('telechargement app', '.cx-apps__bouton', false);
await teste('onglet de bascule', '.cx-bascule__onglet', false);

await nav.close();
