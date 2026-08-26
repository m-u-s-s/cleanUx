/**
 * LE TÉMOIN DU CRITÈRE C6 — il vérifie le garde-fou, pas les pages.
 *
 * C6 demande qu'une page rende un fond SOMBRE quand le thème sombre est actif. Sa
 * première écriture lisait la couleur calculée avec une expression régulière sur les
 * nombres de la chaîne. Cela marche pour `rgb(10, 14, 26)` et pour rien d'autre : le
 * navigateur rend `color(srgb 0.92 0.92 0.92)` pour un `color-mix` et
 * `oklch(0.98 0.01 250)` pour un oklch — des composantes en 0–1, lues comme du 0–255.
 *
 * Un fond PRESQUE BLANC se mesurait alors 0,0003 et passait le seuil. Le garde-fou était
 * aveugle à la classe de fond qu'il existe pour attraper, et le système emploie
 * `color-mix` à vingt-neuf endroits : la cécité n'attendait qu'une ligne de CSS.
 *
 * Un critère qui passe toujours ne protège de rien. Ce fichier lui pose donc les deux
 * questions : est-ce qu'il ATTRAPE les fonds clairs, et est-ce qu'il LAISSE PASSER les
 * fonds sombres. Sans le second, un C6 cassé qui échoue partout aurait l'air d'un
 * gardien zélé.
 *
 * Il importe la fonction RÉELLE du harnais. En recopier une jumelle ici mesurerait la
 * copie, et les deux dériveraient sans que rien ne le dise.
 *
 *   node tools/visual-qa/temoin-c6.mjs
 */
import { chromium } from 'playwright';
import { fondSuitLeTheme } from './check.mjs';

// [fond du body, ce qu'on décrit, le critère doit-il PASSER]
const CAS = [
  ['#f8fafc', 'clair — le défaut d’origine', false],
  ['rgb(248, 250, 252)', 'clair écrit en rgb', false],
  ['color-mix(in srgb, #ffffff 92%, #000)', 'clair écrit en color-mix', false],
  ['oklch(0.98 0.01 250)', 'clair écrit en oklch', false],
  ['#0a0e1a', 'la nuit du thème', true],
  ['color-mix(in srgb, #0a0e1a 92%, #000)', 'nuit écrite en color-mix', true],
  ['oklch(0.16 0.02 260)', 'nuit écrite en oklch', true],
  // Du verre sombre : blanc à 5,5 % POSÉ SUR la nuit. Le lire sans composer le
  // déclarerait blanc à 100 %.
  ['rgba(255, 255, 255, 0.055)', 'verre sombre sur la nuit', true],
  // Un body transparent laisse voir `<html>`, que le thème peint.
  ['transparent', 'body transparent, html sombre', true],
];

const nav = await chromium.launch();
const page = await nav.newPage();
let rates = 0;

for (const [fond, nom, attendu] of CAS) {
  await page.setContent(`<style>html{background:#0a0e1a}body{background:${fond}}</style><p>x</p>`);

  const obtenu = (await fondSuitLeTheme(page)).ok;

  if (obtenu !== attendu) rates++;

  console.log(
    `  ${obtenu === attendu ? 'OK  ' : 'RATÉ'}  ${nom.padEnd(30)}` +
    ` attendu ${attendu ? 'PASSE ' : 'ÉCHOUE'}, obtenu ${obtenu ? 'PASSE' : 'ÉCHOUE'}`,
  );
}

await nav.close();

console.log(`\n  ${CAS.length - rates}/${CAS.length}`);
process.exit(rates ? 1 : 0);
