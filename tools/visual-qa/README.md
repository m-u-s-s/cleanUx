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
