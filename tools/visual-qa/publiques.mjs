// tools/visual-qa/publiques.mjs
//
// Les pages PUBLIQUES en vue mobile — celles qu'un visiteur voit avant d'avoir un compte.
// `run.mjs` couvre 120 pages authentifiées et UNE seule page publique : l'accueil, le catalogue
// et le parcours de commande n'étaient mesurés nulle part.

import { chromium } from 'playwright';
import { mkdirSync, writeFileSync } from 'node:fs';
import { checkModule } from './check.mjs';

const BASE = process.env.VQA_BASE ?? 'http://127.0.0.1:8000';

/** Ce qu'un visiteur atteint sans compte, dans l'ordre où il le rencontre. */
export const PAGES_PUBLIQUES = [
  { key: 'accueil', path: '/' },
  { key: 'catalogue', path: '/services' },
  { key: 'commander', path: '/commander' },
  { key: 'tarifs', path: '/pricing' },
  { key: 'location', path: '/location' },
  { key: 'blog', path: '/blog' },
  { key: 'aide', path: '/aide' },
  { key: 'connexion', path: '/login' },
  { key: 'inscription', path: '/register' },
  { key: 'mot-de-passe-oublie', path: '/forgot-password' },
  { key: 'mentions-legales', path: '/legal/mentions-legales' },
  { key: 'cookies', path: '/legal/cookies' },
];

async function balayer() {
  const navigateur = await chromium.launch();
  const contexte = await navigateur.newContext({ deviceScaleFactor: 2 });
  const lignes = [];

  for (const page of PAGES_PUBLIQUES) {
    const r = await checkModule(contexte, BASE, page);
    lignes.push(r);
    console.log(`${r.pass ? 'PASS' : 'FAIL'}  ${r.key.padEnd(22)} ${r.path}`);
  }

  await navigateur.close();

  mkdirSync('out', { recursive: true });
  const reussies = lignes.filter((l) => l.pass).length;

  let md = '# Pages publiques — vue mobile 390×844\n\n';
  md += `${reussies}/${lignes.length} pages PASS.\n\n`;
  md += '| Page | Chemin | HTTP | C1 | C2 | C3 | C4 | C5 | Pass |\n|---|---|---|---|---|---|---|---|---|\n';

  for (const l of lignes) {
    const c = (k) => (l.criteria?.[k] === undefined ? '–' : l.criteria[k] ? '✅' : '❌');
    md += `| ${l.key} | ${l.path} | ${l.http} | ${c('c1')} | ${c('c2')} | ${c('c3')} | ${c('c4')} | ${c('c5')} | ${l.pass ? '✅' : '❌'} |\n`;
  }

  const fautives = lignes.filter((l) => !l.pass);
  if (fautives.length) {
    md += '\n## Ce qui cloche\n\n';
    for (const l of fautives) {
      md += `### ${l.key} (${l.path})\n\n`;
      if (l.error) md += `Erreur : ${l.error}\n\n`;
      for (const [k, v] of Object.entries(l.offenders ?? {})) {
        if (Array.isArray(v) && v.length) {
          md += `- **${k}** : ${v.slice(0, 6).map((x) => JSON.stringify(x)).join(', ')}\n`;
        }
      }
      md += '\n';
    }
  }

  writeFileSync('out/publiques.md', md);
  console.log(`\n${reussies}/${lignes.length} PASS → out/publiques.md`);
  process.exitCode = reussies === lignes.length ? 0 : 1;
}

balayer();
