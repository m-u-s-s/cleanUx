import { chromium } from 'playwright';

const b = await chromium.launch();
const p = await (await b.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
const err = [];
p.on('pageerror', (e) => err.push(String(e).slice(0, 140)));

await p.goto('http://127.0.0.1:8000/login', { waitUntil: 'domcontentloaded' });
await p.getByRole('button', { name: 'Refuser optionnels', exact: true }).first().click().catch(() => {});
await p.fill('#email', 'admin@brio.test');
await p.fill('#password', '12345678');
await p.click('button[type=submit]').catch(() => {});
await p.waitForTimeout(1800);

await p.goto('http://127.0.0.1:8000/admin/analytics', { waitUntil: 'domcontentloaded' });
await p.waitForTimeout(2500);
err.length = 0;

await p.locator('a').filter({ hasText: /^\s*Dashboard/ }).first().click().catch(() => {});
await p.waitForTimeout(2500);

const d = await p.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
console.log('clic « Dashboard » depuis /admin/analytics : ' + (err.length ? err.join(' | ') : 'aucune erreur JS'));
console.log('arrivée : ' + p.url().replace('http://127.0.0.1:8000', '') + ' — débordement ' + d + 'px');

await b.close();
