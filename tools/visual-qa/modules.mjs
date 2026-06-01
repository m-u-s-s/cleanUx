// tools/visual-qa/modules.mjs
// Inventaire des modules embed + comptes QA, dérivé des sources EXISTANTES
// (storage/app/parity_webview.json + le mapping de scripts/embed_sweep.php).
import { readFileSync } from 'node:fs';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { dirname, resolve } from 'node:path';

const __dirname = dirname(fileURLToPath(import.meta.url));

export const QA_PASSWORD = 'QaPhase2!';

// Même mapping que scripts/embed_sweep.php ($creds).
export const CREDENTIALS = {
  admin: 'admin@cleanux.test',
  provider_company: 'qa-provider-company@cleanux.test',
  entreprise: 'dominique.monnier@example.org',
  provider: 'bsanchez@example.org',
  client: 'lemoine.gabrielle@example.net',
};

// Priorité identique à credKeyForRoles() dans embed_sweep.php.
const CRED_PRIORITY = ['admin', 'provider_company', 'entreprise', 'provider', 'client'];

export function credKeyForRoles(roles = []) {
  for (const k of CRED_PRIORITY) {
    if (roles.includes(k)) return k;
  }
  return null; // public → pas de login
}

// Pages qui exigent MySQL (500 sous SQLite harness) — hors périmètre headless.
// (Source : docs/runbooks/EMBED-VISUAL-QA.md, section "deferred".)
export const DEFERRED_KEYS = new Set([
  'dashboard-client-analytics',
  'dashboard-entreprise-client',
  'dashboard-entreprise-client-membres',
  'dashboard-entreprise-client-facturation',
  'dashboard-entreprise-prestataire-canaux',
  'dashboard-entreprise-prestataire-dispatch',
  'dashboard-entreprise-prestataire-equipe',
]);

export function loadModules() {
  const path = resolve(__dirname, '../../storage/app/parity_webview.json');
  const raw = JSON.parse(readFileSync(path, 'utf8'));
  return raw.map((m) => ({
    key: m.key,
    path: m.path,
    roles: m.roles ?? [],
    credKey: credKeyForRoles(m.roles ?? []),
    deferred: DEFERRED_KEYS.has(m.key),
  }));
}

// Exécution directe : liste les modules (smoke).
// (pathToFileURL pour matcher correctement le chemin sur Windows ET POSIX.)
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  const mods = loadModules();
  const byCred = {};
  for (const m of mods) (byCred[m.credKey ?? 'public'] ??= []).push(m.key);
  console.log(`Total modules: ${mods.length}, deferred: ${mods.filter((m) => m.deferred).length}`);
  for (const [k, list] of Object.entries(byCred)) console.log(`  ${k}: ${list.length}`);
}
