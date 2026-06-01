# Visual QA harness (mobile embed pages)

Headless Playwright sweep of every embedded WebView page at 390×844, checking 5 mobile criteria.

## Prerequisites
- **Seed the 5 QA accounts** (idempotent, password `QaPhase2!`) — required for the per-role logins:
  ```
  php artisan db:seed --class=QaAccountsSeeder
  ```
  This versioned seeder (`database/seeders/QaAccountsSeeder.php`) provisions the admin,
  provider-company OWNER, client-company OWNER, independent provider, and personal client used by
  `CREDENTIALS` in `modules.mjs` (and `scripts/embed_sweep.php`). Self-contained: it creates the two
  QA orgs if absent. Re-runnable safely. It is **not** part of `DatabaseSeeder` (no QA accounts in prod).
- A running Laravel server reachable at `VQA_BASE` (default `http://127.0.0.1:8000`):
  `php artisan serve` from the repo root.
- `npm install && npx playwright install chromium` in this folder.

## Run
```
VQA_BASE=http://127.0.0.1:8000 npm run qa
```
Writes `out/report.json` + `out/report.md`.

## Criteria (per page, 390px viewport)
1. **C1** No horizontal document scroll · 2. **C2** Tap targets not thumb-hostile (primary controls) ·
3. **C3** Readable text (no horizontal clip) · 4. **C4** No broken layout (no element overflowing the
viewport's right edge) · 5. **C5** Nav chrome absent (`[data-chrome="primary-nav"]`).

`VQA_TOLERANCE` (default 2px) softens C1/C3/C4. 7 MySQL-only pages are skipped (see `modules.mjs`
`DEFERRED_KEYS`).

## Tuned thresholds (signal calibration — baseline 2026-06-01)

The raw "≥44px in both dimensions" tap-target rule and a naive overflow rule produced massive false
positives (77/111 FAIL, ~70 pages flagging the same patterns). Calibrated against the first real sweep so
that **one FAIL = one real mobile concern**:

- **C2 tap targets** — flags a control only when it is hostile to the thumb: exiguous in **both**
  dimensions (`height < 24px && width < 80px`) **or** a tiny icon button (`width < 28px`). A wide-but-short
  control (admin tab strip 374×35, secondary text-toggle ~90×24) stays a PASS — horizontal touch-slop makes
  it reachable. Selectors restricted to real controls (`button`, `[role=button]`, submit/button inputs,
  `a.btn`, `.ui-btn`, `.cu-btn-*`), never inline text links. (`C2_MIN_HEIGHT=24`, `C2_NARROW=80`.)
- **C4 + inScrollable** — an element is exempt when it (or any ancestor) has `overflow-x: auto|scroll`,
  **including itself** (a `<table overflow-x-auto>`) and **table internals** (`thead/tbody/tr/td/th`) whose
  root `<table>` lives inside a horizontally-scrollable wrapper (the Tailwind `overflow-x-auto > table`
  pattern). This stops legitimately scrollable tables from being reported as broken layout.
- **C1 ∩ C4 correlate exactly** on the residual real failures: admin pages whose data tables exceed 390px
  **without** a scroll wrapper genuinely cause horizontal document scroll — those are true positives to fix
  at source (Lot 2 / Task 4), not noise.

Honest baseline (post-tuning): **55/111 PASS, 56 FAIL** — 14 real C1+C4 table-overflow pages, a handful of
genuine tiny controls / one nav-chrome leak (`admin-users`), plus the residual 21px admin tab-strip pattern
that C2 reports as a real (if minor) WCAG tap-target item for the polish pass. The harness is **not** forced
green.
