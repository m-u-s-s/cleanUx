// LE FILET : SANS MODALE, LIVEWIRE REPREND LA MAIN.
//
// L'interception remplace `el.__livewire_confirm` sur toute page. Si la modale n'est pas
// montee — `layouts/guest` ne la monte pas — un bouton qui ne ferait PLUS RIEN serait pire
// que la boite grise : l'utilisateur clique, rien ne bouge, et rien ne le dit.
//
// On retire la modale de la page, puis on clique : la boite native doit reapparaitre.

import { chromium } from 'playwright';
import { loginAs } from './check.mjs';

const BASE = process.env.VQA_BASE ?? 'http://127.0.0.1:8000';
const nav = await chromium.launch();
const ctx = await nav.newContext({ viewport: { width: 390, height: 844 } });
await loginAs(ctx, BASE, 'admin');

const page = await ctx.newPage();

let boitesNatives = 0;
// On REFUSE : ce test ne doit rien supprimer.
page.on('dialog', async (d) => { boitesNatives += 1; await d.dismiss(); });

await page.goto(BASE + '/admin/trades', { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(2500);

// On simule une mise en page qui ne monte pas le composant.
const retire = await page.evaluate(() => {
  const n = [...document.querySelectorAll('*')]
    .find((e) => e.hasAttribute('x-on:brio-confirmer.window'));
  if (!n) return false;
  n.remove();
  return true;
});

console.log('modale retiree de la page : ' + (retire ? 'oui' : 'NON'));

const combien = await page.evaluate(() => [...document.querySelectorAll('button')]
  .filter((b) => b.hasAttribute('wire:confirm')).length);
console.log('boutons wire:confirm sur la page : ' + combien);

if (!combien) { console.log('ECHEC — plus de bouton apres le retrait'); await nav.close(); process.exit(1); }

await page.evaluate(() => [...document.querySelectorAll('button')]
  .find((b) => b.hasAttribute('wire:confirm')).click());
await page.waitForTimeout(1200);

const modale = await page.locator('.brio-modal[role="alertdialog"]').count();

console.log('modale de verre ouverte : ' + (modale ? 'oui' : 'non'));
console.log('boite native reprise : ' + (boitesNatives > 0 ? 'oui' : 'NON'));

const tenu = retire && modale === 0 && boitesNatives === 1;
console.log(tenu ? 'OK — sans modale, le bouton demande quand meme.' : 'ECHEC');

await nav.close();
process.exit(tenu ? 0 : 1);
