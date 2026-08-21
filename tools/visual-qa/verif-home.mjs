import { chromium } from 'playwright';

const b = await chromium.launch();
const p = await (await b.newContext({ viewport: { width: 1440, height: 900 } })).newPage();

const err = [];
p.on('pageerror', (e) => err.push(String(e).slice(0, 160)));

await p.goto('http://127.0.0.1:8000/login', { waitUntil: 'domcontentloaded' });
await p.getByRole('button', { name: 'Refuser optionnels', exact: true }).first().click().catch(() => {});
await p.fill('#email', 'admin@brio.test');
await p.fill('#password', '12345678');
await p.click('button[type=submit]').catch(() => {});
await p.waitForTimeout(1600);

for (const u of ['/admin/home', '/admin/dashboard']) {
  err.length = 0;
  await p.goto('http://127.0.0.1:8000' + u, { waitUntil: 'domcontentloaded' });
  await p.waitForTimeout(2200);
  console.log(u.padEnd(20) + ' : ' + (err.length ? err.join(' | ') : 'aucune erreur JS'));
}

await b.close();
