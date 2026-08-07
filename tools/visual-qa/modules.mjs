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
  admin: 'admin@brio.test',
  provider_company: 'qa-provider-company@brio.test',
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
  // ── Un-deferred 2026-06-01 (SP4 migrations on MySQL dev DB + org context on the
  //    QA accounts): these render 200 + PASS the 5 mobile criteria at 390px and are
  //    now covered by the harness:
  //      dashboard-entreprise-client, dashboard-entreprise-client-membres,
  //      dashboard-entreprise-client-facturation, dashboard-entreprise-prestataire-canaux,
  //      dashboard-entreprise-prestataire-dispatch
  //
  // ── Un-deferred 2026-06-01 (mobile layout fixes shipped on fix/visual-qa-119):
  //      dashboard-client-analytics      → header controls (period select, custom-date
  //        inputs, apply button, CSV export links) given min-h-[44px] / inline-flex
  //        items-center so they clear the 44px tap-target floor (C2).
  //      dashboard-entreprise-prestataire-equipe → TeamManagement members <table>
  //        wrapped in <div class="overflow-x-auto"> + min-w-[640px] so it scrolls inside
  //        the 390px viewport instead of overflowing (right:636) and clipping (C3+C4).
  // Doublon : /admin/users est un alias 302 → /admin/utilisateurs (routes/admin.php).
  // Le redirect perd le query `?embed=1`, donc la cible rend HORS embed (nav chrome
  // présent → C5 faux échec). La VRAIE page est 'admin-utilisateurs' (déjà couverte).
  // Ce n'est pas un bug de layout : on dédoublonne au lieu de "corriger" un alias.
  'admin-users',
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
