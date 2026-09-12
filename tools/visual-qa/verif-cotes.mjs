/** Les deux cotes de l'accueil : bascule, carrousel, film commun, memoire du choix. */
import { chromium } from 'playwright';
import { mkdirSync } from 'node:fs';
const SORTIE = 'tools/visual-qa/out/cotes/';
mkdirSync(SORTIE, { recursive: true });
const nav = await chromium.launch();

const etat = (p) => p.evaluate(() => {
  const vis = (id) => { const e = document.getElementById(id); return e ? getComputedStyle(e).display : 'absent'; };
  const imgs = [...document.querySelectorAll('.cx-arg__image img')];
  return {
    client: vis('cx-cote-client'),
    prestataire: vis('cx-cote-prestataire'),
    choisi: document.querySelector('.cx-bascule__onglet.is-choisi')?.textContent.trim(),
    curseur: getComputedStyle(document.querySelector('.cx-bascule__curseur') || document.body).transform,
    carrouselSurtitre: document.querySelector('.cx-metier__panneau--intro .cx-subhead')?.textContent.trim(),
    carrouselAngle: document.querySelector('.cx-metier__angle')?.textContent.trim().slice(0, 52),
    film: document.querySelector('[data-cx-film]') ? getComputedStyle(document.querySelector('[data-cx-film]')).display : 'absent',
    cartes: document.querySelectorAll('.cx-arg__carte').length,
    images: imgs.length,
    chargees: imgs.filter((i) => i.naturalWidth > 0).length,
    debordement: document.documentElement.scrollWidth > document.documentElement.clientWidth,
  };
});

for (const theme of ['light', 'dark']) {
  const ctx = await nav.newContext({ viewport: { width: 1440, height: 950 }, colorScheme: theme });
  const p = await ctx.newPage();
  const err = [];
  p.on('pageerror', (e) => err.push(String(e).slice(0, 130)));
  await p.goto('http://127.0.0.1:8000/', { waitUntil: 'domcontentloaded' });
  await p.getByRole('button', { name: 'Refuser optionnels', exact: true }).first().click().catch(() => {});
  await p.locator('.cx-bascule').scrollIntoViewIfNeeded();
  await p.waitForTimeout(1600);

  const a = await etat(p);
  await p.screenshot({ path: `${SORTIE}${theme}-client.png` });

  await p.getByRole('tab', { name: /propose mes services/i }).click();
  await p.waitForTimeout(1400);
  const b = await etat(p);
  await p.screenshot({ path: `${SORTIE}${theme}-prestataire.png` });

  console.log(`\n${theme.toUpperCase()}`);
  console.log('  client   : ' + a.client + ' -> ' + b.client);
  console.log('  presta   : ' + a.prestataire + ' -> ' + b.prestataire);
  console.log('  onglet   : ' + a.choisi + ' -> ' + b.choisi);
  console.log('  curseur  : ' + (a.curseur === b.curseur ? 'IMMOBILE — DEFAUT' : 'il glisse'));
  console.log('  carrousel: "' + a.carrouselSurtitre + '" -> "' + b.carrouselSurtitre + '"'
    + (a.carrouselSurtitre === b.carrouselSurtitre ? '  — NE BASCULE PAS' : ''));
  console.log('  angle    : "' + a.carrouselAngle + '"\n             -> "' + b.carrouselAngle + '"');
  console.log('  film     : ' + a.film + ' / ' + b.film + '  (doit rester visible des deux cotes)');
  console.log('  cartes   : ' + a.cartes + ' -> ' + b.cartes);
  console.log('  images   : ' + b.chargees + '/' + b.images);
  console.log('  debordement horizontal : ' + (b.debordement ? 'OUI — DEFAUT' : 'non'));
  console.log('  erreurs JS : ' + (err.length ? err.join(' | ') : 'aucune'));
  await ctx.close();
}

/* LA MEMOIRE : le choix doit survivre au rechargement. */
{
  const ctx = await nav.newContext({ viewport: { width: 1440, height: 950 } });
  const p = await ctx.newPage();
  await p.goto('http://127.0.0.1:8000/', { waitUntil: 'domcontentloaded' });
  await p.getByRole('button', { name: 'Refuser optionnels', exact: true }).first().click().catch(() => {});
  await p.getByRole('tab', { name: /propose mes services/i }).click();
  await p.waitForTimeout(700);
  await p.reload({ waitUntil: 'domcontentloaded' });
  await p.waitForTimeout(1400);
  const apres = await etat(p);
  console.log('\nMEMOIRE DU CHOIX');
  console.log('  apres rechargement : ' + apres.choisi + (/services/i.test(apres.choisi || '') ? '  (bon)' : '  — OUBLIE'));

  /* LE LIEN PARTAGE : #prestataire doit ouvrir le bon cote. */
  const ctx2 = await nav.newContext({ viewport: { width: 1440, height: 950 } });
  const p2 = await ctx2.newPage();
  await p2.goto('http://127.0.0.1:8000/#prestataire', { waitUntil: 'domcontentloaded' });
  await p2.waitForTimeout(1400);
  const parLien = await etat(p2);
  console.log('  par lien #prestataire : ' + parLien.choisi + (/services/i.test(parLien.choisi || '') ? '  (bon)' : '  — IGNORE'));
  await ctx.close(); await ctx2.close();
}

/* SANS JAVASCRIPT : le cote client doit rester lisible. */
{
  const ctx = await nav.newContext({ viewport: { width: 1440, height: 950 }, javaScriptEnabled: false });
  const p = await ctx.newPage();
  await p.goto('http://127.0.0.1:8000/', { waitUntil: 'domcontentloaded' });
  const r = await p.evaluate(() => ({
    client: document.getElementById('cx-cote-client') ? getComputedStyle(document.getElementById('cx-cote-client')).display : 'absent',
    presta: document.getElementById('cx-cote-prestataire') ? getComputedStyle(document.getElementById('cx-cote-prestataire')).display : 'absent',
    texte: (document.getElementById('cx-cote-client')?.innerText || '').length,
  }));
  console.log('\nSANS JAVASCRIPT');
  console.log('  cote client : ' + r.client + ' (' + r.texte + ' caracteres lisibles)');
  console.log('  cote presta : ' + r.presta + ' (masque : normal)');
  await p.screenshot({ path: `${SORTIE}sans-js.png` });
  await ctx.close();
}
await nav.close();
console.log('\nCaptures dans ' + SORTIE);
