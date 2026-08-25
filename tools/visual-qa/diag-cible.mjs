// Interroge TROIS elements precis vus casses sur la capture, et rapporte d'ou vient
// leur couleur. Une moyenne ne dit pas quelle regle perd : il faut le cas nomme.
import { chromium } from 'playwright';
import { loginAs } from './check.mjs';

const BASE = process.env.VQA_BASE ?? 'http://127.0.0.1:8000';

const run = async () => {
  const navigateur = await chromium.launch();
  const contexte = await navigateur.newContext({ viewport: { width: 1440, height: 900 } });

  await loginAs(contexte, BASE, 'client');
  const page = await contexte.newPage();
  await page.addInitScript(() => { try { localStorage.setItem('theme', 'dark'); } catch (e) {} });
  await page.goto(BASE + '/dashboard/client', { waitUntil: 'domcontentloaded', timeout: 30000 });
  await page.waitForTimeout(1600);

  const rapport = await page.evaluate(() => {
    const decrire = (el, nom) => {
      if (!el) return { nom, absent: true };
      const st = getComputedStyle(el);
      return {
        nom,
        tag: el.tagName,
        classes: el.className.toString().slice(0, 160),
        couleur: st.color,
        fond: st.backgroundColor,
        image: st.backgroundImage.slice(0, 90),
        flou: st.backdropFilter,
      };
    };

    const parTexte = (frag) => [...document.querySelectorAll('body *')]
      .filter((e) => e.children.length === 0 && (e.textContent || '').trim().includes(frag))[0];

    const heros = document.querySelector('[class*="ESPACE"], .brio-hero')
      || [...document.querySelectorAll('div')].find((d) => (d.textContent || '').includes('ESPACE CLIENT'));

    return [
      decrire(heros, 'le heros'),
      decrire(parTexte('Aucune reservation') || parTexte('Aucune réservation'), 'le vide'),
      decrire(parTexte('Total prestations'), 'libelle de stat'),
      decrire(document.documentElement, '<html>'),
      decrire(document.body, '<body>'),
    ];
  });

  for (const r of rapport) {
    if (r.absent) { console.log(`  ${r.nom} : ABSENT`); continue; }
    console.log(`  ${r.nom}  <${r.tag}>`);
    console.log(`      classes : ${r.classes}`);
    console.log(`      couleur : ${r.couleur}   fond : ${r.fond}`);
    if (r.image && r.image !== 'none') console.log(`      image   : ${r.image}`);
    if (r.flou && r.flou !== 'none') console.log(`      flou    : ${r.flou}`);
  }

  await navigateur.close();
};

run();
