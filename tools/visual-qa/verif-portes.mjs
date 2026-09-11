/** Les deux portes de l'accueil : bascule, images, et repli sans JS. */
import { chromium } from 'playwright';
import { mkdirSync } from 'node:fs';
const SORTIE = 'tools/visual-qa/out/portes/';
mkdirSync(SORTIE, { recursive: true });
const nav = await chromium.launch();

for (const theme of ['light', 'dark']) {
  const ctx = await nav.newContext({ viewport: { width: 1440, height: 950 }, colorScheme: theme });
  const p = await ctx.newPage();
  const erreurs = [];
  p.on('pageerror', (e) => erreurs.push(String(e).slice(0, 140)));
  await p.goto('http://127.0.0.1:8000/', { waitUntil: 'domcontentloaded' });
  await p.getByRole('button', { name: 'Refuser optionnels', exact: true }).first().click().catch(() => {});
  await p.locator('#commencer').scrollIntoViewIfNeeded();
  await p.waitForTimeout(1200);

  const etat = async () => p.evaluate(() => {
    const v = (id) => { const e = document.getElementById(id); return e ? getComputedStyle(e).display : 'absent'; };
    const imgs = [...document.querySelectorAll('.cx-arg__image img')];
    return {
      client: v('cx-arg-volet-client'), gagnant: v('cx-arg-volet-gagnant'),
      portes: document.querySelectorAll('.cx-arg__porte').length,
      choisie: document.querySelector('.cx-arg__porte.is-choisie')?.textContent.trim().split('\n')[0],
      images: imgs.length,
      chargees: imgs.filter((i) => i.naturalWidth > 0).length,
      hauteur: Math.round(document.body.scrollHeight),
    };
  });

  const avant = await etat();
  await p.screenshot({ path: `${SORTIE}${theme}-client.png` });

  await p.getByRole('tab', { name: /gagner de l/i }).click();
  await p.waitForTimeout(900);
  const apres = await etat();
  await p.screenshot({ path: `${SORTIE}${theme}-gagnant.png` });

  console.log(`\n${theme.toUpperCase()}`);
  console.log('  portes           :', avant.portes);
  console.log('  par defaut       : client=' + avant.client + ' gagnant=' + avant.gagnant + '  (' + avant.choisie + ')');
  console.log('  apres bascule    : client=' + apres.client + ' gagnant=' + apres.gagnant + '  (' + apres.choisie + ')');
  console.log('  images chargees  : ' + apres.chargees + '/' + apres.images);
  console.log('  hauteur de page  : ' + avant.hauteur + ' px');
  console.log('  erreurs JS       :', erreurs.length ? erreurs.join(' | ') : 'aucune');
  await ctx.close();
}

/* SANS JAVASCRIPT : le volet client doit rester lisible. */
{
  const ctx = await nav.newContext({ viewport: { width: 1440, height: 950 }, javaScriptEnabled: false });
  const p = await ctx.newPage();
  await p.goto('http://127.0.0.1:8000/', { waitUntil: 'domcontentloaded' });
  const r = await p.evaluate(() => {
    const e = document.getElementById('cx-arg-volet-client');
    const g = document.getElementById('cx-arg-volet-gagnant');
    return { client: e ? getComputedStyle(e).display : 'absent', gagnant: g ? getComputedStyle(g).display : 'absent',
             texteVisible: (document.getElementById('cx-arg-volet-client')?.innerText || '').length };
  });
  console.log('\nSANS JAVASCRIPT');
  console.log('  volet client  :', r.client, '(doit etre visible)');
  console.log('  volet gagnant :', r.gagnant, '(masque : normal)');
  console.log('  texte lisible :', r.texteVisible, 'caracteres');
  await p.screenshot({ path: `${SORTIE}sans-js.png` });
  await ctx.close();
}
await nav.close();
console.log('\nCaptures dans', SORTIE);
