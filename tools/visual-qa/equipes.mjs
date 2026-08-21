// Fenêtre dédiée au tableau de bord des 4 équipes : elle reste ouverte et
// la page se rafraîchit toute seule toutes les 3 secondes.
import { chromium } from 'playwright';

const navigateur = await chromium.launch({
  headless: false,
  args: ['--window-size=1500,1000', '--window-position=60,20'],
});
const page = await (await navigateur.newContext({ viewport: { width: 1480, height: 920 } })).newPage();
await page.goto('http://127.0.0.1:8899/index.html', { waitUntil: 'domcontentloaded' });
console.log('tableau de bord des équipes ouvert — la fenêtre reste en place');
await page.waitForTimeout(24 * 60 * 60 * 1000);
