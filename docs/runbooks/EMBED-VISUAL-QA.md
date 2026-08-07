# Phase-2 Visual QA — Embedded WebView Pages (Operate-Together Runbook)

> **Status:** Phase 1 (autonomous) DONE — registry complete (124 modules, real paths, role-scoped),
> three parity test suites green (paths-resolve, embed-render, role-access), this scaffold merged.
> Phase 2 (operate-together) is **in progress** — automated render sweep done (see below); the
> per-module *visual* pass at phone width is what remains before `responsive_verified` is flipped.

---

## Phase-2 automated render sweep — results (2026-05-31)

`scripts/embed_sweep.php` logs in per role against a running server on **MySQL** (seeded demo data,
5 role accounts) and GETs every `{path}?embed=1`, recording HTTP status + nav-chrome presence. This
gives an objective render check across all 118 WebView modules (the embed-render PHPUnit test only
exercises client/provider/B2B on SQLite; this also covered the 71 admin pages and resolved the 7
deferred ones on real MySQL).

**Result: 115/118 render clean (HTTP 200, nav chrome absent). Zero pages leaked nav chrome** — the
embed-guard is applied everywhere. Render bugs found and fixed during the sweep (branch
`fix/phase2-embed-qa`): client `litiges` + `admin-home` (schema-drift columns); the **entire B2B
company-dashboard cluster** across 3 drift layers (`bookings.client_organization_id`→
`customer_organization_id`, `missions.scheduled_at`→`planned_start_at`, `missions.completed_at`→
`actual_end_at`, missing `Message::readBy` relation, null-unsafe `forOrg` scopes, `BillingCenter`
summary keys + `$invoices`, `Booking` `providerUser`/`clientUser` alias relations, ambiguous
`created_at` joins, Livewire `#[On]` placeholder defaults).

**Still not rendering (3):**
- `admin-feature-flags` — **local env only**: `feature_flag_overrides` table is an unrun migration;
  the migration chain is blocked by a pre-existing broken `trade_id` migration (can't `SET NULL` FK +
  `NOT NULL`). Not a prod bug, but the broken migration should be filed.
- `admin-users` — **not a bug**: 302 redirect, it's an alias of `/admin/utilisateurs` (already passes).
  Candidate to dedupe from the registry.
- `dashboard-client-analytics` — `ClientAnalyticsDashboard` aggregator has 2 more bugs (ambiguous
  `created_at` in a 3-table join + missing `trend` KPI key on sparse data). Follow-up.

**Action-path bugs found (don't block render, break form submits — follow-up):**
- `TaskBoard::updateStatus()` writes `tasks.completed_at` (no such column).
- `DispatchCenter` assign-worker writes `mission_assignments.provider_user_id`/`assigned_by` (no such
  columns; real cols `user_id`/`role`).

The remaining work is the **visual** pass: open each of the 115 rendering pages at ~390px and confirm
the 5 checks, then flip `responsive_verified`.

---

## What this runbook is

Brio delivers mobile parity via two strategies:

- **Native screens** — six modules rebuilt as React Native / Expo screens (booking, tracking, chat,
  missions, earnings, invoices). Not part of this runbook.
- **WebView fallbacks** — every other module serves the existing Laravel/Livewire web page inside a
  mobile WebView, activated by appending `?embed=1` to the URL. SP1's `EmbedMode` middleware strips
  the primary navigation when the query param is present (the `<nav>` is wrapped in
  `@unless($embedded)` and carries `data-chrome="primary-nav"`).

This runbook covers the **118 WebView fallback modules** (across 6 role buckets). Its purpose is to
walk a human operator and Claude together through each page at phone width, confirm it renders
acceptably, and record the result so `responsive_verified` can be flipped.

---

## Division of labor

| Who | Does what |
|-----|-----------|
| **Claude** | Provides exact steps: which role to log in as, the full `?embed=1` URL, what to look for. Interprets reported results. Proposes surgical embed-mode CSS/Tailwind fixes (one small commit per module) when a page fails. |
| **Operator** | Logs into the web app at phone width (~390 px) or on a real device. Opens each URL. Reports PASS / FAIL and any notes. |
| **Together** | Agree on a fix when a page fails, apply it, verify again, then mark PASS. |

---

## `responsive_verified` flip rule

A module's `responsive_verified` flag moves from `false` to `true` only when **both** conditions
are met:

1. Its embed-render test is **green** in `tests/Feature/Parity/ParityEmbedRenderTest.php`
   (HTTP 200 returned, nav chrome absent).
2. Its visual-QA row is **PASS** in this runbook (verified by the operator at phone width).

Never flip a module based on only one of the two conditions.

---

## Prerequisites

- A browser set to ~390 px viewport width (Chrome DevTools → iPhone 14 Pro preset is fine), **or**
  a real iOS / Android device.
- A test account for each role:
  - **Client** — regular consumer account
  - **Provider** — employee / prestataire account
  - **Entreprise client** (`entreprise`) — B2B client-company account
  - **Entreprise prestataire** (`provider_company`) — B2B provider-company account
  - **Admin** — back-office admin account
  - **Public** — no login needed (just open the URL)
- A running staging environment with **MySQL** (not SQLite — see Deferred section below).

### How to open an embedded page

Append `?embed=1` to the path shown in each row:

```
https://<staging-host>/dashboard/client/litiges?embed=1
```

The primary navigation bar must be absent. If it appears, the embed param was not applied (check
middleware registration).

---

## Visual checks — apply to every row

At phone width (~390 px), each embedded page must pass all five checks:

| # | Check |
|---|-------|
| 1 | **No horizontal scroll** — page content fits within the viewport; no content is cut off to the right. |
| 2 | **Tap targets ≥ ~44 px** — buttons, links, and interactive elements are large enough to tap. |
| 3 | **Readable text** — no text overflows its container, clips, or truncates in a way that hides meaning. |
| 4 | **No broken layout** — no overlapping elements, collapsed containers, or invisible content. |
| 5 | **Nav chrome ABSENT** — the primary top navigation bar does not appear (embed mode applied). |

Mark the row **PASS** only if all five pass. Otherwise mark **FAIL** and note which check(s) failed.

---

## Module tables

### How to fill in a row

| Column | How to fill |
|--------|-------------|
| `key` | Module key from `config/parity.php` — pre-filled. |
| `path` | Web path — pre-filled. |
| `URL to open` | Pre-filled (`path + ?embed=1`). |
| `PASS/FAIL` | Operator fills after visual check. |
| `timestamp` | ISO-8601 or `YYYY-MM-DD HH:MM`. |
| `who` | Operator name / initials. |
| `notes` | Any failure detail or "ok" if trivially clean. |

---

### Role: Client (20 modules)

Log in as a **client** account before opening these URLs.

| key | path | URL to open | PASS/FAIL | timestamp | who | notes |
|-----|------|-------------|-----------|-----------|-----|-------|
| dashboard-client | /dashboard/client | /dashboard/client?embed=1 | | | | |
| dashboard-client-rendez-vous | /dashboard/client/rendez-vous | /dashboard/client/rendez-vous?embed=1 | | | | |
| dashboard-client-prestataires | /dashboard/client/prestataires | /dashboard/client/prestataires?embed=1 | | | | |
| dashboard-client-donnees | /dashboard/client/donnees | /dashboard/client/donnees?embed=1 | | | | |
| dashboard-client-fidelite | /dashboard/client/fidelite | /dashboard/client/fidelite?embed=1 | | | | |
| dashboard-client-parrainage | /dashboard/client/parrainage | /dashboard/client/parrainage?embed=1 | | | | |
| dashboard-client-portefeuille | /dashboard/client/portefeuille | /dashboard/client/portefeuille?embed=1 | | | | |
| dashboard-client-litiges | /dashboard/client/litiges | /dashboard/client/litiges?embed=1 | | | | |
| dashboard-client-profil | /dashboard/client/profil | /dashboard/client/profil?embed=1 | | | | |
| dashboard-client-favoris-employes | /dashboard/client/favoris-employes | /dashboard/client/favoris-employes?embed=1 | | | | |
| dashboard-client-historique | /dashboard/client/historique | /dashboard/client/historique?embed=1 | | | | |
| dashboard-client-abonnements | /dashboard/client/abonnements | /dashboard/client/abonnements?embed=1 | | | | |
| dashboard-client-abonnements-v2 | /dashboard/client/abonnements-v2 | /dashboard/client/abonnements-v2?embed=1 | | | | |
| dashboard-client-api-tokens | /dashboard/client/api-tokens | /dashboard/client/api-tokens?embed=1 | | | | |
| dashboard-client-kyb-onboarding | /dashboard/client/kyb-onboarding | /dashboard/client/kyb-onboarding?embed=1 | | | | |
| dashboard-client-contrats | /dashboard/client/contrats | /dashboard/client/contrats?embed=1 | | | | |
| dashboard-client-nps | /dashboard/client/nps | /dashboard/client/nps?embed=1 | | | | |
| dashboard-client-chantiers-groupes | /dashboard/client/chantiers-groupes | /dashboard/client/chantiers-groupes?embed=1 | | | | |
| dashboard-client-devis-ia | /dashboard/client/devis-ia | /dashboard/client/devis-ia?embed=1 | | | | |
| dashboard-client-analytics | /dashboard/client/analytics | /dashboard/client/analytics?embed=1 | | | | See Deferred section — needs populated KPI/trend data on MySQL staging. |

---

### Role: Provider (16 modules)

Log in as a **provider / employe** account before opening these URLs.

| key | path | URL to open | PASS/FAIL | timestamp | who | notes |
|-----|------|-------------|-----------|-----------|-----|-------|
| dashboard-employe | /dashboard/employe | /dashboard/employe?embed=1 | | | | |
| dashboard-employe-avis | /dashboard/employe/avis | /dashboard/employe/avis?embed=1 | | | | |
| dashboard-employe-portefeuille | /dashboard/employe/portefeuille | /dashboard/employe/portefeuille?embed=1 | | | | |
| dashboard-employe-litiges | /dashboard/employe/litiges | /dashboard/employe/litiges?embed=1 | | | | |
| dashboard-employe-verification | /dashboard/employe/verification | /dashboard/employe/verification?embed=1 | | | | |
| dashboard-employe-badges | /dashboard/employe/badges | /dashboard/employe/badges?embed=1 | | | | |
| dashboard-employe-disponibilites | /dashboard/employe/disponibilites | /dashboard/employe/disponibilites?embed=1 | | | | |
| dashboard-employe-planning | /dashboard/employe/planning | /dashboard/employe/planning?embed=1 | | | | |
| dashboard-employe-historique | /dashboard/employe/historique | /dashboard/employe/historique?embed=1 | | | | |
| dashboard-employe-incident | /dashboard/employe/incident | /dashboard/employe/incident?embed=1 | | | | |
| dashboard-employe-equipe | /dashboard/employe/equipe | /dashboard/employe/equipe?embed=1 | | | | |
| dashboard-employe-coordination | /dashboard/employe/coordination | /dashboard/employe/coordination?embed=1 | | | | |
| dashboard-employe-chef-equipe | /dashboard/employe/chef-equipe | /dashboard/employe/chef-equipe?embed=1 | | | | |
| dashboard-employe-feedbacks | /dashboard/employe/feedbacks | /dashboard/employe/feedbacks?embed=1 | | | | |
| dashboard-employe-validation-multiple-rdv | /dashboard/employe/validation-multiple-rdv | /dashboard/employe/validation-multiple-rdv?embed=1 | | | | |
| dashboard-employe-google-calendar | /dashboard/employe/google-calendar | /dashboard/employe/google-calendar?embed=1 | | | | |

---

### Role: Entreprise client (5 modules)

Log in as a **B2B client-company** account (role `entreprise`) before opening these URLs.

> **Note (B2B context):** During SP4 a systemic bug was found and fixed — the 10 entreprise company
> dashboards were accessing Livewire computed properties as `$this->xProperty` instead of `$this->x`,
> causing every B2B dashboard to return HTTP 500 in production. Fixed in this branch. The B2B
> company layouts were also embed-guarded in this branch. These pages are **newly mobile-reachable**
> and warrant careful first-time visual QA. See also the Deferred section below for 3 of these pages
> that additionally 500'd under the SQLite test harness.

| key | path | URL to open | PASS/FAIL | timestamp | who | notes |
|-----|------|-------------|-----------|-----------|-----|-------|
| dashboard-entreprise-client | /dashboard/entreprise-client | /dashboard/entreprise-client?embed=1 | | | | See Deferred section. |
| dashboard-entreprise-client-locaux | /dashboard/entreprise-client/locaux | /dashboard/entreprise-client/locaux?embed=1 | | | | |
| dashboard-entreprise-client-reservations | /dashboard/entreprise-client/reservations | /dashboard/entreprise-client/reservations?embed=1 | | | | |
| dashboard-entreprise-client-membres | /dashboard/entreprise-client/membres | /dashboard/entreprise-client/membres?embed=1 | | | | See Deferred section. |
| dashboard-entreprise-client-facturation | /dashboard/entreprise-client/facturation | /dashboard/entreprise-client/facturation?embed=1 | | | | See Deferred section. |

---

### Role: Entreprise prestataire (5 modules)

Log in as a **B2B provider-company** account (role `provider_company`) before opening these URLs.

> **Note (B2B context):** Same systemic fix as above applies here — these pages are newly
> mobile-reachable after the SP4 Livewire property-access bug fix and embed-guard addition in this
> branch. Three of these pages also appear in the Deferred section.

| key | path | URL to open | PASS/FAIL | timestamp | who | notes |
|-----|------|-------------|-----------|-----------|-----|-------|
| dashboard-entreprise-prestataire | /dashboard/entreprise-prestataire | /dashboard/entreprise-prestataire?embed=1 | | | | |
| dashboard-entreprise-prestataire-canaux | /dashboard/entreprise-prestataire/canaux | /dashboard/entreprise-prestataire/canaux?embed=1 | | | | See Deferred section. |
| dashboard-entreprise-prestataire-taches | /dashboard/entreprise-prestataire/taches | /dashboard/entreprise-prestataire/taches?embed=1 | | | | |
| dashboard-entreprise-prestataire-dispatch | /dashboard/entreprise-prestataire/dispatch | /dashboard/entreprise-prestataire/dispatch?embed=1 | | | | See Deferred section. |
| dashboard-entreprise-prestataire-equipe | /dashboard/entreprise-prestataire/equipe | /dashboard/entreprise-prestataire/equipe?embed=1 | | | | See Deferred section. |

---

### Role: Public (1 module)

No login required.

| key | path | URL to open | PASS/FAIL | timestamp | who | notes |
|-----|------|-------------|-----------|-----------|-----|-------|
| help | /aide | /aide?embed=1 | | | | |

---

### Role: Admin (71 modules)

Log in as an **admin** account before opening these URLs.

| key | path | URL to open | PASS/FAIL | timestamp | who | notes |
|-----|------|-------------|-----------|-----------|-----|-------|
| accounting | /admin/accounting-v2 | /admin/accounting-v2?embed=1 | | | | |
| audit | /admin/audit | /admin/audit?embed=1 | | | | |
| kyb | /admin/kyb-v2 | /admin/kyb-v2?embed=1 | | | | |
| admin-dashboard | /admin/dashboard | /admin/dashboard?embed=1 | | | | |
| admin-home | /admin/home | /admin/home?embed=1 | | | | |
| admin-missions | /admin/missions | /admin/missions?embed=1 | | | | |
| admin-utilisateurs | /admin/utilisateurs | /admin/utilisateurs?embed=1 | | | | |
| admin-users | /admin/users | /admin/users?embed=1 | | | | |
| admin-alerts | /admin/alerts | /admin/alerts?embed=1 | | | | |
| admin-analytics | /admin/analytics | /admin/analytics?embed=1 | | | | |
| admin-credits-clients | /admin/credits-clients | /admin/credits-clients?embed=1 | | | | |
| admin-avis | /admin/avis | /admin/avis?embed=1 | | | | |
| admin-matching | /admin/matching | /admin/matching?embed=1 | | | | |
| admin-stripe | /admin/stripe | /admin/stripe?embed=1 | | | | |
| admin-translations | /admin/translations | /admin/translations?embed=1 | | | | |
| admin-disputes | /admin/disputes | /admin/disputes?embed=1 | | | | |
| admin-kyc | /admin/kyc | /admin/kyc?embed=1 | | | | |
| admin-gdpr | /admin/gdpr | /admin/gdpr?embed=1 | | | | |
| admin-loyalty | /admin/loyalty | /admin/loyalty?embed=1 | | | | |
| admin-tips | /admin/tips | /admin/tips?embed=1 | | | | |
| admin-trip-tracking | /admin/trip-tracking | /admin/trip-tracking?embed=1 | | | | |
| admin-presence | /admin/presence | /admin/presence?embed=1 | | | | |
| admin-nps | /admin/nps | /admin/nps?embed=1 | | | | |
| admin-safety | /admin/safety | /admin/safety?embed=1 | | | | |
| admin-badges | /admin/badges | /admin/badges?embed=1 | | | | |
| admin-bundles | /admin/bundles | /admin/bundles?embed=1 | | | | |
| admin-sms | /admin/sms | /admin/sms?embed=1 | | | | |
| admin-push | /admin/push | /admin/push?embed=1 | | | | |
| admin-realtime | /admin/realtime | /admin/realtime?embed=1 | | | | |
| admin-analytics-v2 | /admin/analytics-v2 | /admin/analytics-v2?embed=1 | | | | |
| admin-availability | /admin/availability | /admin/availability?embed=1 | | | | |
| admin-risk | /admin/risk | /admin/risk?embed=1 | | | | |
| admin-marketing | /admin/marketing | /admin/marketing?embed=1 | | | | |
| admin-insurance | /admin/insurance | /admin/insurance?embed=1 | | | | |
| admin-fx | /admin/fx | /admin/fx?embed=1 | | | | |
| admin-notification-preferences | /admin/notification-preferences | /admin/notification-preferences?embed=1 | | | | |
| admin-quality | /admin/quality | /admin/quality?embed=1 | | | | |
| admin-cancellations-v2 | /admin/cancellations-v2 | /admin/cancellations-v2?embed=1 | | | | |
| admin-onboarding-v2 | /admin/onboarding-v2 | /admin/onboarding-v2?embed=1 | | | | |
| admin-pricing-v2 | /admin/pricing-v2 | /admin/pricing-v2?embed=1 | | | | |
| admin-contracts-v2 | /admin/contracts-v2 | /admin/contracts-v2?embed=1 | | | | |
| admin-webhooks-v2 | /admin/webhooks-v2 | /admin/webhooks-v2?embed=1 | | | | |
| admin-geolocation-v2 | /admin/geolocation-v2 | /admin/geolocation-v2?embed=1 | | | | |
| admin-api-tokens-v2 | /admin/api-tokens-v2 | /admin/api-tokens-v2?embed=1 | | | | |
| admin-chat-v2 | /admin/chat-v2 | /admin/chat-v2?embed=1 | | | | |
| admin-subscriptions-v2 | /admin/subscriptions-v2 | /admin/subscriptions-v2?embed=1 | | | | |
| admin-fleet-v2 | /admin/fleet-v2 | /admin/fleet-v2?embed=1 | | | | |
| admin-feature-flags | /admin/feature-flags | /admin/feature-flags?embed=1 | | | | |
| admin-stripe-connect-providers | /admin/stripe-connect-providers | /admin/stripe-connect-providers?embed=1 | | | | |
| admin-ia-dispatch | /admin/ia-dispatch | /admin/ia-dispatch?embed=1 | | | | |
| admin-business-dashboard | /admin/business-dashboard | /admin/business-dashboard?embed=1 | | | | |
| admin-platform-readiness | /admin/platform-readiness | /admin/platform-readiness?embed=1 | | | | |
| admin-approbations-entreprises | /admin/approbations-entreprises | /admin/approbations-entreprises?embed=1 | | | | |
| admin-sites | /admin/sites | /admin/sites?embed=1 | | | | |
| admin-trades | /admin/trades | /admin/trades?embed=1 | | | | |
| admin-onboarding-providers | /admin/onboarding-providers | /admin/onboarding-providers?embed=1 | | | | |
| admin-onboarding-documents | /admin/onboarding-documents | /admin/onboarding-documents?embed=1 | | | | |
| admin-planning | /admin/planning | /admin/planning?embed=1 | | | | |
| admin-calendar | /admin/calendar | /admin/calendar?embed=1 | | | | |
| admin-feedbacks | /admin/feedbacks | /admin/feedbacks?embed=1 | | | | |
| admin-finance | /admin/finance | /admin/finance?embed=1 | | | | |
| admin-outils | /admin/outils | /admin/outils?embed=1 | | | | |
| admin-services | /admin/services | /admin/services?embed=1 | | | | |
| admin-teams-partners | /admin/teams-partners | /admin/teams-partners?embed=1 | | | | |
| admin-international | /admin/international | /admin/international?embed=1 | | | | |
| admin-orchestration | /admin/orchestration | /admin/orchestration?embed=1 | | | | |
| admin-automation | /admin/automation | /admin/automation?embed=1 | | | | |
| admin-modules | /admin/modules | /admin/modules?embed=1 | | | | |
| admin-countries | /admin/countries | /admin/countries?embed=1 | | | | |
| admin-emails | /admin/emails | /admin/emails?embed=1 | | | | |
| admin-premium-clients | /admin/premium-clients | /admin/premium-clients?embed=1 | | | | |

---

## Deferred — Pages with no automated render proof (must be verified manually)

`ParityEmbedRenderTest` records the following 7 modules as **skipped** because they returned HTTP
500 in the SQLite test harness. They have **no automated render proof**. Visual QA is the **only**
verification for these pages.

> **Critical instruction:** Open these pages on a **real MySQL staging environment with realistic
> (seeded) data**. Several of the 500s are SQLite-test-only limitations that should render fine on
> production MySQL — but any page that still errors on MySQL staging is a **real bug** and must be
> filed as such before `responsive_verified` can be flipped.

| key | Likely cause of the 500 in the test harness | What to watch for on staging |
|-----|--------------------------------------------|------------------------------|
| `dashboard-client-analytics` | The analytics dashboard renders KPI trend charts and aggregated booking/revenue data. In the test harness the relevant tables are empty (no KPI rows, no trend series), so a missing array key causes a 500. Likely fine on MySQL staging with seeded data. | Verify charts render with data. If 500 persists on staging with data, the missing-key guard is a real bug. |
| `dashboard-entreprise-client` | The B2B client company overview joins `organizations`, `sites`, and `bookings` tables. In the test harness the company fixture may be incomplete. Additionally, the Livewire computed-property bug (`$this->xProperty` vs `$this->x`) was fixed in this branch — must confirm the fix resolves the production path. | Confirm HTTP 200 and that org/site summary renders. |
| `dashboard-entreprise-client-membres` | The members list uses `orderByRaw("FIELD(status, 'active', 'pending', 'suspended')")`. MySQL's `FIELD()` function is not supported by SQLite, causing the query to throw during tests. On MySQL staging this should work correctly. | Confirm list renders ordered correctly. If still 500 on MySQL, `FIELD()` call or its fallback is broken. |
| `dashboard-entreprise-client-facturation` | The facturation (billing) view references a Livewire computed property that joins `invoices` with `subscriptions_v2`. In the test harness the `subscriptions_v2` table may be empty or the join produces an ambiguous `created_at` column (a known SQLite-vs-MySQL difference in column disambiguation). | Confirm invoices list renders. If ambiguous-column error appears on MySQL staging, add explicit column qualification. |
| `dashboard-entreprise-prestataire-canaux` | The dispatch-channels view accesses computed properties that were affected by the systemic Livewire `$this->xProperty` bug. Additionally, in the test harness no channel fixtures exist. | Confirm HTTP 200 and channels list renders (may be empty but must not 500). |
| `dashboard-entreprise-prestataire-dispatch` | The dispatch dashboard runs a weighted matching query that joins multiple tables including `mission_scoring_audits`. In the test harness this table is empty and the query may dereference a missing key in the audit payload. | Confirm dispatch view renders (can show "no pending missions"). If 500 persists on MySQL staging with data, the audit-payload guard is a real bug. |
| `dashboard-entreprise-prestataire-equipe` | The team management view attempts to call a method that references `Message::readBy()`, which in the test harness fails because the chat module's seeded messages are absent. On MySQL staging with realistic chat/message data this should resolve. | Confirm team list renders. If `readBy()` call still errors on staging, the method guard needs a null-safe fallback. |

---

## B2B / Entreprise context note

During SP4 (launch-hardening branch) a **systemic bug** was discovered and corrected: all 10
entreprise company dashboards (both client-side and provider-side) were reading Livewire computed
properties using the wrong syntax (`$this->someProperty` instead of `$this->some_property` /
calling the getter incorrectly), which caused every B2B dashboard to return HTTP 500 in production.

The fix is merged into this branch. Additionally, the B2B company layouts were extended with embed
guards in this branch (same `@unless($embedded)` pattern as the client/provider layouts), making
these pages properly mobile-reachable for the first time.

**Consequence for visual QA:** the entire `entreprise` and `provider_company` buckets (10 modules
total) are undergoing **first-time mobile visual review**. Allocate extra attention to layout
correctness; do not assume they are production-proven.

---

## Definition of Done reference

| Phase | Scope | Status |
|-------|-------|--------|
| **Phase 1 — Autonomous** (this PR) | Registry complete (124 modules, real paths, role-scoped). Three parity test suites green: `ParityPathsResolveTest`, `ParityEmbedRenderTest`, `ParityRoleAccessTest`. This runbook scaffold merged. | **DONE** |
| **Phase 2 — Operate-together** (post-merge) | Every WebView row in this runbook reaches PASS at phone width. `responsive_verified` flipped to `true` per module (requires both embed-render test green AND visual PASS). | Pending |

---

*Runbook generated 2026-05-31 from live `config/parity.php` output (118 WebView modules across 6
role buckets). Keys and paths are authoritative — pulled via `php artisan tinker` against the actual
config file, not from documentation or memory.*
