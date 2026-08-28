# Visual QA harness (mobile embed pages)

Headless Playwright sweep of every embedded WebView page at 390×844, checking 5 mobile criteria.

## Prerequisites
- **Seed the 5 QA accounts** (idempotent ; mot de passe commun défini par `config/brio.php` →
  `seed.password`, `12345678` par défaut) — required for the per-role logins:
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

Ne vérifier qu'une page (ou quelques-unes) :
```
VQA_BASE=http://127.0.0.1:8000 VQA_ONLY=admin-order-engine npm run qa
```

### Les deux autres balayages

`npm run qa` couvre les 121 pages **authentifiées**. Deux surfaces lui échappent :

| Commande | Ce qu'elle mesure |
|---|---|
| `npm run qa:publiques` | Les 12 pages qu'un visiteur voit **sans compte** — `run.mjs` n'en couvrait qu'une |
| `npm run qa:theme` | L'éclair de thème au chargement, les boutons sans nom accessible, le réglage du thème sous 640 px |
| `npm run qa:espacement` | Deux blocs de premier niveau qui se **touchent** dans le slot d'une `x-page-shell` — la coquille n'espace pas ses enfants |

`qa:theme` a besoin d'un compte client valide. Les comptes de `modules.mjs` viennent d'un seeder
Faker : après un `migrate:fresh --seed`, passez `VQA_CLIENT=<email>` ou relancez `QaAccountsSeeder`.

#### Le contraste est INDICATIF, il ne fait pas échouer

`qa:theme` calcule le rapport WCAG de chaque texte, dans les deux modes. Cette mesure a trouvé
deux vrais défauts — les prix invisibles de `/pricing`, les titres blancs sur blanc de `/login` —
mais elle garde des angles morts, et son décompte ne vaut pas un verdict :

| Ce qu'elle sait faire | Ce qu'elle ne sait pas |
|---|---|
| Composer les couches translucides (`.brio-glass` sur la nuit) | Échantillonner un dégradé : elle **écarte** l'élément |
| Ignorer ce qui fait moins de 4 px ou sort de l'écran | Suivre un texte peint par `background-clip: text` |
| Appliquer le seuil 3:1 au grand texte et au gras | Voir une image de fond derrière le texte |
| Écarter les emoji | — ils portent leurs propres couleurs, `color` ne s'y applique pas |

Deux faux positifs déjà payés : un bouton à `linear-gradient` mesuré contre le fond de la page
(1,06:1 annoncé, aucun défaut réel), et un lien d'évitement à `left: -9999px` compté comme visible.

Bloquez le service worker (`serviceWorkers: 'block'`) dans tout script Playwright de ce dossier :
sans cela il recharge le document en pleine mesure et détruit le contexte d'exécution.

### ⚠ Les pages ADMIN exigent de lever la 2FA

`Enforce2FA` détourne tout administrateur sans `two_factor_confirmed_at` vers son profil. Le
balayage suit la redirection, arrive sur une page pourvue de sa navigation, et échoue au critère
C5 — **pour les 70 pages admin à la fois**, quelle que soit leur mise en page réelle. Le symptôme
trompé : `c5_nav_chrome_absent: false` partout, et une URL finale en `/user/profile`.

Lancer donc le serveur avec la contrainte levée — on mesure de la mise en page, pas de
l'authentification :
```
ENFORCE_2FA_FOR_ADMINS=false php artisan serve
```

Ne PAS confirmer une 2FA sur le compte QA à la place : Fortify exige alors un code OTP à la
connexion, que le harnais ne sait pas produire, et le balayage se retrouve bloqué sur la page de
challenge (symptôme : un `<span>Cx</span>` du layout invité dans les coupables C3).

### La liste des pages se régénère

`storage/app/parity_webview.json` dérivait du registre — il lui manquait deux modules. Un balayage
vert sur une liste périmée ne dit rien de la page qu'on vient d'ajouter :
```
php artisan parity:webview-manifest
```
Writes `tools/visual-qa/out/report.json` + `tools/visual-qa/out/report.md`.

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
  `a.btn`, `.ui-btn`, `.brio-btn-*`), never inline text links. (`C2_MIN_HEIGHT=24`, `C2_NARROW=80`.)
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
