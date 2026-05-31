# Registry Completion + Long-tail Polish — Design Spec

**Date:** 2026-05-31
**Status:** Approved design (pre-implementation-plan)
**Sub-project:** 4 of 4 (final) in the "Total channel parity → launch" program
**Branch:** `feat/registry-completion` (off `feat/native-migration` — the SP1→SP3 stack)

---

## Context & goal

The final sub-project. SP1 built the parity foundation (registry + WebView host + hub), SP3
migrated the `invoices` exemplar to full native parity and shipped the rubric + playbook. This
sub-project closes the **honest gap** in SP1's "total parity" promise and polishes the embedded
long-tail.

Two facts drive it:
1. **SP1's "every module reachable on both surfaces" is only true for 10 of ~50 modules.** The
   mobile hub surfaces only what's in `config/parity.php` (currently 10 entries); the other ~40
   modules exist on web but never appear in the app. Completing the registry makes the total-parity
   claim *literally* true.
2. **The remaining WebView modules must NOT be migrated to native now.** The SP3 rubric (approved)
   scored every remaining WebView module (`accounting`/`audit`/`kyb` admin-gated, `help`
   below-threshold) as **stay-WebView**, and is explicit that migration decisions need **real
   post-launch usage data** ("you cannot honestly rank frequency until the app is live"). SP4
   therefore **completes and polishes the WebView surface — it does not migrate anything to native.**

**Goal:** make the registry the complete single source of truth for "every module on mobile,"
**prove every WebView fallback genuinely renders**, and polish the embedded long-tail to be good on
a phone — without migrating anything to native (that stays the post-launch, rubric-driven backlog).

### Decisions (from brainstorming)
- **Module granularity:** a "module" = one **top-level navigable web area per role** (a tappable hub
  entry), NOT one of the ~135 routes and NOT a `platform_modules` feature-flag. Curated from the
  role-based web navigation.
- **Polish bar:** **both** an automated test (path resolves + embed renders) **and** an
  operate-together visual-QA pass; `responsive_verified` flips only when both pass.
- **Registry storage:** stays **file-based** (`config/parity.php`), consistent with SP1 (a flag-flip
  rides with deploy).
- **Paths must be real, verified routes** — the SP3 stale-path bug (`/client/invoices` vs the real
  `/dashboard/client/finance`) at the scale of ~40 modules is the central risk.

### Current registry state (verified)
`config/parity.php` has 10 modules: native = booking, tracking, chat, missions, earnings, invoices;
webview = accounting, audit, kyb (`responsive_verified: false`), help (`true`). Route surface:
admin 73, client 40, employe 22 (~135 routes → curated to navigable areas).

---

## Architecture & shape

The registry becomes the **complete single source of truth**, and each WebView fallback is proven
to render. Two phases (the SP2 structure: autonomous artifacts + operate-together drills).

**Phase 1 (autonomous, this spec's build):** curate + register all navigable modules in
`config/parity.php` (as `webview`, role-scoped, `responsive_verified: false`; the 6 native untouched);
three autonomous test suites covering every module; scaffold the Phase-2 QA runbook.

**Phase 2 (operate-together):** walk the per-module visual-QA checklist at phone width; fix responsive
gaps module-by-module; flip `responsive_verified` when both the test and the visual check pass.

---

## Phase 1 components (autonomous)

Five bounded units.

**1. Module curation + registry population** *(foundation; discovery-heavy).*
Read the role route files (`routes/admin.php`, `client.php`, `employe.php`), the web nav, and the role
dashboards to enumerate the **navigable areas per role**. For each, a registry entry: `key`, `title`,
`icon` (ionicons), **real verified `path`** (matched to the route table), `web: 'native'`,
`mobile: 'webview'`, `roles`, `responsive_verified: false`. Written into `config/parity.php`; the 6
existing native entries are untouched. A module visible to multiple roles lists all applicable roles
(`[]` = everyone authenticated). *Depends on:* the route table + nav as truth.

**2. Path-resolution test** — `tests/Feature/Parity/ParityPathsResolveTest.php`.
Iterate `config('parity.modules')`; assert every `path` matches a **registered GET route** (via the
router's route collection — e.g. resolve `Request::create($path,'GET')` against `app('router')`).
One failing assertion per stale/wrong path. Scale-proof guard against the SP3 stale-path bug.
*Depends on:* the router. Pure, fast.

**3. Embed-render test** — `tests/Feature/Parity/ParityEmbedRenderTest.php`.
For each `webview` module, `actingAs` a minimal user of its role, `GET {path}?embed=1`, assert **200
+ chrome-less** (SP1 `EmbedMode` strips nav — assert the `data-chrome="primary-nav"` marker is
absent). Proves every embedded fallback renders for its audience. *Boundary:* hub modules are
top-level (list/dashboard) pages that render without a specific entity; where a page needs fixtures,
seed minimal; if a page is irreducibly fixture-heavy, `markTestSkipped` with a reason **and** a note
in the QA runbook (no silent gaps). *Depends on:* web routes + EmbedMode.

**4. Role-filter test** — `tests/Feature/Parity/ParityRoleAccessTest.php`.
Extends SP1's parity-map role filtering to **every** module: a role-X user's `/api/parity-map`
contains exactly the X-visible modules; a role-X user hitting another role's module path is blocked by
the existing web authz (403/redirect/non-200). Proves the registry's `roles` match real access
control. *Depends on:* `ParityMapController` + web authz.

**5. `docs/runbooks/EMBED-VISUAL-QA.md` scaffold** — the Phase-2 checklist.
One section per webview module: `key`, `path`, the role to log in as, the visual checks (phone width:
no horizontal scroll, tappable targets, readable text, no broken/overflowing layout, embed chrome
absent), and a results table (`PASS/FAIL | timestamp | who | notes`). Plus the `responsive_verified`
flip rule (embed-render test green **and** visual PASS) and the list of any fixture-heavy modules
whose embed-render test was skipped (to be visually verified manually). *Documentation; no test.*

**Boundary discipline:** units 1–4 are registry + tests (path-resolution and role-filter pure/fast;
embed-render is the heavier one that loads pages). **No responsive fixes happen in Phase 1** — visual
issues aren't detectable without a browser, so fixes are Phase 2, surgical per module.

---

## Phase 2 (operate-together)

Driven by `EMBED-VISUAL-QA.md`. **I produce the exact steps** (role to log in as, the `{path}?embed=1`
URL, what to look for at phone width) and interpret what you report; **you execute the visual check**
on a real device or a narrow browser; **we fix responsive gaps together** (surgical embed-mode
CSS/Tailwind, one small commit per module). `responsive_verified` flips to `true` for a module only
when **both** its embed-render test is green **and** its visual QA passes (after any fix). Paced
module-by-module across sessions; not a single sweep.

---

## Definition of Done

*Phase 1 (autonomous):*
1. Registry **complete** — every navigable area per role in `config/parity.php` with a real verified
   path + correct `roles` (6 native untouched; the rest `webview`, `responsive_verified: false`).
2. **Path-resolution** test green (every path → a real route).
3. **Embed-render** test green (every webview fallback renders chrome-less for its role; any
   fixture-heavy skips documented with a reason in the runbook).
4. **Role-filter** test green (parity map ↔ web authz per role).
5. `EMBED-VISUAL-QA.md` scaffolded.
6. CI green (PHPUnit + Pint + PHPStan full run; parity tests in the always-run suite, 0 unjustified
   skips).

*Phase 2 (operate-together):*
7. Each webview module visual-QA'd + responsive-fixed + `responsive_verified` flipped — recorded in
   the runbook.

**"Total parity genuinely complete" = Phase 1 done (every module reachable + every fallback proven to
render) + Phase 2 visual QA per module.**

---

## Scope boundaries

**In scope:** curate + register all navigable modules; the three autonomous test suites; the QA
runbook; Phase-2 visual QA + responsive fixes.

**Out of scope:**
- **Migrating any module to native** — that is the post-launch, rubric-driven, usage-data-gated
  backlog. SP4 completes and polishes the **WebView** surface only; it migrates nothing.
- The **scaffold/codegen generator** — deferred (not chosen); a clean future item once migrations
  resume post-launch with data.
- A **DB-backed registry** — kept file-based per SP1.
- New business modules or features.

**Dependency:** builds on SP1's registry/hub/`EmbedMode` + SP3's registry state, so it bases on
`feat/native-migration` (or `main` once the SP1→SP3 stack merges). Note the stack ordering:
parity-foundation → native-migration → registry-completion.
