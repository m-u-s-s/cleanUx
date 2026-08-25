// Capture une page dans les deux modes, en bureau ET en mobile, pour juger la matiere.
import { chromium } from 'playwright';
import { loginAs } from './check.mjs';

const BASE = 'http://127.0.0.1:8000';
const SORTIE = process.env.SORTIE ?? '.';

const cibles = [
  { cle: 'admin', cred: 'admin', chemin: '/admin/accounting-v2' },
  { cle: 'prestataire', cred: 'provider', chemin: '/dashboard/employe' },
  { cle: 'client', cred: 'client', chemin: '/dashboard/client' },
];

const run = async () => {
  const navigateur = await chromium.launch();

  for (const cible of cibles) {
    for (const [nomVue, viewport] of [['bureau', { width: 1440, height: 900 }], ['mobile', { width: 390, height: 844 }]]) {
      for (const theme of ['light', 'dark']) {
        const contexte = await navigateur.newContext({ viewport });
        try {
          await loginAs(contexte, BASE, cible.cred);
          const page = await contexte.newPage();
          await page.addInitScript((t) => {
            try { localStorage.setItem('theme', t); } catch (e) {}
          }, theme);
          await page.goto(BASE + cible.chemin, { waitUntil: 'domcontentloaded', timeout: 30000 });
          await page.waitForTimeout(1600);
          await page.screenshot({ path: `${SORTIE}/${cible.cle}-${nomVue}-${theme}.png`, fullPage: false });
          console.log(`ok  ${cible.cle} ${nomVue} ${theme}`);
        } catch (e) {
          console.log(`ERR ${cible.cle} ${nomVue} ${theme} : ${e.message.split('\n')[0]}`);
        }
        await contexte.close();
      }
    }
  }

  await navigateur.close();
};

run();
