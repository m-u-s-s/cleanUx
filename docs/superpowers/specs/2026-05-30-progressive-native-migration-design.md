# Progressive Native Migration — Design Spec

**Date:** 2026-05-30
**Status:** Approved design (pre-implementation-plan)
**Sub-project:** 3 of 4 in the "Total channel parity → launch" program
**Branch:** `feat/native-migration` (off `feat/parity-foundation` — see base-branch note)

---

## Context & goal

Sub-project 3 of the launch program. Sub-project 1 (parity foundation) made every module
reachable on mobile — hot paths native, the long tail rendered in an authenticated WebView,
with `config/parity.php` as the seam (flip a module's `mobile` flag `webview → native` to
re-route it, no nav rewrite; proven by sub-project 1's flag-flip test). Sub-project 3 is the
**strangler queue**: progressively replacing WebView modules with native screens, one small
safe PR at a time.

**Scope (decided during brainstorming):** this is **not** "migrate everything." The hot paths
(booking, tracking, chat, missions, earnings) are already native; the current WebView modules
are mostly low-frequency back-office that should *stay* embedded. So sub-project 3 delivers:
1. a **native-worthiness rubric** that decides which modules are *worth* full native parity
   (and which stay WebView, possibly forever);
2. a **repeatable migration playbook** (`NATIVE-MIGRATION-PLAYBOOK.md`) so every future
   migration is a mechanical, low-risk PR;
3. **one exemplar migration — `invoices` — to full native parity** as living proof the
   playbook works end to end (including the hard API-gap step).
The remaining migrations are **ongoing post-launch work**, executed by re-running the playbook.

**Parity bar (decided):** **full feature parity** per migration — a module's native screen
must replicate the web version's *entire* feature set before the flag flips. Crucially, this
moves the YAGNI decision *up to module selection*: the rubric gates which modules are worth
full native parity, so giant/unbounded back-office modules score low and stay WebView. You
never half-build a screen, and you never sink effort into a screen nobody needs native.

### Base-branch note

Sub-project 1 (`feat/parity-foundation`) is **not yet merged to `main`**. Sub-project 3
requires its code (`config/parity.php`, `ModuleHubScreen`, `EmbeddedModuleScreen`,
`NATIVE_ROUTES`), so this work bases on `feat/parity-foundation` (this spec's branch is off
it). If parity-foundation merges to `main` first, rebase onto `main`.

### Current registry state (verified, from `feat/parity-foundation:config/parity.php`)

- **Native today:** booking, tracking, chat, missions, earnings.
- **WebView (long-tail):** accounting, audit, kyb (admin), invoices, help (client/public).
- `NATIVE_ROUTES` in `ModuleHubScreen` maps `key → screen name`; a `native`-flagged module
  with no `NATIVE_ROUTES` entry safely falls back to the WebView — so the playbook adds both.

---

## The native-worthiness rubric

A weighted scorecard applied to every module. Five criteria, 0–3 each (max 15), plus a hard
gate and disqualifiers. The rubric is the artifact that decides what is *worth* full native
parity — and what stays WebView.

| Criterion | 0 | 3 | Why |
|---|---|---|---|
| **Frequency** | rarely opened on mobile | daily-driver | native pays off where users live |
| **Device leverage** | none | several (camera/QR, GPS, push deep-link, biometric, offline, native share/download) | what a WebView genuinely can't do well |
| **WebView friction** | read-only content | heavy forms / fast nav / latency-sensitive | where embedded feels worst |
| **Audience fit** | admin back-office | B2C / field user on the go | who actually benefits |
| **Full-parity tractability** | huge multi-tab center | small bounded feature set | **inverse cost** — under full-parity, the gate |

**Hard gate:** never migrate if `tractability ≤ 1` (replicating an unbounded surface in full
native parity is the multi-year trap). **Disqualifiers** (auto stay-WebView regardless of
score): export-heavy modules (FEC/CSV/accounting), admin-only + frequency-0, any module whose
feature surface can't be bounded.

**Threshold:** migrate if `score ≥ 9` AND `tractability ≥ 2`; else stay WebView and **revisit
with real usage data** post-launch (you can't rank frequency honestly until people use the app).

**Applied to current WebView modules (self-consistent):**
- **invoices** — client, bounded (list + detail + PDF), native share/download → ~11 → **migrate**.
- **help** — public, content-only FAQ, WebView fine → ~5 → **stay WebView**.
- **accounting / audit / kyb** — admin-only, huge, export-heavy → ~2 + disqualifier → **stay WebView**.

The rubric is documented (with this scoring table + a per-module worksheet) and applied to the
full registry to produce the **ranked backlog**.

---

## The migration playbook (repeatable PR recipe)

Written to `docs/runbooks/NATIVE-MIGRATION-PLAYBOOK.md` with a per-module parity-checklist
template. For module `X` (currently `webview`):

1. **Parity audit.** Enumerate *every* action/view the web module at its `path` offers → a
   per-module **parity checklist**. The flag does not flip until every item is covered
   natively. This makes "full parity" verifiable, not aspirational.
2. **API-gap analysis.** The native screen consumes the existing API (`apiClient`). Web modules
   often perform actions via server-rendered **Livewire, not a JSON API** → each such action is
   an **API gap** to be built (backend endpoint). (This is also why low-tractability modules
   score lower: many Livewire-only actions = expensive.)
3. **Build the native screen(s)** in `mobile/client/src/screens/`, consuming the API, using the
   shared UI kit (`@/ui`, `@/theme`). Cover the full checklist + the rubric's device upgrades.
4. **Wire navigation (three additive edits):** add to `RootStackParamList` (`navigation/types.ts`);
   register `<Stack.Screen>` in `navigation/RootNavigator.tsx`; add `X: { screen: 'X' }` to
   `NATIVE_ROUTES` in `screens/ModuleHubScreen.tsx`.
5. **Flip the seam:** `config/parity.php` → module `X` `mobile: 'webview' → 'native'`. One line.
   (Until both the `NATIVE_ROUTES` entry *and* the flag exist, the module keeps rendering the
   WebView — no broken mid-PR state.)
6. **Tests (the gate):** native screen test (mocked API, every checklist action); routing
   flag-flip assertion (flag flipped → routes native, not `EmbeddedModule`); backend tests for
   new API endpoints.
7. **Safety / rollback:** the WebView path stays intact. **Rollback = flip the flag back**
   (config change, zero code revert, instant). Optionally gate the flip behind a per-module
   rollout flag for a staged % rollout.
8. **Ship as one small PR**, scoped to module `X` only.

---

## Exemplar: `invoices` (full native parity)

Rubric #1, and a complete playbook exerciser. Grounded facts (verified):
- **Web surface:** `app/Livewire/Client/FinanceDocumentsClient.php` (client finance-documents /
  invoices list). Models exist: `FinanceInvoice`, `FinanceDocumentService`.
- **No invoices API exists** — the web module is Livewire-only → a real **API gap** to build.
- No native invoices screen yet in `mobile/client`.

The exemplar therefore runs the *whole* playbook:
1. **Parity audit** of `FinanceDocumentsClient` → checklist (list documents, filter, view a
   document's detail, download/open the PDF — confirm the exact action set against the component).
2. **API-gap build (backend):** new client invoices API — list (`GET /api/client/invoices`),
   detail (`GET /api/client/invoices/{id}`), and PDF retrieval (a download/stream endpoint) —
   reusing `FinanceDocumentService`/`FinanceInvoice`. Sanctum-guarded, role + ownership scoped
   (a client sees only their own documents). Backend feature tests incl. cross-tenant isolation.
3. **Native screen(s):** `InvoicesScreen` (list) + detail, consuming the new API, with **native
   PDF share/download** (the rubric's device-leverage upgrade) instead of an in-WebView viewer.
4. **Nav wiring:** `Invoices` in types + `RootNavigator` + `NATIVE_ROUTES` (`invoices: { screen: 'Invoices' }`).
5. **Flip:** `config/parity.php` invoices `mobile: 'webview' → 'native'`.
6. **Tests:** native screen test; flag-flip routing assertion; backend API + isolation tests.
7. **Rollback proven:** a test (or documented step) showing `native → webview` re-routes to the
   embedded path.

---

## Verification — every migration ships with

1. Completed **parity checklist** (full-feature-parity gate).
2. **Native screen test** (renders, mocked API, exercises every checklist action).
3. **Routing flag-flip assertion** (flag flipped → routes native, not `EmbeddedModule`).
4. **Backend tests** for new API endpoints (incl. role + ownership isolation).
5. **WebView fallback intact** → rollback = flip the flag back, proven by a test.

---

## Definition of Done

1. **Rubric** documented (scoring table + per-module worksheet) and **applied to the full
   registry** → a ranked backlog of native candidates vs stay-WebView.
2. **`NATIVE-MIGRATION-PLAYBOOK.md`** written with the 8-step recipe + per-module parity-checklist
   template.
3. **`invoices` migrated to full native parity:** invoices API built (list/detail/PDF, scoped +
   isolation-tested), native `InvoicesScreen` (+ detail, native PDF share), nav wired, parity
   flag flipped, all tests green, WebView fallback intact.
4. **Rollback proven:** flipping invoices `native → webview` re-routes to the embedded path
   (test).
5. CI green (mobile typecheck + jest; backend PHPUnit + Pint + PHPStan), 0 unjustified skips.

---

## Scope boundaries

**In scope:** the rubric + ranked backlog; the playbook doc; the single `invoices` full-parity
migration (incl. its API-gap backend build); the verification pattern.

**Out of scope:** migrating any module other than invoices (the rest is ongoing post-launch via
the playbook); a scaffold/codegen generator for migrations (a natural sub-project-4 item, only
worthwhile *after* the manual pattern is proven here); changing the WebView host or parity
foundation itself (sub-project 1); the second write-shape exemplar (disputes/contracts —
explicitly deferred).

**Dependency:** requires sub-project 1's parity foundation present on the base branch (see
base-branch note). The invoices migration also depends on `FinanceDocumentService`/`FinanceInvoice`
being able to serve a client's own documents via the new API.
