/** Capture l'accueil section par section, pour montrer la page telle qu'elle rend. */
import { chromium } from 'playwright';
import { mkdirSync } from 'node:fs';
const S = 'tools/visual-qa/out/montre/';
mkdirSync(S, { recursive: true });
const nav = await chromium.launch();

async function ouvrir(theme) {
  const ctx = await nav.newContext({
    viewport: { width: 1400, height: 880 },
    colorScheme: theme,
    reducedMotion: 'reduce', // fige le film : sinon le pin deplace la page sous la capture
  });
  const p = await ctx.newPage();
  await p.goto('http://127.0.0.1:8000/', { waitUntil: 'domcontentloaded' });
  await p.getByRole('button', { name: 'Refuser optionnels', exact: true }).first().click().catch(() => {});
  await p.waitForTimeout(2200);
  return { ctx, p };
}

/* Les barres collantes se reimpriment dans CHAQUE capture de section : on les
   masque le temps de la prise, puis on les rend. */
const CACHER = '.cxnav, .cx-nav, header, .cx-bascule, .cx-cta-flottant, [class*="flottant"], [class*="sticky-cta"]';

async function prendre(p, selecteur, fichier, cacherBascule = true) {
  const el = p.locator(selecteur).first();
  if (!(await el.count())) { console.log('  absent :', selecteur); return; }
  await el.scrollIntoViewIfNeeded();
  await p.waitForTimeout(900);
  await p.evaluate(([sel, garder]) => {
    document.querySelectorAll(sel).forEach((e) => {
      const cs = getComputedStyle(e);
      if (cs.position !== 'sticky' && cs.position !== 'fixed') return;
      if (! garder && e.classList.contains('cx-bascule')) return;
      e.dataset.cache = '1';
      e.style.visibility = 'hidden';
    });
  }, [CACHER, cacherBascule]);
  await p.waitForTimeout(220);
  await el.screenshot({ path: S + fichier });
  await p.evaluate(() => document.querySelectorAll('[data-cache]').forEach((e) => {
    e.style.visibility = ''; delete e.dataset.cache;
  }));
  const b = await el.boundingBox();
  console.log('  ' + fichier.padEnd(30) + Math.round(b.width) + 'x' + Math.round(b.height));
}

for (const theme of ['light', 'dark']) {
  const { ctx, p } = await ouvrir(theme);
  console.log('\n' + theme.toUpperCase());

  // Le heros : capture au viewport, il occupe l'ecran entier.
  await p.screenshot({ path: `${S}${theme}-1-heros.png` });
  console.log('  ' + `${theme}-1-heros.png`.padEnd(30) + '1400x880');

  await prendre(p, '.cx-bascule', `${theme}-2-bascule.png`, false);
  await prendre(p, '.cx-metier__panneau--intro', `${theme}-3-metiers.png`);

  // COTE CLIENT
  await prendre(p, '#cx-cote-client .cx-cote__accroche', `${theme}-4-client-ouverture.png`);
  await prendre(p, '#cx-cote-client section.cx-arg:nth-of-type(2)', `${theme}-5-client-pourquoi.png`);
  await prendre(p, '#cx-cote-client section.cx-arg:nth-of-type(4)', `${theme}-6-client-confiance.png`);
  await prendre(p, '#cx-cote-client .cx-cote__final', `${theme}-7-client-final.png`);

  // COTE PRESTATAIRE
  await p.getByRole('tab', { name: /propose mes services/i }).click();
  await p.waitForTimeout(1400);
  await prendre(p, '#cx-cote-prestataire .cx-cote__accroche', `${theme}-8-presta-ouverture.png`);
  await prendre(p, '#cx-cote-prestataire section.cx-arg:nth-of-type(2)', `${theme}-9-presta-gagner.png`);
  await prendre(p, '#cx-cote-prestataire section.cx-arg:nth-of-type(4)', `${theme}-10-presta-societe.png`);
  await prendre(p, '#cx-cote-prestataire .cx-cote__final', `${theme}-11-presta-final.png`);

  await ctx.close();
}
await nav.close();
console.log('\nCaptures dans ' + S);
