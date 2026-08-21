import { chromium } from 'playwright';

const b = await chromium.launch({ headless: false, args: ['--window-size=1400,950', '--window-position=40,30'] });
const p = await (await b.newContext({ viewport: { width: 1360, height: 880 } })).newPage();

await p.goto('http://127.0.0.1:8000/login', { waitUntil: 'domcontentloaded' });
await p.getByRole('button', { name: 'Refuser optionnels', exact: true }).first().click().catch(() => {});
await p.fill('#email', 'lemoine.gabrielle@example.net');
await p.fill('#password', '12345678');
await p.click('button[type=submit]').catch(() => {});
await p.waitForTimeout(1600);

await p.goto('http://127.0.0.1:8000/dashboard/client/litiges', { waitUntil: 'domcontentloaded' });
await p.waitForTimeout(1500);

await p.locator('button').filter({ hasText: /Vitre du salon/ }).first().click().catch(() => {});
await p.waitForTimeout(1800);

const vu = await p.evaluate(() => {
  const t = document.body.innerText;
  return {
    reference: (t.match(/REC-\d{6}/) || [])[0] || null,
    fil: t.includes('reçue par le support'),
    zone: !!document.querySelector('textarea'),
    bouton: t.includes('Envoyer la réponse'),
  };
});

console.log('  référence affichée : ' + (vu.reference || 'ABSENTE'));
console.log('  fil de discussion  : ' + (vu.fil ? 'affiché' : 'absent'));
console.log('  zone de réponse    : ' + (vu.zone ? 'présente' : 'absente'));
console.log('  bouton de réponse  : ' + (vu.bouton ? 'présent' : 'absent'));

await p.screenshot({ path: 'out/litiges-acheve.png' });
await p.waitForTimeout(2500);
await b.close();
