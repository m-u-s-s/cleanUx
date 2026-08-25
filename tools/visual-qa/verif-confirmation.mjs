// L'ÉPREUVE DE `wire:confirm` — dans un vrai navigateur, seul endroit qui puisse la donner.
//
// Quatre choses à prouver, et aucune suite PHP ne le peut :
//   1. la boîte grise du navigateur ne s'ouvre plus (elle bloquerait la page) ;
//   2. la modale de verre s'ouvre à sa place, avec LE message de l'attribut ;
//   3. refuser n'exécute rien — le point qui compte vraiment ;
//   4. confirmer appelle bien le rappel que Livewire a confié.
//
// ── CE TEST NE SUPPRIME RIEN ─────────────────────────────────────────────────────────────
//
// Sa première version cliquait « Confirmer » sur un vrai bouton de suppression. Elle a donc
// supprimé trois métiers de la base de démonstration — puis a échoué à l'exécution suivante,
// faute de bouton à cliquer : un test qui détruit ce qu'il mesure. Le chemin de l'acceptation
// passe maintenant par une sonde, qui prouve le contrat sans toucher aux données.

import { chromium } from 'playwright';
import { loginAs } from './check.mjs';

const BASE = process.env.VQA_BASE ?? 'http://127.0.0.1:8000';

const nav = await chromium.launch();
const ctx = await nav.newContext({ viewport: { width: 390, height: 844 } });
await loginAs(ctx, BASE, 'admin');

const page = await ctx.newPage();

// Si une boîte native s'ouvrait malgré tout, elle figerait la page : on la compte.
let boitesNatives = 0;
page.on('dialog', async (d) => { boitesNatives += 1; await d.dismiss(); });

// Les appels serveur : c'est eux qui disent si une action est partie.
let appels = 0;
page.on('request', (r) => { if (r.method() === 'POST' && r.url().includes('/livewire/update')) appels += 1; });

await page.goto(BASE + '/admin/trades', { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(2500);

const bouton = page.locator('[wire\\:confirm]').first();

if (!(await bouton.count())) {
  console.log('ÉCHEC — aucun bouton `wire:confirm` sur cette page.');
  await nav.close();
  process.exit(1);
}

const message = await bouton.getAttribute('wire:confirm');
console.log('message attendu : ' + message);

await bouton.click();
await page.waitForTimeout(900);

const modale = page.locator('.brio-modal[role="alertdialog"]');
const ouverte = await modale.count();
console.log('modale de verre ouverte : ' + (ouverte ? 'oui' : 'NON'));
console.log('boîtes natives ouvertes : ' + boitesNatives);

if (!ouverte) {
  console.log('ÉCHEC — la modale ne s’est pas ouverte.');
  await nav.close();
  process.exit(1);
}

const texte = (await modale.locator('.brio-modal-texte').first().textContent()) || '';
console.log('message affiché : ' + texte.trim());

const dangerParDefaut = await modale.evaluate((n) => n.classList.contains('brio-modal-danger'));
console.log('ton danger par défaut : ' + (dangerParDefaut ? 'oui' : 'NON'));

// ── LE REFUS N'EXÉCUTE RIEN ──────────────────────────────────────────────────────────────
const avantRefus = appels;
await modale.getByRole('button', { name: /Annuler|Annuleren|Cancel/ }).click();
await page.waitForTimeout(1400);
const refusMuet = appels === avantRefus;
console.log('refus sans appel serveur : ' + (refusMuet ? 'oui' : 'NON (' + (appels - avantRefus) + ' appel(s))'));

// ── CONFIRMER APPELLE LE RAPPEL, ET LES DEUX TONS SE DISTINGUENT ─────────────────────────
const sonde = await page.evaluate(async () => {
  const attendre = () => new Promise((r) => setTimeout(r, 450));
  const boite = () => document.querySelector('.brio-modal[role="alertdialog"]');
  const clic = (motif) => [...(boite()?.querySelectorAll('button') ?? [])]
    .find((b) => motif.test((b.textContent || '').trim()))?.click();

  let recu = 0;
  let refuse = 0;

  // Ton doux + acceptation : le rappel `action` doit partir une fois, et une seule.
  window.dispatchEvent(new CustomEvent('brio-confirmer', {
    detail: { message: 'Approuver ?', ton: 'neutre', action: () => { recu += 1; }, instead: () => { refuse += 1; } },
  }));
  await attendre();

  const lire = () => (boite()
    ? { danger: boite().classList.contains('brio-modal-danger'), titre: boite().querySelector('.brio-modal-titre')?.textContent?.trim() }
    : null);

  const doux = lire();
  clic(/Confirmer|Bevestigen|Confirm/);
  await attendre();

  // Ton danger + refus : c'est `instead` qui doit partir, pas `action`.
  window.dispatchEvent(new CustomEvent('brio-confirmer', {
    detail: { message: 'Supprimer ?', ton: 'danger', action: () => { recu += 1; }, instead: () => { refuse += 1; } },
  }));
  await attendre();

  const dur = lire();
  clic(/Annuler|Annuleren|Cancel/);
  await attendre();

  return { recu, refuse, doux, dur };
});

console.log('rappel `action` appelé : ' + sonde.recu + ' fois (attendu 1)');
console.log('rappel `instead` appelé : ' + sonde.refuse + ' fois (attendu 1)');
console.log('ton doux   : ' + JSON.stringify(sonde.doux));
console.log('ton danger : ' + JSON.stringify(sonde.dur));

const tonsDistincts = Boolean(sonde.doux && sonde.dur
  && sonde.doux.danger === false && sonde.dur.danger === true
  && sonde.doux.titre !== sonde.dur.titre);

console.log('les deux tons se distinguent : ' + (tonsDistincts ? 'oui' : 'NON'));

const tenu = ouverte
  && boitesNatives === 0
  && texte.trim() === message.trim()
  && dangerParDefaut
  && refusMuet
  && sonde.recu === 1
  && sonde.refuse === 1
  && tonsDistincts;

console.log(tenu ? 'OK — les quatre points tiennent.' : 'ÉCHEC');

await nav.close();
process.exit(tenu ? 0 : 1);
