# Polish & Visual QA — Design Spec

**Date :** 2026-06-01
**Statut :** Design approuvé (avant plan d'implémentation)
**Branche prévue :** `feat/polish-and-visual-qa` (off `main`)

---

## Contexte & objectif

Le programme 4-relations (SP1-SP4) a livré la **fonctionnalité** (4 relations C2I/C2B/B2I/B2B
opérationnelles, gates verts) mais ses **nouvelles surfaces UI** ont été construites « fonctionnelles
mais minimales ». Par ailleurs, la **Phase-2 visual QA** des pages embarquées (WebView mobile) est restée
manuelle et inachevée. Ce projet fait deux choses : (1) une **refonte premium** des surfaces SP2/SP3/SP4,
et (2) la construction d'un **harness de visual QA automatisé** (Playwright headless) qui balaye les ~115
pages embarquées contre 5 critères mobiles, produit un rapport, et sert de gate répétable.

Référence de design = le système EXISTANT (pas d'invention) : web `brio-*` (tool-mode clair :
`brio-hero`/`brio-card`/`brio-kpi`) + `ui-*` (card/button/badge/input/empty-state/table-shell) + tokens
(`resources/css/tokens.css`) ; mobile `mobile/shared/src/theme` + `mobile/shared/src/ui` (23 composants) ;
et le pattern de filtres de `BrowseProviders` à répliquer pour `BrowseCompanies`.

### Décisions (validées)

1. **Deux lots, un cycle.** Lot 2 (harness QA) construit **en premier** (mesure objective avant/après +
   valide ensuite les pages refondues), puis Lot 1 (refonte premium).
2. **Profondeur = refonte premium** (au-delà de l'alignement : heroes, drill-down, micro-interactions),
   ancrée sur le design system existant.
3. **Direction par contexte** : client + admin en **clair premium `brio-*`** ; prestataire en **slate
   sombre** cohérent avec le `DispatchCenter` existant.
4. **Visual QA automatisée** (Playwright headless), pas manuelle. Les 7 pages « deferred » (qui exigent
   MySQL, 500 sous SQLite) restent hors périmètre headless.

---

## État des lieux (vérifié)

**Design system web** — `tailwind.config.js` (brand indigo `#6366f1`/`#4f46e5`, surface slate, sémantiques
emerald/amber/red, accents amber/cyan/violet ; shadows `soft-*` ; fonts Figtree/Space Grotesk) ;
`resources/css/tokens.css` (radius 10→28px, ease premium) ; `resources/css/tool-mode.css`
(`brio-hero`/`brio-card`/`brio-kpi`/`brio-page-header`/`brio-btn-*`/`brio-empty`/`brio-table`) ; composants Blade
`resources/views/components/ui/*` (`card`, `button`, `badge`, `page-header`, `empty-state`, `table-shell`)
+ `stat-card`. Deux modes : `brio-*` (clair, dashboards) / `cx-*` (sombre, vitrine).

**Niveau de finition des surfaces (échelle BRUT → MIN-POLI → POLI → TRÈS-POLI)** :
- `resources/views/livewire/client/browse-companies.blade.php` — **BRUT** (cards simples, **aucun filtre**).
- `resources/views/livewire/client/booking/scheduling/provider-selection.blade.php` — **MIN-POLI**
  (3 paliers OK, blocs « créneaux alternatifs » en alertes amber custom hors design system).
- `resources/views/livewire/client-company/client-contracts-center.blade.php` — **MIN-POLI** (read-only,
  pas de drill-down).
- `resources/views/livewire/admin/b2b/operations/contract-form.blade.php` — **BRUT** (inputs custom,
  PAS `.ui-input`, pas de validation visuelle, grille tarifaire en liste simple).
- `resources/views/livewire/admin/b2b/operations/sla-breaches.blade.php` — **POLI** (table propre, manque
  tuiles récap + liens).
- Bloc « contrats partenaires » dans `resources/views/livewire/provider-company/dispatch-center.blade.php`
  — **MIN-POLI** (la page hôte est TRÈS-POLI sombre ; le bloc ajouté n'est pas à niveau).

**Pattern de filtres réutilisable** — `app/Livewire/Client/BrowseProviders.php` + sa vue :
`#[Url]` props (`query` debounce 400ms, `tradeId`, `minRating` [null/3/4/4.5], `minPrice`/`maxPrice`,
`postalCode` + autocomplete, `sort` [rating/popularity/price_asc/price_desc]), hook `updating()` →
`resetPage()`, `resetFilters()`, `selectionMode` + `selectProvider()` → `dispatch('providerSelected')`.
Markup : sidebar `lg:col-span-1` (filtres) + résultats `lg:col-span-3` (header tri + grille + pagination).

**Design system mobile** — `mobile/shared/src/theme` (colors brand/surface/semantic 11 steps, typography
Figtree/SpaceGrotesk, spacing 8px-base, radius, shadows indigo-tinted) + `mobile/shared/src/ui` (Button,
Badge, Screen, TextInput, Avatar, Skeleton, EmptyState, Divider, ProgressBar, StatCard…). États :
`BookingCompanySearchScreen` **EXCELLENT** ; `BookingStepProvider` **GOOD** (20% `Pressable` bruts) ;
`BookingDetailScreen` **FAIR** (`DetailRow` custom intra-fichier, pas d'états empty/error).

**Infra embed / QA** — middleware `app/Http/Middleware/EmbedMode.php` (`?embed=1` ou header `X-Embedded:1`
→ partage `$embedded`, masque `[data-chrome="primary-nav"]`) ; `app/Http/Controllers/WebViewEntryController.php`
(ticket → session → redirect `?embed=1`) ; `scripts/embed_sweep.php` (balayage HTTP, login par rôle, 5
comptes `*@brio.test`/exemple en `QaPhase2!`) ; runbook `docs/runbooks/EMBED-VISUAL-QA.md` (118 modules,
115 rendent 200 sans chrome, 5 critères visuels, 7 deferred MySQL). **Aucun Playwright/Puppeteer installé.**

---

## Architecture & composants

### Lot 2 — Harness de visual QA automatisé *(construit en premier)*

Unités :

1. **Installation Playwright** — `@playwright/test` (ou `playwright` + `chromium`) en devDependency, dans un
   dossier dédié `tools/visual-qa/` (package isolé pour ne pas polluer le mobile/Expo). Chromium headless.

2. **Inventaire des pages** — `tools/visual-qa/modules.mjs` : la liste `{ role, key, path }` des modules,
   dérivée du registre de parité (`config/parity*` / le runbook). Source unique consommée par le checker.

3. **Checker** — `tools/visual-qa/check.mjs` : pour chaque rôle, login via `POST /login` (comptes QA
   `QaPhase2!`), puis pour chaque module : `goto(<base><path>?embed=1)` à viewport **390×844**, et évalue
   les 5 critères dans la page (`page.evaluate`) :
   - **C1 no-h-scroll** : `documentElement.scrollWidth ≤ clientWidth + 2`.
   - **C2 tap-targets** : tous les `a[href], button, [role=button], input, select` visibles ont
     `width ≥ 44 && height ≥ 44` (sinon liste les sélecteurs fautifs, avec une whitelist configurable pour
     les exceptions légitimes type liens inline).
   - **C3 readable-text** : aucun élément texte avec `scrollWidth > clientWidth + 2` (clip horizontal).
   - **C4 no-broken-layout** : aucun élément débordant la largeur viewport (`getBoundingClientRect().right >
     clientWidth + 2`) ; pas de conteneur à enfants avec hauteur 0.
   - **C5 nav-chrome-absent** : `document.querySelector('[data-chrome="primary-nav"]')` est `null`.
   Tolérance configurable (env `VQA_TOLERANCE`, défaut 2px).

4. **Rapport** — `tools/visual-qa/report.mjs` écrit `tools/visual-qa/out/report.json` + `report.md`
   (matrice page × critère, PASS/FAIL + détails des éléments fautifs). Le markdown est lisible et peut
   alimenter le runbook.

5. **Runner** — `tools/visual-qa/run.mjs` (ou un script npm `visual-qa`) : orchestre login + sweep + report.
   Suppose un serveur Laravel atteignable (`VQA_BASE`, défaut `http://127.0.0.1:8000`). Ne lance PAS le
   serveur lui-même (documenté : `php artisan serve` à part) pour rester simple et déterministe.

6. **Correction des échecs** — chaque FAIL du rapport (hors 7 deferred) est corrigé à la source
   (Tailwind/Blade/Livewire) ; re-run jusqu'à vert sur le périmètre couvert. Les modules verts basculent
   `responsive_verified: true` (là où ce flag vit — `config/parity` ou le registre).

*Dépend de :* le serveur Laravel + les comptes QA seedés (vérifier le seeder utilisé par `embed_sweep.php`),
le middleware `EmbedMode`.

### Lot 1 — Refonte premium des surfaces *(après le harness)*

Chaque surface est une unité indépendante.

**Client — clair premium `brio-*`**

- **`BrowseCompanies`** (`app/Livewire/Client/BrowseCompanies.php` + vue) : ajoute les props filtres
  (`#[Url]` `query`, `minRating`, `sort` ∈ `rating|providers|name` — **pas** de prix : une société n'a pas
  de prix unitaire ; **pas** de postal : la zone vient déjà du contexte booking), hook `updating()`,
  `resetFilters()`. La requête filtre/trie la collection de `EligibleCompaniesResolver` (ou un repository
  dédié) sur nom/note. Vue : sidebar filtres (style `BrowseProviders`) + grille de `brio-card` société
  (avatar, nom, note ★ + count, badge `providers_count`, tags métiers si dispo) + `.ui-empty`. Le mode
  sélection (embed) conserve le CTA « Choisir cette société » et l'event `companySelected`.
- **`provider-selection`** (vue picker 3 paliers) : convertit le sélecteur de type + les blocs « créneaux
  alternatifs » (provider ET company) en composants design-system (`brio-card`/`ui-badge` + chips de créneau
  cliquables réutilisant l'action de sélection existante). Pas de changement de logique Livewire.
- **`ClientContractsCenter`** (`app/Livewire/ClientCompany/ClientContractsCenter.php` + vue) : `brio-page-header`/
  `brio-hero`, cartes contrat en `brio-card` avec métriques `brio-kpi` (remise/grille/SLA), et un **drill-down** :
  sélection d'un contrat → panneau détail (liste Work Orders, statut SLA agrégé, table de la grille
  tarifaire). Reste **lecture seule** (aucune mutation ; isolation org inchangée).

**Admin — clair premium `brio-*`**

- **`ContractForm` + grille** (vue + ajustements `B2BOperationsCenter` si besoin) : inputs → `<x-ui.input>`/
  `.ui-input` + `.ui-label` + `.ui-error-msg` (états focus/erreur), layout form premium en `brio-card`,
  éditeur de grille tarifaire en `<x-ui.table-shell>` avec ligne d'ajout inline + retour visuel de
  validation. Aucune nouvelle logique métier.
- **`SLABreaches`** (vue) : 3 tuiles `brio-kpi` (pending / breached / escalated, comptes) au-dessus de la
  table existante, chips de statut harmonisés, IDs mission/contrat rendus comme liens si une route détail
  existe (sinon laissés en texte, documenté).

**Prestataire — slate sombre cohérent**

- **Bloc « contrats partenaires »** dans la vue `DispatchCenter` : section sombre premium (cartes client,
  status-dot, remise/grille, compteur d'obligations SLA entrantes) alignée au style slate de la page hôte.
  La réassignation worker SP3 reste intacte.

**Mobile — premium adaptive**

- Extraire `DetailRow` (aujourd'hui custom dans `BookingDetailScreen.tsx`) en composant partagé
  `mobile/shared/src/ui/DetailRow.tsx` (typé, accessible) ; l'utiliser dans `BookingDetailScreen`.
- Ajouter des états **empty/error** à `BookingDetailScreen` (réutiliser `EmptyState`/`ErrorState`).
- Unifier les `Pressable` bruts (type selector + favoris) de `BookingStepProvider` via une `Card`/
  `SelectableCard` partagée du design system (ou un composant local réutilisable), tokens cohérents.
- Affiner le placement/variant du badge `contract_covered` (déjà `Badge variant="info"`, vérifier
  hiérarchie visuelle). `BookingCompanySearchScreen` : retouches mineures seulement (déjà excellent).

---

## Flux du harness (visual QA)

1. Opérateur (ou CI) lance le serveur : `php artisan serve` (DB de dev/seedée).
2. `node tools/visual-qa/run.mjs` : pour chaque rôle → login (`QaPhase2!`) → pour chaque module → `goto
   ?embed=1` à 390×844 → `evaluate` les 5 critères → collecte.
3. Écrit `out/report.json` + `out/report.md` (matrice PASS/FAIL + éléments fautifs).
4. Les FAIL (hors 7 deferred) sont corrigés à la source ; re-run jusqu'au vert.
5. Modules verts → `responsive_verified: true`.

---

## Definition of Done

1. **Harness** : Playwright installé (`tools/visual-qa/`), `modules.mjs` (inventaire), `check.mjs` (5
   critères), `report.mjs` (JSON+MD), `run.mjs` (orchestration login+sweep). Documenté dans un README court
   (prérequis : serveur lancé, comptes seedés).
2. **Rapport initial** généré sur les pages couvertes ; les FAIL listés.
3. **Corrections** : tous les FAIL du périmètre headless corrigés à la source ; re-run **vert** ; 7 deferred
   listés comme reste-à-faire (non bloquant).
4. **BrowseCompanies** : filtres (recherche/note/tri) + grille premium `brio-card` + empty-state ; tests
   Livewire (filtre/tri appliqués, event sélection intact).
5. **provider-selection** : sélecteur + créneaux alternatifs en composants design-system (provider+company).
6. **ClientContractsCenter** : refonte `brio-*` + drill-down contrat (WO + SLA + grille), lecture seule,
   isolation org inchangée ; test (drill-down rend, isolation tenue).
7. **ContractForm + grille** : `.ui-input`/`.ui-label`/`.ui-error` + table grille + validation visuelle ;
   test (ajout rate card via le form reste vert).
8. **SLABreaches** : tuiles `brio-kpi` récap + table harmonisée.
9. **DispatchCenter** bloc partenaire : section sombre premium ; test isolation/réassignation toujours vert.
10. **Mobile** : `DetailRow` extrait + utilisé ; états empty/error sur `BookingDetailScreen` ; cards
    `BookingStepProvider` unifiées ; badge contrat affiné ; tsc + jest verts.
11. **Gates** : suite PHP complète verte, **PHPStan full** `[OK]`, Pint clean ; mobile `tsc --noEmit` +
    Jest verts ; harness re-run vert sur le périmètre.

---

## Limites de scope

**Dans le scope :** harness Playwright + rapport + corrections des FAIL du parc embed (périmètre headless) ;
refonte premium des 6 surfaces web SP2/SP3/SP4 + montée en gamme des 3 écrans mobiles ; réutilisation du
design system existant.

**Hors scope :**
- Refonte de pages hors SP2/SP3/SP4 **au-delà** des corrections que le harness rend nécessaires (un FAIL
  bloquant sur une page tierce est corrigé au minimum, pas redesigné).
- Nouvelles fonctionnalités métier (les surfaces restent fonctionnellement identiques ; on ajoute seulement
  les filtres `BrowseCompanies` et le drill-down lecture de `ClientContractsCenter`).
- Les **7 pages MySQL-deferred** (vérif manuelle ultérieure sur staging MySQL).
- **Pipeline CI** du harness (on livre le script runnable, pas l'intégration CI/CD).
- Backend/logique de matching, contrats, SLA (déjà livrés et audités SP1-SP4).

**Dépendances :** design system existant (`brio-*`/`ui-*`/tokens, mobile theme/ui), `BrowseProviders` (pattern
filtres), middleware `EmbedMode`, comptes QA seedés, `EligibleCompaniesResolver` (données société),
`OrganizationContract`/`ContractRateCard`/`ContractSlaEvent` (données portails). S'appuie sur SP1-SP4 mergés.
