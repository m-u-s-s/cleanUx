# Launch Hardening — Design Spec

**Date:** 2026-05-30
**Status:** Approved design (pre-implementation-plan)
**Sub-project:** 2 of 4 in the "Total channel parity → launch" program
**Branch:** `feat/launch-hardening` (off `main`)

---

## Context & goal

Sub-project 2 of the launch program. Unlike sub-project 1 (which *built* the parity
foundation), this sub-project **validates, instruments, and proves the existing money +
mission "transaction spine" is production-trustworthy** — it does not reimplement payment
logic. The driving goal is "launch to real users," so the bar is: *rule out the specific ways
this system could lose, leak, or double-spend money,* across both web and mobile surfaces.

The deliverable has two halves, decided during brainstorming:
- **In-repo artifacts** I build autonomously (tests, instrumentation, scripts, config) —
  CI-verifiable on SQLite + a faked Stripe SDK.
- **Live drills** run *operate-together*: I produce exact commands + pass/fail assertions;
  the user executes the privileged/credentialed steps (deploy, secrets, Stripe dashboard,
  backup access) against **real staging + Stripe test-mode**, and pastes back results.

### Infrastructure (confirmed already provisioned)

- Production-like **staging** (MySQL + Redis + Reverb + queue workers), reachable via the
  existing `deploy-staging.yml` SSH target.
- **Stripe test-mode** API keys + at least one test **Connect** account.
- **Sentry** live (receiving events from a deployed environment).
- **Backups** configured (restorable snapshot exists).

### E2E depth (decided)

**Backend spine** only — one true E2E through the service/API layer that both surfaces call.
Surfaces are thin clients over this engine, so proving the engine proves the money path for
both. No UI automation (Dusk/Maestro) in this sub-project.

### Money-authority decision (decided)

For the cancellation money decision, **`CancellationV2\CancellationEngine` is authoritative**
(`quote`/`execute`). The legacy `CancelBookingService`/`CancellationFeeCalculator` is NOT the
source of truth for refund amounts. F9's test and the D4 drill assert against CancellationV2.

---

## The transaction spine (the thing under test)

Grounded in the real services:
- `MissionPaymentService::authorize(Booking, paymentMethodId): PaymentIntent` — pre-auth.
- `MissionPaymentService::capture(Booking): ?PaymentIntent`, `markFailed(Booking)`.
- `StripeConnectPaymentService::captureMissionPayment(Mission): ?ProviderPayout`,
  `createProviderPayout(Mission, Booking)`, `refundMissionPayment(...)`, `syncPaymentIntent(Booking)`.
- `ProviderWalletService`: `recordEarning`, `recordTip`, `recordRefundClawback`, `recordPayout`,
  `markPayoutCleared`, `reversePayout`, `requestWithdraw`, `balance`, `transactionHistory`.
- `StripeReconciliationService::run(...)`.
- `Webhooks\StripeWebhookEventProcessor::process(StripeWebhookEvent)` — idempotent, handles
  `payment_intent.succeeded`, `payment_intent.payment_failed`, `charge.refunded`,
  `transfer.created`, `account.updated`.
- `CancellationV2\CancellationEngine::quote/execute`, `CancellationIntegrationsRunner::run`.
- `Tips\TipService` (`charged → paid_out` lifecycle, idempotent, loyalty bonus).

**Canonical happy path (the E2E asserts every hop):**
1. Client books → `authorize()` creates a **pre-auth** PaymentIntent with
   `transfer_data.destination` = provider Connect account + platform `application_fee`;
   booking `payment_status = authorized`.
2. Provider accepts → mission created/assigned.
3. QR-start → mission `in_progress`.
4. QR-end → mission `completed`, booking `completed`.
5. `captureMissionPayment(mission)` captures the PI → Stripe auto-transfers to the provider →
   `ProviderPayout` created → wallet `recordEarning` + `recordPayout` write the immutable ledger.
6. Webhooks (`payment_intent.succeeded`, `transfer.created`) arrive **async**, processed
   **idempotently**, keeping DB ↔ Stripe in sync.
7. `StripeReconciliationService::run()` confirms DB ledger == Stripe truth.

---

## Failure-mode register (F1–F10) — must be *proven impossible* before launch

Each becomes a test, and where relevant a monitor and a runbook line.

| # | Failure mode | Why launch-blocking | Primary surface |
|---|---|---|---|
| F1 | Double-capture of a PI | Charges client twice / over-transfers | `MissionPaymentService::capture` / `captureMissionPayment` |
| F2 | Lost / replayed / out-of-order webhook | DB drifts from Stripe; ghost or missing payouts | `StripeWebhookEventProcessor` |
| F3 | Refund-after-capture mishandled | Money out with no wallet clawback | `refundMissionPayment` + `recordRefundClawback` |
| F4 | Cross-tenant money leak | Client/org A sees or acts on B's booking/payment/payout | money-path endpoints |
| F5 | Payout to wrong Connect account | Provider B paid for provider A's mission | `createProviderPayout` `transfer_data.destination` |
| F6 | Stuck mission holding pre-auth | Client funds held indefinitely; no capture/release | mission state + capture |
| F7 | Declined/expired pre-auth at capture | Mission done, provider unpaid, no alert | `capture` failure path |
| F8 | Reconciliation divergence undetected | Silent money discrepancy | `StripeReconciliationService` |
| F9 | Cancellation-window refund wrong/double-applied | Tiered fee miscalc, refund of uncaptured PI, refund-after-capture without clawback, or legacy-vs-V2 disagreement | `CancellationV2\CancellationEngine` (authoritative) + `CancellationIntegrationsRunner` |
| F10 | Tip charged but not paid out / double-charged / survives cancellation | Client charged with no provider credit, or double charge | `TipService` (`confirmCharge`/`markPaidOut`) + wallet |

---

## In-repo artifacts (deliverables) + boundaries

Six focused, independently-testable units. Tests run on SQLite with the Stripe SDK **faked at
the boundary** (test-mode-shaped responses); no live calls in CI.

1. **`MoneyMissionSpineTest`** — the full-spine happy-path E2E. Drives the real services
   book → authorize → accept → QR-start → QR-end → captureMissionPayment → ProviderPayout →
   wallet recordEarning/recordPayout → reconcile, asserting DB state + ledger balance at every
   hop. *Depends on:* spine services only. No new production code. Establishes shared fixtures.

2. **`MoneyFailureModesTest`** — one test per F1–F10 proving the system rejects/handles the
   mode. Where a test surfaces a real bug, **the minimal fix is in scope** (scoped to that
   failure mode, each with a regression test). *Depends on:* spine services.

3. **`MoneyPathIsolationTest`** — cross-role (client/provider/admin) + cross-organization
   access control on every money-path endpoint, including the sub-project-1 WebView/parity
   surfaces. Proves no read or mutation crosses a tenant boundary. *Depends on:* existing authz
   + role/org factories.

4. **`SpineHealthCheck` + business alerts** — extends `HealthCheckController` /
   `ProductionHealthReport` to assert spine-critical dependencies (DB, Redis, queue
   depth/worker liveness, Stripe reachability, Reverb), and adds Sentry-wired business-event
   alert emitters: failed pre-auth at capture (F7), payout failure (F5/F7), webhook backlog
   (F2), stuck mission holding funds (F6), reconciliation divergence (F8). *Interface:* a health
   endpoint payload + named alert emitters (each unit-verified). *Depends on:* Sentry config,
   queue, health controller.

5. **`backup:restore-drill`** — an artisan command that locates a backup, restores it to a
   scratch database, runs an integrity assertion (row counts + spine-ledger consistency), and
   reports RTO/RPO. Idempotent; never touches prod data. *Depends on:* DB config.

6. **Production-parity config + `config:parity-check`** — a corrected `.env` template (Redis
   queue + workers, Redis cache, async webhook processing) and a command that asserts a running
   environment matches the production profile (fails loudly if `queue=sync` / `cache=file`
   where it shouldn't be). *Depends on:* config. Pure inspection.

**Boundary discipline:** #1–#3 are tests (zero/minimal prod change); #4–#6 add small, focused
ops code. No artifact rebuilds payment logic. A `GO-LIVE-RUNBOOK.md` is scaffolded alongside
(drill sections empty, filled in Phase 2).

---

## Live drill playbook (operate-together)

Division of labor: **I produce exact commands, expected outputs, and pass/fail assertions; the
user executes the privileged steps and pastes back results.** All drills are
test-mode / scratch-DB / **non-destructive to prod**. Results recorded in `GO-LIVE-RUNBOOK.md`
(each with PASS/FAIL + timestamp + who ran it).

| Drill | Pairs with | User executes | Assertion |
|---|---|---|---|
| D1 — Staging smoke + parity | #6, #4 | Deploy branch → migrate → `config:parity-check` + health endpoint | MySQL/Redis/queue-workers/Reverb green; staging ≈ prod |
| D2 — Stripe test-mode spine | #1 | Trigger a real test-mode booking through to completion on staging | pre-auth PI (Connect destination + app fee) → capture → transfer on test Connect account → `reconcile` shows DB == Stripe |
| D3 — Webhook resilience | #2 (F2) | `stripe trigger` / resend / out-of-order via Stripe CLI | idempotent DB outcomes; reconciliation self-heals a missed event |
| D4 — Refund + cancel + tip | #2 (F3/F9/F10) | Cancel inside vs outside fee window; post-capture refund; tip | refund matches CancellationV2 quote; wallet clawback fires; tip `charged→paid_out` reconciles; no double-charge |
| D5 — Monitoring alert | #4 | Induce each synthetic failure (stuck mission, forced payout failure) | the Sentry alert actually fires and is actionable |
| D6 — Restore drill | #5 | Point `backup:restore-drill` at a real snapshot → scratch DB | true RTO/RPO measured; integrity + spine-ledger consistency pass |

**Prerequisites the user supplies at D2/D3:** Stripe CLI installed, a test Connect account id,
and how staging triggers a booking (UI vs API/seeder) so the trigger can be scripted precisely.

---

## Sequencing

**Phase 1 (in-repo, autonomous, CI-verifiable, in order):**
#1 full-spine E2E → #2 failure-mode tests (+ minimal fixes) → #3 isolation tests →
#4 health + alerts → #5 restore-drill command → #6 parity config + check.
(`GO-LIVE-RUNBOOK.md` scaffolded alongside.)

**Phase 2 (live, operate-together, ordered, each gates the next):**
D1 → D2 → D3 → D4 → D5 → D6.

---

## Definition of Done

1. Full-spine E2E + F1–F10 + isolation tests **green in CI** (SQLite + faked Stripe), **0 skips**.
2. Every bug surfaced by F1–F10 is **fixed with a regression test** (or explicitly accepted
   with documented rationale).
3. `SpineHealthCheck` reports all spine dependencies; each business alert emitter is unit-verified.
4. `backup:restore-drill` runs **green on a real snapshot**, with recorded RTO/RPO.
5. `config:parity-check` **passes on staging** (MySQL + Redis + queue workers + Reverb confirmed).
6. `GO-LIVE-RUNBOOK.md` complete: **D1–D6 all recorded PASS** against real staging + Stripe
   test-mode, each with timestamp + who ran it.
7. Cancellation money-authority pinned to **CancellationV2** and documented (this spec).

**Launch-ready = items 1–7 all true.** Phase 1 is fully autonomous; Phase 2 is paired live work.

---

## Scope boundaries

**In scope:** the six artifacts; minimal bug fixes surfaced by F1–F10; the live drills; the
runbook. Focus is the **money + mission spine only**.

**Out of scope:** reimplementing payment logic; UI-level E2E (Dusk/Maestro); hardening of
non-spine modules; provisioning infrastructure (already exists); merging sub-project 1 (separate
PR — isolation tests reference its surfaces and assume it is present on the implementation base).

**Base-branch note:** because `MoneyPathIsolationTest` (#3) references sub-project-1's parity
surfaces, the implementation base should be `feat/parity-foundation` (or `main` after it
merges), not bare `main`. To be resolved at writing-plans time.
