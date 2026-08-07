# Polish & Visual QA — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Livrer (Lot 2) un harness de visual-QA automatisé Playwright qui balaye les ~115 pages embarquées contre 5 critères mobiles + corrige les FAIL, puis (Lot 1) une refonte premium des surfaces SP2/SP3/SP4 ancrée sur le design system existant.

**Architecture :** Lot 2 d'abord — un package Node isolé `tools/visual-qa/` (Playwright headless) qui lit l'inventaire de modules existant (`storage/app/parity_webview.json`), se logge par rôle (comptes QA `QaPhase2!`), charge chaque `<path>?embed=1` à 390×844 et évalue 5 critères ; produit un rapport ; puis on corrige les FAIL à la source. Lot 1 ensuite — refonte visuelle par contexte (client/admin clair `cu-*`, prestataire slate sombre) en réutilisant `cu-*`/`ui-*`/mobile `theme`+`ui`, sans changer la logique métier.

**Tech Stack :** Playwright (`@playwright/test`) + Chromium ; Laravel 10 + Livewire 3 + Tailwind (`cu-*`/`ui-*`) ; Expo/RN (`mobile/shared/src/theme` + `ui`). PHPUnit, PHPStan full, Pint.

**Faits terrain vérifiés (à NE PAS re-supposer) :**
- Inventaire des modules : `storage/app/parity_webview.json` (array de `{ key, path, roles }`). Source unique consommée par `scripts/embed_sweep.php` — la RÉUTILISER.
- Comptes QA (mot de passe commun `QaPhase2!`) : `admin`→`admin@brio.test` ; `provider_company`→`qa-provider-company@brio.test` ; `entreprise`→`dominique.monnier@example.org` ; `provider`→`bsanchez@example.org` ; `client`→`lemoine.gabrielle@example.net`. Mapping rôle→compte = `credKeyForRoles()` dans `embed_sweep.php` (priorité admin > provider_company > entreprise > provider > client ; `roles=[]` → public, pas de login).
- Login Fortify : `GET /login` rend un form avec `name="_token"` (CSRF) ; `POST /login` avec `_token,email,password` → 302 vers dashboard si OK, retour `/login` si échec. **En Playwright, on remplit le form et on submit** (le navigateur gère le cookie CSRF) — pas besoin d'extraire le token à la main.
- Embed mode : `app/Http/Middleware/EmbedMode.php` — `?embed=1` masque la nav ; le marqueur `[data-chrome="primary-nav"]` est ABSENT du DOM en embed.
- Flag de vérif : `config/parity.php` contient `responsive_verified` par module (géré par `app/Console/Commands/ParityScaffoldRegistry.php`).
- Composants Blade réels sous `resources/views/components/ui/` : `card`, `button`, `badge`, `page-header`, `empty-state`, `table-shell`, `field`, `stat`, `section-heading`, `toast`, `skeleton`, `icon`. **PAS de `input.blade.php`** → pour les inputs, utiliser `<x-ui.field>` (lire son API) + la classe CSS `.ui-input`/`.ui-label`/`.ui-error-msg`.
- CSS design system : `resources/css/tokens.css`, `resources/css/tool-mode.css` (`cu-hero`/`cu-card`/`cu-kpi`/`cu-page-header`/`cu-btn-*`/`cu-empty`/`cu-table`/`cu-status-dot-*`), `resources/css/app.css` (classes `.ui-*`).
- Pattern filtres : `app/Livewire/Client/BrowseProviders.php` (props `#[Url]` `query` debounce 400ms / `minRating` [null,3,4,4.5] / `sort` ; `updating($name)`→`resetPage()` ; `resetFilters()` ; `selectionMode` + `selectProvider()`→`dispatch('providerSelected')`) + `resources/views/livewire/client/browse-providers.blade.php` (sidebar `lg:col-span-1` + résultats `lg:col-span-3`).
- `app/Livewire/Client/BrowseCompanies.php` : a déjà `selectionMode`, `selectCompany()`→`dispatch('companySelected')`, `getCompaniesProperty()` (via `EligibleCompaniesResolver::forContext` ou fallback `OrganizationAccount` PROVIDER_COMPANY) + props contexte `serviceZoneId`/`tradeId`. Sérialisation société : `{id,name,rating_avg,rating_count,providers_count}` (cf. `CompanyDirectoryController`).
- `app/Livewire/ClientCompany/ClientContractsCenter.php` : computed `getContractsProperty()` (contrats de l'org du membre, eager `providerOrganization`/`rateCards`/`workOrders` + `withCount` SLA breached) ; layout `->layout('layouts.client-company')`.
- Mobile : `mobile/shared/src/ui` (Button/Badge/Screen/TextInput/Avatar/Skeleton/EmptyState/ErrorState/Divider/ProgressBar/StatCard…), `mobile/shared/src/theme` (colors/typography/spacing/radius/shadows). `BookingDetailScreen.tsx` a un `DetailRow` custom intra-fichier ; badge `contract_covered` en `Badge variant="info"`. `BookingStepProvider.tsx` a des `Pressable` bruts (type selector + favoris).

**Conventions de gates (chaque tâche) :** TDD quand il y a une logique testable (filtres, drill-down). Polish purement visuel → test = « la vue rend sans erreur » + non-régression des tests existants de la surface. À la fin de chaque tâche : `php artisan test --filter=<ciblé>` vert + `vendor/bin/pint <fichiers>`. Suite complète + PHPStan full + mobile + re-run harness = **Task 12**. **Jamais `git add -A`** (Expo `mobile/.expo/*` + un `scripts/*.ps1` non-trackés). Commits finissent par `Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>`.

---

## LOT 2 — Harness de visual QA (construit en premier)

### Task 1: Scaffolder `tools/visual-qa/` (Playwright + inventaire des modules)

**Files:**
- Create: `tools/visual-qa/package.json`
- Create: `tools/visual-qa/.gitignore`
- Create: `tools/visual-qa/modules.mjs`
- Create: `tools/visual-qa/README.md`

- [ ] **Step 1: Créer le package isolé**

`tools/visual-qa/package.json` :

```json
{
  "name": "brio-visual-qa",
  "private": true,
  "type": "module",
  "version": "1.0.0",
  "description": "Headless mobile-viewport visual QA sweep for embedded WebView pages.",
  "scripts": {
    "qa": "node run.mjs",
    "modules": "node modules.mjs"
  },
  "devDependencies": {
    "playwright": "^1.48.0"
  }
}
```

`tools/visual-qa/.gitignore` :

```
node_modules/
out/
```

- [ ] **Step 2: Installer Playwright + Chromium**

```bash
cd tools/visual-qa && npm install && npx playwright install chromium
```

Expected: `playwright` installé, navigateur Chromium téléchargé. (Si l'install Chromium échoue en sandbox, le signaler — le harness reste utilisable là où Chromium est dispo.)

- [ ] **Step 3: `modules.mjs` — lire l'inventaire existant + mapping rôle→compte**

```js
// tools/visual-qa/modules.mjs
// Inventaire des modules embed + comptes QA, dérivé des sources EXISTANTES
// (storage/app/parity_webview.json + le mapping de scripts/embed_sweep.php).
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
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
if (import.meta.url === `file://${process.argv[1]}`) {
  const mods = loadModules();
  const byCred = {};
  for (const m of mods) (byCred[m.credKey ?? 'public'] ??= []).push(m.key);
  console.log(`Total modules: ${mods.length}, deferred: ${mods.filter((m) => m.deferred).length}`);
  for (const [k, list] of Object.entries(byCred)) console.log(`  ${k}: ${list.length}`);
}
```

- [ ] **Step 4: README court**

`tools/visual-qa/README.md` :

```markdown
# Visual QA harness (mobile embed pages)

Headless Playwright sweep of every embedded WebView page at 390×844, checking 5 mobile criteria.

## Prerequisites
- A running Laravel server reachable at `VQA_BASE` (default `http://127.0.0.1:8000`):
  `php artisan serve` from the repo root (dev DB seeded with the QA accounts).
- `npm install && npx playwright install chromium` in this folder.

## Run
```
VQA_BASE=http://127.0.0.1:8000 npm run qa
```
Writes `out/report.json` + `out/report.md`.

## Criteria (per page, 390px viewport)
1. No horizontal scroll · 2. Tap targets ≥44px (primary controls) · 3. Readable text (no clip) ·
4. No broken layout (no right-overflow) · 5. Nav chrome absent (`[data-chrome="primary-nav"]`).

`VQA_TOLERANCE` (default 2px) softens 1/3/4. 7 MySQL-only pages are skipped (see modules.mjs DEFERRED_KEYS).
```

- [ ] **Step 5: Smoke + commit**

```bash
cd tools/visual-qa && node modules.mjs
```
Expected: affiche le total des modules (~118) + répartition par rôle.

```bash
git add tools/visual-qa/package.json tools/visual-qa/.gitignore tools/visual-qa/modules.mjs tools/visual-qa/README.md
git commit -m "feat(visual-qa): scaffold isolated Playwright harness + module inventory (reuses parity_webview.json)"
```

(NE PAS committer `node_modules/` ni `out/` — couverts par `.gitignore`. NE PAS `git add -A`.)

---

### Task 2: `check.mjs` — login par rôle + 5 critères à 390px

**Files:**
- Create: `tools/visual-qa/check.mjs`

- [ ] **Step 1: Implémenter le login + l'évaluation des critères**

```js
// tools/visual-qa/check.mjs
import { CREDENTIALS, QA_PASSWORD } from './modules.mjs';

const TOL = Number(process.env.VQA_TOLERANCE ?? 2);
const VIEWPORT = { width: 390, height: 844 };

/** Connexion via le form Fortify (le navigateur gère le CSRF). */
export async function loginAs(context, base, credKey) {
  if (!credKey) return; // public
  const email = CREDENTIALS[credKey];
  const page = await context.newPage();
  await page.goto(`${base}/login`, { waitUntil: 'networkidle' });
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', QA_PASSWORD);
  await Promise.all([
    page.waitForLoadState('networkidle'),
    page.click('button[type="submit"], input[type="submit"]'),
  ]);
  const url = page.url();
  await page.close();
  if (url.includes('/login')) {
    throw new Error(`login failed for ${credKey} (${email}) — still on /login`);
  }
}

/** Évalue les 5 critères dans la page. Retourne { c1..c5, offenders }. */
const EVAL = (tol) => {
  const T = tol;
  const out = { criteria: {}, offenders: {} };
  const docEl = document.documentElement;

  // C1 — pas de scroll horizontal au niveau document.
  out.criteria.c1_no_h_scroll = docEl.scrollWidth <= docEl.clientWidth + T;

  // C5 — nav chrome absent en embed.
  out.criteria.c5_nav_chrome_absent = !document.querySelector('[data-chrome="primary-nav"]');

  const vw = docEl.clientWidth;
  const visible = (el) => {
    const s = getComputedStyle(el);
    if (s.display === 'none' || s.visibility === 'hidden' || s.opacity === '0') return false;
    const r = el.getBoundingClientRect();
    return r.width > 0 && r.height > 0;
  };
  const inScrollable = (el) => {
    // ignore les éléments dans un conteneur à scroll horizontal intentionnel.
    let p = el.parentElement;
    while (p) {
      const ox = getComputedStyle(p).overflowX;
      if (ox === 'auto' || ox === 'scroll') return true;
      p = p.parentElement;
    }
    return false;
  };

  // C2 — tap targets : seulement les CONTRÔLES primaires (boutons, liens-boutons),
  // pas les liens texte inline (sinon faux positifs massifs).
  const controls = [...document.querySelectorAll(
    'button, [role="button"], input[type="submit"], input[type="button"], a.btn, .ui-btn, .cu-btn-primary, .cu-btn-secondary, .cu-btn-danger'
  )].filter(visible);
  const smallTargets = controls
    .filter((el) => { const r = el.getBoundingClientRect(); return r.width < 44 || r.height < 44; })
    .map((el) => ({ tag: el.tagName.toLowerCase(), text: (el.textContent || '').trim().slice(0, 40),
                    w: Math.round(el.getBoundingClientRect().width), h: Math.round(el.getBoundingClientRect().height) }));
  out.criteria.c2_tap_targets = smallTargets.length === 0;
  out.offenders.c2 = smallTargets.slice(0, 10);

  // C3 — texte lisible : aucun élément avec clip horizontal (scrollWidth>clientWidth).
  const clipped = [...document.querySelectorAll('p, span, h1, h2, h3, h4, a, button, td, th, li, label')]
    .filter(visible)
    .filter((el) => el.scrollWidth > el.clientWidth + T && !inScrollable(el))
    .map((el) => ({ tag: el.tagName.toLowerCase(), text: (el.textContent || '').trim().slice(0, 40) }));
  out.criteria.c3_readable_text = clipped.length === 0;
  out.offenders.c3 = clipped.slice(0, 10);

  // C4 — layout non cassé : aucun élément débordant à droite du viewport.
  const overflow = [...document.querySelectorAll('body *')]
    .filter(visible)
    .filter((el) => { const s = getComputedStyle(el); return s.position !== 'fixed' && s.position !== 'absolute'; })
    .filter((el) => !inScrollable(el))
    .filter((el) => el.getBoundingClientRect().right > vw + T)
    .map((el) => ({ tag: el.tagName.toLowerCase(), cls: (el.className || '').toString().slice(0, 50),
                    right: Math.round(el.getBoundingClientRect().right) }));
  out.criteria.c4_no_broken_layout = overflow.length === 0;
  out.offenders.c4 = overflow.slice(0, 10);

  return out;
};

export async function checkModule(context, base, mod) {
  const page = await context.newPage();
  await page.setViewportSize(VIEWPORT);
  let httpStatus = 0;
  try {
    const resp = await page.goto(`${base}${mod.path}?embed=1`, { waitUntil: 'networkidle', timeout: 30000 });
    httpStatus = resp ? resp.status() : 0;
    // laisser Livewire/JS poser le layout
    await page.waitForTimeout(400);
    const result = await page.evaluate(EVAL, TOL);
    await page.close();
    const pass = Object.values(result.criteria).every(Boolean);
    return { key: mod.key, path: mod.path, role: mod.credKey ?? 'public', http: httpStatus, pass, ...result };
  } catch (e) {
    await page.close();
    return { key: mod.key, path: mod.path, role: mod.credKey ?? 'public', http: httpStatus, pass: false,
             criteria: {}, offenders: {}, error: String(e).slice(0, 200) };
  }
}
```

- [ ] **Step 2: Smoke isolé (sans serveur, juste parse)**

```bash
cd tools/visual-qa && node -e "import('./check.mjs').then(m=>console.log(Object.keys(m)))"
```
Expected: `[ 'loginAs', 'checkModule' ]` (le module charge sans erreur de syntaxe).

- [ ] **Step 3: Commit**

```bash
git add tools/visual-qa/check.mjs
git commit -m "feat(visual-qa): per-role login + 5 mobile criteria evaluator (390px, signal-tuned)"
```

---

### Task 3: `report.mjs` + `run.mjs` — orchestration + rapport, et premier run

**Files:**
- Create: `tools/visual-qa/report.mjs`
- Create: `tools/visual-qa/run.mjs`

- [ ] **Step 1: `report.mjs`**

```js
// tools/visual-qa/report.mjs
import { mkdirSync, writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';

const __dirname = dirname(fileURLToPath(import.meta.url));
const C = ['c1_no_h_scroll', 'c2_tap_targets', 'c3_readable_text', 'c4_no_broken_layout', 'c5_nav_chrome_absent'];
const LABEL = { c1_no_h_scroll: 'C1', c2_tap_targets: 'C2', c3_readable_text: 'C3', c4_no_broken_layout: 'C4', c5_nav_chrome_absent: 'C5' };

export function writeReport(results) {
  const outDir = resolve(__dirname, 'out');
  mkdirSync(outDir, { recursive: true });
  writeFileSync(resolve(outDir, 'report.json'), JSON.stringify(results, null, 2));

  const passed = results.filter((r) => r.pass).length;
  const failed = results.filter((r) => !r.pass);
  let md = `# Visual QA report\n\n${passed}/${results.length} pages PASS.\n\n`;
  md += `| Page | Role | HTTP | ${C.map((c) => LABEL[c]).join(' | ')} | Pass |\n`;
  md += `|---|---|---|${C.map(() => '---').join('|')}|---|\n`;
  for (const r of results.sort((a, b) => `${a.role}${a.key}`.localeCompare(`${b.role}${b.key}`))) {
    const cells = C.map((c) => (r.criteria?.[c] === undefined ? '–' : r.criteria[c] ? '✓' : '✗'));
    md += `| ${r.key} | ${r.role} | ${r.http} | ${cells.join(' | ')} | ${r.pass ? '✅' : '❌'} |\n`;
  }
  if (failed.length) {
    md += `\n## Failures detail\n\n`;
    for (const r of failed) {
      md += `### ${r.key} (${r.role}) — HTTP ${r.http}\n`;
      if (r.error) md += `- error: ${r.error}\n`;
      for (const c of ['c2', 'c3', 'c4']) {
        if (r.offenders?.[c]?.length) md += `- ${c}: ${JSON.stringify(r.offenders[c])}\n`;
      }
      md += '\n';
    }
  }
  writeFileSync(resolve(outDir, 'report.md'), md);
  return { passed, total: results.length, failed: failed.length };
}
```

- [ ] **Step 2: `run.mjs`**

```js
// tools/visual-qa/run.mjs
import { chromium } from 'playwright';
import { loadModules } from './modules.mjs';
import { loginAs, checkModule } from './check.mjs';
import { writeReport } from './report.mjs';

const BASE = process.env.VQA_BASE ?? 'http://127.0.0.1:8000';

const run = async () => {
  const mods = loadModules().filter((m) => !m.deferred); // 7 deferred MySQL exclus
  const byCred = {};
  for (const m of mods) (byCred[m.credKey ?? 'public'] ??= []).push(m);

  const browser = await chromium.launch();
  const results = [];
  for (const [credKey, group] of Object.entries(byCred)) {
    const context = await browser.newContext({ viewport: { width: 390, height: 844 } });
    try {
      await loginAs(context, BASE, credKey === 'public' ? null : credKey);
    } catch (e) {
      console.error(`[login ${credKey}] ${e.message}`);
      for (const m of group) results.push({ key: m.key, path: m.path, role: credKey, http: 0, pass: false, criteria: {}, offenders: {}, error: `login failed: ${e.message}` });
      await context.close();
      continue;
    }
    for (const m of group) {
      const r = await checkModule(context, BASE, m);
      results.push(r);
      console.log(`${r.pass ? 'PASS' : 'FAIL'}  ${r.role.padEnd(16)} ${r.key}`);
    }
    await context.close();
  }
  await browser.close();

  const summary = writeReport(results);
  console.log(`\n${summary.passed}/${summary.total} PASS, ${summary.failed} FAIL → out/report.md`);
  process.exit(summary.failed > 0 ? 1 : 0);
};

run();
```

- [ ] **Step 3: Lancer le serveur + premier run**

Dans un terminal séparé : `php artisan serve` (DB de dev seedée avec les comptes QA). Vérifie d'abord que les comptes existent : `php artisan tinker --execute="echo \App\Models\User::where('email','admin@brio.test')->exists() ? 'OK' : 'MISSING';"`. **Si MISSING**, identifie/lance le seeder QA (cherche un seeder qui crée ces comptes — grep `admin@brio.test` dans `database/seeders/`) et documente la commande. Puis :

```bash
cd tools/visual-qa && VQA_BASE=http://127.0.0.1:8000 npm run qa
```
Expected : un rapport `out/report.md` avec la matrice. **Ce premier rapport est la baseline** : note le nombre de PASS/FAIL et les familles d'échecs.

- [ ] **Step 4: Tuner pour éliminer les faux positifs**

Lis `out/report.md`. Si des critères produisent des faux positifs systématiques (ex. C2 sur des contrôles légitimement petits, C4 sur des conteneurs `overflow` non détectés), affine `check.mjs` (élargir la détection `inScrollable`, ajouter une whitelist de sélecteurs C2, ajuster `VQA_TOLERANCE`) pour que le rapport soit **signal-riche** (un FAIL = un vrai problème mobile). Re-run. Documente les seuils retenus dans le README.

- [ ] **Step 5: Commit (harness + baseline)**

```bash
git add tools/visual-qa/report.mjs tools/visual-qa/run.mjs tools/visual-qa/README.md
git commit -m "feat(visual-qa): orchestrator + report writer; tuned thresholds; baseline sweep captured"
```
(NE PAS committer `out/`.)

---

### Task 4: Corriger les FAIL du parc (itératif) + basculer `responsive_verified`

**Files:**
- Modify: les vues/CSS Blade des pages en FAIL (variable selon le rapport)
- Modify: `config/parity.php` (flags `responsive_verified`)

- [ ] **Step 1: Lister les FAIL réels**

Depuis `out/report.md` (baseline Task 3), extrais la liste des pages en FAIL (hors 7 deferred) avec le critère fautif + les `offenders`. Regroupe par cause commune (ex. un même composant partagé qui déborde sur plusieurs pages → un seul fix).

- [ ] **Step 2: Corriger à la source, page/famille par page/famille**

Pour chaque FAIL : ouvre la vue concernée, identifie l'élément fautif (via `offenders`), corrige en Tailwind/Blade **au minimum nécessaire** (ex. `min-w-0`/`truncate` pour un texte qui clippe ; `flex-wrap`/`overflow-x-auto` sur une table ; `w-full max-w-full` ; tailles de boutons `min-h-[44px]`). NE refonds PAS la page (c'est le Lot 1 pour les surfaces SP2/SP3/SP4 ; ici = correction ciblée). Re-run le harness après chaque famille de fixes : `cd tools/visual-qa && npm run qa` → le(s) page(s) ciblée(s) passent.

- [ ] **Step 3: Basculer `responsive_verified`**

Pour chaque module désormais vert, mets `responsive_verified => true` dans `config/parity.php` (lis la structure existante du fichier + `ParityScaffoldRegistry.php` pour le format exact). Si un test PHPUnit vérifie ce flag, lance-le.

- [ ] **Step 4: Re-run final + commit**

```bash
cd tools/visual-qa && npm run qa   # 0 FAIL hors deferred attendu
```

```bash
git add <vues corrigées> config/parity.php
git commit -m "fix(visual-qa): resolve mobile-viewport failures across embed pages; flip responsive_verified"
```

Rapporte : nombre de pages corrigées, familles de causes, pages restant en deferred (les 7 MySQL).

---

## LOT 1 — Refonte premium des surfaces (après le harness)

> Chaque tâche de refonte commence par **LIRE** : (a) la surface réelle (vue + composant Livewire/écran), (b) le composant design-system de référence cité, (c) une page « TRÈS-POLI » du même contexte. Puis adapter — **pas de markup inventé sans avoir lu l'existant**.

### Task 5: `BrowseCompanies` — filtres + grille premium

**Files:**
- Modify: `app/Livewire/Client/BrowseCompanies.php`
- Modify: `resources/views/livewire/client/browse-companies.blade.php`
- Test: `tests/Feature/Relations/BrowseCompaniesFilterTest.php`

- [ ] **Step 1: LIRE** `app/Livewire/Client/BrowseProviders.php` (props `#[Url]`, `updating()`, `resetFilters()`, `selectionMode`) + sa vue (sidebar+grille) ; `app/Livewire/Client/BrowseCompanies.php` actuel (computed `getCompaniesProperty`, `selectCompany`, contexte zone/trade) + sa vue.

- [ ] **Step 2: Test des filtres (échoue)**

```php
<?php

namespace Tests\Feature\Relations;

use App\Livewire\Client\BrowseCompanies;
use App\Models\OrganizationAccount;
use App\Enums\OrganizationType;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrowseCompaniesFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_query_filters_companies_by_name_and_sort_orders_by_rating(): void
    {
        OrganizationAccount::factory()->create(['type' => OrganizationType::PROVIDER_COMPANY->value, 'name' => 'Alpha Clean', 'rating_avg' => 4.2]);
        OrganizationAccount::factory()->create(['type' => OrganizationType::PROVIDER_COMPANY->value, 'name' => 'Beta Services', 'rating_avg' => 4.9]);

        // Sans contexte zone → fallback simple (toutes les PROVIDER_COMPANY notées).
        Livewire::test(BrowseCompanies::class)
            ->set('query', 'alpha')
            ->assertSee('Alpha Clean')
            ->assertDontSee('Beta Services');

        Livewire::test(BrowseCompanies::class)
            ->set('sort', 'rating')
            ->assertSeeInOrder(['Beta Services', 'Alpha Clean']); // 4.9 avant 4.2
    }

    public function test_selection_event_still_dispatched(): void
    {
        $org = OrganizationAccount::factory()->create(['type' => OrganizationType::PROVIDER_COMPANY->value, 'rating_avg' => 4.0]);
        Livewire::test(BrowseCompanies::class, ['selectionMode' => true])
            ->call('selectCompany', $org->id)
            ->assertDispatched('companySelected', organizationId: $org->id);
    }

    public function test_reset_filters_clears_query(): void
    {
        Livewire::test(BrowseCompanies::class)
            ->set('query', 'xyz')
            ->call('resetFilters')
            ->assertSet('query', '');
    }
}
```

Run: `php artisan test --filter=BrowseCompaniesFilterTest` → FAIL (props absentes).

- [ ] **Step 3: Implémenter les filtres (composant)**

Dans `BrowseCompanies.php`, calque le style de `BrowseProviders` :

```php
use Livewire\Attributes\Url;

#[Url(as: 'q')]
public string $query = '';

#[Url(as: 'rating')]
public ?float $minRating = null;

#[Url(as: 'sort')]
public string $sort = 'rating'; // rating | providers | name

public function updating($name): void
{
    if (in_array($name, ['query', 'minRating', 'sort'], true) && method_exists($this, 'resetPage')) {
        $this->resetPage();
    }
}

public function resetFilters(): void
{
    $this->reset(['query', 'minRating']);
    $this->sort = 'rating';
}
```

Dans `getCompaniesProperty()` (lis l'existant), applique le filtre/tri sur la collection retournée (après `EligibleCompaniesResolver`/fallback) :

```php
return $base
    ->when($this->query !== '', fn ($c) => $c->filter(
        fn ($org) => str_contains(mb_strtolower((string) $org->name), mb_strtolower($this->query))
    ))
    ->when($this->minRating !== null, fn ($c) => $c->filter(
        fn ($org) => (float) ($org->rating_avg ?? 0) >= $this->minRating
    ))
    ->sortBy(fn ($org) => match ($this->sort) {
        'name' => mb_strtolower((string) $org->name),
        'providers' => -1 * (int) ($org->providers_count ?? 0),
        default => -1 * (float) ($org->rating_avg ?? 0), // rating desc
    })
    ->values();
```

(Adapte selon que `getCompaniesProperty` retourne une `Collection` Eloquent ou un tableau sérialisé — garde le type cohérent avec la vue.)

- [ ] **Step 4: Refonte de la vue (grille `cu-card` + sidebar filtres)**

Réécris `browse-companies.blade.php` en réutilisant le pattern de `browse-providers.blade.php` : une sidebar de filtres (`recherche` debounce 400ms, boutons note `[Tous,3★+,4★+,4.5★+]`, select tri) + une grille de `cu-card` société (avatar initiale, nom, note ★ + count, badge `providers_count`, CTA « Choisir cette société » conditionné par `$selectionMode`) + `<x-ui.empty-state>` quand vide. En mode embed (picker), la sidebar peut être condensée. Garde l'event `companySelected`.

- [ ] **Step 5: PASS + rendu**

```bash
php artisan test --filter='BrowseCompaniesFilter|BrowseCompaniesSelection'
```
Vert (les tests SP3 existants `BrowseCompaniesSelectionTest` restent verts). La vue rend sans erreur.

- [ ] **Step 6: pint + commit**

```bash
vendor/bin/pint app/Livewire/Client/BrowseCompanies.php tests/Feature/Relations/BrowseCompaniesFilterTest.php
git add app/Livewire/Client/BrowseCompanies.php resources/views/livewire/client/browse-companies.blade.php tests/Feature/Relations/BrowseCompaniesFilterTest.php
git commit -m "feat(polish): BrowseCompanies — search/rating/sort filters + premium cu-card grid"
```

---

### Task 6: `provider-selection` — picker + créneaux alternatifs en design-system

**Files:**
- Modify: `resources/views/livewire/client/booking/scheduling/provider-selection.blade.php`

- [ ] **Step 1: LIRE** la vue actuelle (3 paliers + blocs amber `preferredProviderAlternativeSlots` / `preferredCompanyAlternativeSlots`) + une page `cu-*` de référence + le composant `<x-ui.badge>`.

- [ ] **Step 2: Refondre visuellement (sans toucher la logique Livewire)**

Convertis le sélecteur de type (3 boutons) en cards `cu-card`/boutons cohérents ; les deux blocs « créneaux alternatifs » (provider ET company) en un composant visuel design-system commun : un encart `cu-card` avec un titre, le message, et des **chips de créneau** cliquables réutilisant l'action existante (le `wire:click="$set('rdvDate', ...); $set('rdvHeure', ...)"` déjà en place — NE change PAS le mécanisme). Aucune propriété/méthode Livewire nouvelle.

- [ ] **Step 3: Rendu + non-régression**

```bash
php artisan test --filter='PrendreRendezVous|PreferredCompanyAvailability'
```
Vert (les blocs alternatifs marchent toujours — mêmes props `preferred*AlternativeSlots`/`preferred*Message`).

- [ ] **Step 4: pint + commit**

```bash
vendor/bin/pint resources/views/livewire/client/booking/scheduling/provider-selection.blade.php
git add resources/views/livewire/client/booking/scheduling/provider-selection.blade.php
git commit -m "feat(polish): booking provider picker + alternative-slot chips aligned to design system"
```

---

### Task 7: `ClientContractsCenter` — refonte `cu-*` + drill-down lecture

**Files:**
- Modify: `app/Livewire/ClientCompany/ClientContractsCenter.php`
- Modify: `resources/views/livewire/client-company/client-contracts-center.blade.php`
- Test: `tests/Feature/Relations/ClientContractsDrilldownTest.php`

- [ ] **Step 1: LIRE** le composant + la vue actuels + `cu-hero`/`cu-card`/`cu-kpi` (dans `tool-mode.css`) + le test SP4 `ClientContractsCenterTest` (montage org/membre).

- [ ] **Step 2: Test du drill-down + isolation (échoue)**

```php
<?php

namespace Tests\Feature\Relations;

use App\Livewire\ClientCompany\ClientContractsCenter;
use App\Models\OrganizationAccount;
use App\Models\OrganizationContract;
use App\Models\OrganizationMember;
use App\Models\User;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientContractsDrilldownTest extends TestCase
{
    use RefreshDatabase;

    public function test_selecting_a_contract_shows_its_detail_and_respects_org_isolation(): void
    {
        $org = OrganizationAccount::factory()->create();
        $foreign = OrganizationAccount::factory()->create();
        $mine = OrganizationContract::factory()->create([
            'organization_account_id' => $org->id,
            'provider_organization_id' => OrganizationAccount::factory()->create()->id,
            'status' => 'active', 'contract_reference' => 'CT-MINE-1',
        ]);
        $foreignContract = OrganizationContract::factory()->create([
            'organization_account_id' => $foreign->id,
            'provider_organization_id' => OrganizationAccount::factory()->create()->id,
            'status' => 'active', 'contract_reference' => 'CT-FOREIGN-9',
        ]);

        $user = User::factory()->create(['current_organization_id' => $org->id]);
        OrganizationMember::create(['organization_account_id' => $org->id, 'user_id' => $user->id, 'role' => 'viewer', 'status' => 'active']);

        $component = Livewire::actingAs($user)->test(ClientContractsCenter::class)
            ->call('viewContract', $mine->id)
            ->assertSet('selectedContractId', $mine->id)
            ->assertSee('CT-MINE-1');

        // Isolation : on ne peut pas ouvrir le contrat d'une autre org.
        $component->call('viewContract', $foreignContract->id)
            ->assertSet('selectedContractId', null);
    }
}
```

Run: `php artisan test --filter=ClientContractsDrilldownTest` → FAIL.

- [ ] **Step 3: Implémenter le drill-down (lecture, org-safe)**

Dans `ClientContractsCenter.php` :

```php
public ?int $selectedContractId = null;

public function viewContract(int $contractId): void
{
    $orgId = \Illuminate\Support\Facades\Auth::user()?->organizationContextId();
    // Org-safe : on ne sélectionne que si le contrat appartient à l'org du membre.
    $exists = \App\Models\OrganizationContract::query()
        ->where('id', $contractId)
        ->where('organization_account_id', $orgId)
        ->exists();
    $this->selectedContractId = $exists ? $contractId : null;
}

public function closeContract(): void
{
    $this->selectedContractId = null;
}

/** @return ?\App\Models\OrganizationContract */
public function getSelectedContractProperty(): ?\App\Models\OrganizationContract
{
    if (! $this->selectedContractId) {
        return null;
    }
    $orgId = \Illuminate\Support\Facades\Auth::user()?->organizationContextId();

    return \App\Models\OrganizationContract::query()
        ->where('id', $this->selectedContractId)
        ->where('organization_account_id', $orgId)
        ->with(['providerOrganization:id,name', 'rateCards.serviceCatalog:id,name', 'workOrders'])
        ->first();
}
```

- [ ] **Step 4: Refondre la vue (`cu-*` + panneau détail)**

`cu-page-header`/`cu-hero` en tête ; liste de contrats en `cu-card` avec métriques `cu-kpi` (remise/grille/SLA), chaque carte `wire:click="viewContract({{ $contract->id }})"` ; quand `$this->selectedContract` est posé, un panneau détail (work orders en liste, statut SLA agrégé, table de la grille tarifaire via `<x-ui.table-shell>`) + bouton fermer. Read-only (aucune mutation).

- [ ] **Step 5: PASS + non-régression**

```bash
php artisan test --filter='ClientContractsDrilldown|ClientContractsCenter'
```
Vert (isolation SP4 toujours prouvée).

- [ ] **Step 6: pint + commit**

```bash
vendor/bin/pint app/Livewire/ClientCompany/ClientContractsCenter.php tests/Feature/Relations/ClientContractsDrilldownTest.php
git add app/Livewire/ClientCompany/ClientContractsCenter.php resources/views/livewire/client-company/client-contracts-center.blade.php tests/Feature/Relations/ClientContractsDrilldownTest.php
git commit -m "feat(polish): client contracts portal — premium cu-* layout + org-safe read-only drill-down"
```

---

### Task 8: `ContractForm` + grille — inputs design-system + table premium

**Files:**
- Modify: `resources/views/livewire/admin/b2b/operations/contract-form.blade.php`
- Test: (réutilise `tests/Feature/Relations/B2BOperationsContractTest.php` existant)

- [ ] **Step 1: LIRE** la vue actuelle + `resources/views/components/ui/field.blade.php` (API : props label/error/name/hint ?) + `table-shell.blade.php` + une form admin `cu-*` soignée de référence.

- [ ] **Step 2: Refondre la vue**

Remplace les `input|select` bruts par `<x-ui.field>` (ou la classe `.ui-input`+`.ui-label`+`.ui-error-msg` si `<x-ui.field>` ne couvre pas selects) avec affichage des erreurs Livewire (`@error('contractForm.xxx')`). Layout en `cu-card`. Éditeur de grille tarifaire (`rateCardForm` + `addRateCard`) en `<x-ui.table-shell>` : table des `rateCards` existantes + ligne d'ajout inline (select service + input prix + bouton). Garde EXACTEMENT les `wire:model`/`wire:click` existants (notamment `saveContract`, `addRateCard`, `approveWorkOrder`).

- [ ] **Step 3: Non-régression**

```bash
php artisan test --filter='B2BOperationsContract|EnterpriseWorkOrderApprovalFlow|AdminB2BOperationsCenter'
```
Vert (le form sauvegarde toujours, `addRateCard` marche, le gate PO Task 8-SP4 intact).

- [ ] **Step 4: pint + commit**

```bash
vendor/bin/pint resources/views/livewire/admin/b2b/operations/contract-form.blade.php
git add resources/views/livewire/admin/b2b/operations/contract-form.blade.php
git commit -m "feat(polish): admin B2B contract form — ui.field inputs + table-shell rate-card editor with validation"
```

---

### Task 9: `SLABreaches` — tuiles `cu-kpi` récap

**Files:**
- Modify: `resources/views/livewire/admin/b2b/operations/sla-breaches.blade.php`
- Modify: `app/Livewire/Admin/B2BOperationsCenter.php` (si un compteur récap est nécessaire)

- [ ] **Step 1: LIRE** la vue actuelle + le computed `getSlaBreachesProperty` (B2BOperationsCenter) + `cu-kpi` dans `tool-mode.css`.

- [ ] **Step 2: Ajouter 3 tuiles récap**

Au-dessus de la table existante, 3 `cu-kpi` (pending / breached / escalated). Si les comptes ne sont pas déjà exposés, ajoute un computed léger dans `B2BOperationsCenter` :

```php
/** @return array{pending:int, breached:int, escalated:int} */
public function getSlaCountsProperty(): array
{
    return [
        'pending' => \App\Models\ContractSlaEvent::where('status', 'pending')->count(),
        'breached' => \App\Models\ContractSlaEvent::where('status', 'breached')->count(),
        'escalated' => \App\Models\ContractSlaEvent::where('status', 'escalated')->count(),
    ];
}
```

Harmonise les chips de statut. Les IDs mission/contrat restent en texte (pas de route détail dédiée — documenté).

- [ ] **Step 3: Non-régression + commit**

```bash
php artisan test --filter='B2BOperationsContract'
vendor/bin/pint resources/views/livewire/admin/b2b/operations/sla-breaches.blade.php app/Livewire/Admin/B2BOperationsCenter.php
git add resources/views/livewire/admin/b2b/operations/sla-breaches.blade.php app/Livewire/Admin/B2BOperationsCenter.php
git commit -m "feat(polish): admin SLA dashboard — cu-kpi summary tiles + harmonized status chips"
```

---

### Task 10: `DispatchCenter` — bloc « contrats partenaires » slate premium

**Files:**
- Modify: `resources/views/livewire/provider-company/dispatch-center.blade.php`

- [ ] **Step 1: LIRE** la vue (le bloc `partnerContracts` ajouté en SP4 + le style slate sombre de la page hôte : `bg-slate-900`, status config array, dot indicators).

- [ ] **Step 2: Refondre le bloc partenaire en slate premium**

Aligne la section « Mes contrats partenaires » au style sombre de la page : cards `rounded-2xl border bg-slate-800` avec nom client, `cu-status-dot`/dot de statut, remise/grille, compteur d'obligations SLA entrantes (réutilise `$this->partnerContracts` + missions filtrées par `organization_contract_id`). Ne touche PAS `getMissionsProperty`/`startAssign`/`confirmAssign`.

- [ ] **Step 3: Non-régression (réassignation) + commit**

```bash
php artisan test --filter='PartnerContractsView|DispatchCenterReassignment'
vendor/bin/pint resources/views/livewire/provider-company/dispatch-center.blade.php
git add resources/views/livewire/provider-company/dispatch-center.blade.php
git commit -m "feat(polish): provider dispatch center — partner contracts block restyled to dark premium"
```

---

### Task 11: Mobile — `DetailRow` partagé + états + cards unifiées

**Files:**
- Create: `mobile/shared/src/ui/DetailRow.tsx`
- Modify: `mobile/shared/src/ui/index.ts`
- Modify: `mobile/client/src/screens/BookingDetailScreen.tsx`
- Modify: `mobile/client/src/screens/booking/BookingStepProvider.tsx`
- Test: `mobile/client/src/screens/__tests__/booking-detail-polish.test.tsx`

- [ ] **Step 1: LIRE** `BookingDetailScreen.tsx` (le `DetailRow` custom + le badge `contract_covered`), `BookingStepProvider.tsx` (les `Pressable` bruts), `mobile/shared/src/ui/index.ts` + un composant UI partagé existant (ex. `StatCard`) pour le style/les tokens.

- [ ] **Step 2: Extraire `DetailRow` partagé (typé + accessible)**

```tsx
// mobile/shared/src/ui/DetailRow.tsx
import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { colors, spacing, typography } from '../theme';

export type DetailRowProps = { label: string; value: string; testID?: string };

export function DetailRow({ label, value, testID }: DetailRowProps) {
  return (
    <View style={styles.row} accessibilityRole="text" accessibilityLabel={`${label}: ${value}`} testID={testID}>
      <Text style={styles.label}>{label}</Text>
      <Text style={styles.value}>{value}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: 'row', justifyContent: 'space-between', paddingVertical: spacing.sm },
  label: { color: colors.surface[500], fontSize: typography.sizes.sm },
  value: { color: colors.surface[900], fontSize: typography.sizes.sm, fontWeight: typography.weights.semibold },
});
```

(Adapte les chemins/tokens aux VRAIS noms du `theme` — lis-le.) Exporte-le dans `mobile/shared/src/ui/index.ts`.

- [ ] **Step 3: Utiliser `DetailRow` + ajouter états empty/error dans `BookingDetailScreen`**

Remplace le `DetailRow` local par l'import partagé. Ajoute un état `EmptyState`/`ErrorState` (du design system) quand `booking` est absent/en erreur (au lieu de supposer qu'il existe). Affine le placement du badge `contract_covered`.

- [ ] **Step 4: Unifier les cards de `BookingStepProvider`**

Extrais une `SelectableCard` (ou réutilise un composant existant) pour les `Pressable` du sélecteur de type + favoris (tokens cohérents, état sélectionné via `colors.brand`). Garde les `testID`/`accessibilityRole` existants.

- [ ] **Step 5: Test mobile**

`mobile/client/src/screens/__tests__/booking-detail-polish.test.tsx` : rend `BookingDetailScreen` avec un booking `contract_covered:true` → le badge `contract-coverage-badge` est présent + un `DetailRow` rend label/valeur. (Calque le montage sur un test d'écran existant ; mock la navigation/route.)

```bash
cd mobile/client && npx tsc --noEmit && npx jest booking-detail-polish booking --watchAll=false
```
Vert.

- [ ] **Step 6: Commit**

```bash
git add mobile/shared/src/ui/DetailRow.tsx mobile/shared/src/ui/index.ts mobile/client/src/screens/BookingDetailScreen.tsx mobile/client/src/screens/booking/BookingStepProvider.tsx mobile/client/src/screens/__tests__/booking-detail-polish.test.tsx
git commit -m "feat(polish): mobile — shared DetailRow + empty/error states + unified selectable cards"
```

---

### Task 12: Vérification finale (gates complets + re-run harness)

**Files:** aucun nouveau (corrections de régression/typage uniquement si nécessaire).

- [ ] **Step 1: Suite PHP complète**

Run: `php artisan test` → **0 failed**. Corrige toute régression au plus juste.

- [ ] **Step 2: PHPStan full**

Run: `vendor/bin/phpstan analyse --memory-limit=1G` → `[OK] No errors`. Vraies annotations, **0 suppression / 0 ajout baseline** (réduire le baseline si des entrées deviennent obsolètes est OK).

- [ ] **Step 3: Pint**

Run: `vendor/bin/pint --dirty` → clean (commit si reformatage).

- [ ] **Step 4: Mobile**

Depuis `mobile/client` : `npx tsc --noEmit` (0 erreur) + `npx jest --watchAll=false` (vert).

- [ ] **Step 5: Re-run harness sur les pages touchées**

Avec `php artisan serve` lancé : `cd tools/visual-qa && npm run qa`. Les pages des surfaces refondues (client/admin/provider SP2-SP4) + les pages corrigées au Lot 2 **passent** (hors 7 deferred). Si une refonte a introduit un FAIL mobile, corrige-le.

- [ ] **Step 6: Commit final si corrections**

```bash
git add <chemins précis>
git commit -m "test(polish): final verification — full suite + phpstan green; visual-qa sweep clean on touched pages"
```

---

## Self-Review (effectué)

**Spec coverage :** DoD 1 (harness)→Tasks 1-3 ; DoD 2 (rapport initial)→Task 3 ; DoD 3 (corrections + responsive_verified)→Task 4 ; DoD 4 (BrowseCompanies)→Task 5 ; DoD 5 (provider-selection)→Task 6 ; DoD 6 (ClientContractsCenter drill-down)→Task 7 ; DoD 7 (ContractForm)→Task 8 ; DoD 8 (SLABreaches)→Task 9 ; DoD 9 (DispatchCenter)→Task 10 ; DoD 10 (mobile)→Task 11 ; DoD 11 (gates)→Task 12. Aucune section sans tâche.

**Placeholder scan :** aucun « TBD/TODO ». Les tâches de refonte (5-11) ont des étapes « LIRE puis adapter » explicites avec composant de référence + snippet représentatif — pattern validé sur SP3/SP4. Task 4 (corrections) est intrinsèquement data-driven (dépend du rapport) : structurée en lister→corriger-famille→re-run→flag, avec garde « correction ciblée, pas refonte ».

**Type consistency :** harness — `loadModules()`/`credKeyForRoles()`/`checkModule()`/`writeReport()`/`loginAs()` cohérents entre `modules.mjs`/`check.mjs`/`report.mjs`/`run.mjs` (clés de critères `c1..c5` identiques partout). Polish — `selectedContractId`/`viewContract`/`getSelectedContractProperty` cohérents (Task 7) ; `sort` ∈ `rating|providers|name` (Task 5) ; `DetailRow` props `{label,value,testID}` (Task 11).

**Note importante pour l'exécutant :** Lot 2 exige un **serveur Laravel lancé + Chromium installé**. Si l'environnement headless ne permet pas l'install Chromium ou `php artisan serve`, les Tasks 3-4 (run réel) sont **bloquées** : livrer le harness (Tasks 1-2 + le code de 3) et signaler le blocage plutôt que de simuler un rapport. Le Lot 1 (Tasks 5-11) reste exécutable indépendamment.
