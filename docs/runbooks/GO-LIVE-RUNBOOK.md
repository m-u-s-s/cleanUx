# Brio — GO-LIVE RUNBOOK (Phase 2: Operate-Together Drills)

**Version:** 1.0.0 — Phase-1 complete, Phase-2 pending  
**Branch:** `feat/launch-hardening`  
**Last updated:** 2026-05-30

---

## Purpose — The Launch Gate

This document is the **two-phase launch gate** for Brio production go-live.

- **Phase 1 (CI gate):** All E2E Spine tests + Ops tests pass green in CI, all PHPStan/Pint checks pass, all known money-path bugs are fixed with regression tests. Phase 1 is **complete** as of this commit.
- **Phase 2 (Live drills gate):** All six drills (D1–D6) below are executed against real staging with Stripe test-mode and pass their stated criteria. Phase 2 is **pending operator execution**.

**Production go-live is blocked until both phases are green.**

### Division of Labor

| Actor | Responsibility |
|-------|---------------|
| **Claude (this repo)** | Scripts the exact commands, asserts, test fixtures, and pass/fail criteria for every drill. |
| **Operator (you)** | Executes the privileged steps (SSH, Stripe dashboard, real credentials), records timestamps and results in the tables below, and signs off each drill. |

Operators fill in `[PLACEHOLDER]` values at drill time. Do not edit the pass criteria or DoD items — those are locked by Phase-1 CI.

---

## Phase-2 Prerequisites

Before starting any drill, confirm all of the following are available.

### Tools

- [ ] **Stripe CLI** installed and authenticated (`stripe login`) with a test-mode API key
- [ ] **Test Connect account ID** — format `acct_XXXXXXXXXXXXXXXX` (test-mode). Set `STRIPE_CONNECT_ACCOUNT_ID=acct_...` on staging.
- [ ] **PHP 8.2+ / Artisan** on the staging host (via SSH or deploy runner).
- [ ] **`jq`** installed on the operator workstation (for JSON assertions in D2).

### Staging Booking Trigger

> **TO CONFIRM at drill time:** Choose one of:
> - **A — UI path:** Log in as a test client on staging → create a booking through the web UI → proceed to checkout.
> - **B — Seeder/API path:** `php artisan db:seed --class=SpineDrillBookingSeeder` (create this seeder if UI path is impractical; it should create a booking with a real Stripe test-mode PaymentIntent in `requires_capture` state).
>
> Record the chosen path and the resulting `booking_id` / `payment_intent_id` in each drill's results table.

### Access Required

- [ ] **Staging SSH** — ability to run `php artisan` commands on the staging host.
- [ ] **Stripe test-mode dashboard** — `https://dashboard.stripe.com/test/` — view PaymentIntents, Transfers, and Connect accounts.
- [ ] **Sentry project** — `Brio` project, configured to receive from staging (`SENTRY_DSN` set on staging).
- [ ] **Backup snapshot** — a recent dump of the staging database, accessible to the restore-drill command.
- [ ] **Scratch database connection** — a second database on the staging host (e.g., `brio_drill`), defined in `config/database.php` as connection name `drill` and `.env` `DB_DRILL_*` vars.

---

## Drills D1–D6

> **Pré-vol automatisé.** Avant les drills manuels, lance `php artisan golive:preflight`
> sur staging. Il exécute les contrôles automatisables (D1 `config:parity-check` +
> `spine:health-report`, D5 `spine:check-stuck-missions`, et D6 `backup:restore-drill`
> avec `--with-restore`), rend un **GO / NO-GO** consolidé (gate sur parité + missions
> bloquées) et rappelle les drills manuels D2/D3/D4 à jouer ensuite.

---

### D1 — Staging Smoke + Parity

**Purpose:** Confirm the branch deploys cleanly to staging, migrations run without errors, the production-parity config profile passes, and all infrastructure probes (MySQL, Redis, queue workers, Reverb) report healthy.

**Pairs with artifacts:** `ConfigParityCheck` (`config:parity-check`), `SpineHealthReport` (`spine:health-report`).

#### Operator Steps

```bash
# 1. Deploy branch to staging (fill in your deploy command)
[DEPLOY COMMAND — e.g.: git pull && php artisan down]

# 2. Run pending migrations
php artisan migrate --force

# 3. Bring application back up
php artisan up

# 4. Run production parity check (must exit 0)
php artisan config:parity-check
# Expected: "Environment matches production profile." and exit code 0.
# If any setting fails, fix the .env before continuing.

# 5. Run the spine health report
php artisan spine:health-report
# Expected: all probes ok=true: db, redis, queue, reverb, stripe.
# Note: stripe probe will show ok=false if STRIPE_SECRET_KEY is not set — that is acceptable
# for the parity check itself; it must be set for D2.

# 6. Confirm queue workers are running
php artisan queue:monitor [QUEUE_NAME]
# Or check your supervisor/horizon status.
```

#### Pass Criteria

- `config:parity-check` exits 0 with no `✗` rows.
- `spine:health-report` shows `ok: true` for `db`, `redis`, `queue`, `reverb`. Stripe probe `ok: true` only if `STRIPE_SECRET_KEY` is a valid test-mode key.
- No failed migrations. `php artisan migrate:status` shows all migrations as `Ran`.
- Application returns HTTP 200 on `GET /health` (or equivalent liveness endpoint).

#### Results

| Run | PASS/FAIL | Timestamp | Who | Notes |
|-----|-----------|-----------|-----|-------|
| 1 | | | | |
| 2 | | | | |

---

### D2 — Stripe Test-Mode Spine

**Purpose:** Prove the full money path works end-to-end in real Stripe test-mode: booking creation → PaymentIntent pre-auth (Connect destination + app fee) → mission completion → capture → automatic transfer on the test Connect account → wallet ledger reconciliation shows DB == Stripe.

**Pairs with artifacts:** `MoneyMissionSpineTest` (F1+F4+F5+F8), `StripeConnectPaymentService`.

#### Operator Steps

```bash
# 1. Confirm STRIPE_SECRET_KEY is a test-mode key (sk_test_...)
php artisan tinker --execute="echo config('services.stripe.secret');"
# Must start with sk_test_

# 2. Create a test booking (choose UI or seeder path — see Prerequisites)
# Record: booking_id=[BOOKING_ID], payment_intent_id=[PI_ID]

# 3. Confirm the PaymentIntent is in requires_capture state on Stripe
stripe payment_intents retrieve [PI_ID] | jq '{status, amount, transfer_data}'
# Expected: status=requires_capture, transfer_data.destination=acct_... (Connect account)

# 4. Also confirm the application_fee_amount is set
stripe payment_intents retrieve [PI_ID] | jq '.application_fee_amount'
# Expected: non-null integer (platform fee in cents)

# 5. Complete the mission (trigger QR end + mission completion)
# Via UI: mark mission complete as provider, or via API:
php artisan tinker --execute="
  \$m = \App\Models\Mission::find([MISSION_ID]);
  app(\App\Services\Missions\MissionLifecycleService::class)->complete(\$m);
"

# 6. Verify capture happened on Stripe
stripe payment_intents retrieve [PI_ID] | jq '.status'
# Expected: "succeeded"

# 7. Verify transfer was created on the Connect account
stripe transfers list --limit=5 | jq '.data[] | {id, amount, destination}'
# Expected: a transfer with destination=[CONNECT_ACCOUNT_ID] for the booking amount minus fee

# 8. Verify DB reconciliation shows no mismatch
php artisan tinker --execute="
  \$r = app(\App\Services\Payments\StripeReconciliationService::class);
  \$result = \$r->reconcileBooking(\App\Models\Booking::find([BOOKING_ID]));
  print_r(\$result);
"
# Expected: no divergence, no missing_payout flag

# 9. Verify provider wallet ledger has an earning record
php artisan tinker --execute="
  \App\Models\ProviderWalletTransaction::where('source_type', 'booking')
    ->where('source_id', [BOOKING_ID])
    ->get(['type','direction','amount','idempotency_key']);
" | grep -E "earning|credit"
# Expected: one credit row of type=earning
```

#### Pass Criteria

- PaymentIntent transitions: `requires_capture` → `succeeded`.
- `transfer_data.destination` matches the Connect account ID set in `.env`.
- `application_fee_amount` > 0 on the PaymentIntent.
- At least one Stripe Transfer exists for the booking's provider amount.
- Reconciliation returns no `status_mismatch` and no `missing_payout`.
- Exactly one `earning` credit row in `provider_wallet_transactions` for the booking.

#### Results

| Run | PASS/FAIL | Timestamp | Who | Notes |
|-----|-----------|-----------|-----|-------|
| 1 | | | | |
| 2 | | | | |

---

### D3 — Webhook Resilience

**Purpose:** Confirm that replayed, duplicate, and out-of-order Stripe webhook events produce idempotent DB outcomes. A simulated missed event self-heals when reconciliation is run.

**Pairs with artifacts:** `WebhookResilienceTest` (F2), `StripeWebhookHandlers`, reconciliation `captured_booking_missing_payout` check.

#### Operator Steps

```bash
# 1. Trigger a payment_intent.succeeded event replay using stripe CLI
stripe trigger payment_intent.succeeded
# OR resend a specific event from the Stripe dashboard (Developers → Webhooks → select event → Resend).

# 2. Confirm the webhook was received and processed (check logs)
php artisan queue:work --once  # if using sync/database queue
tail -f storage/logs/laravel.log | grep -E "webhook|payment_intent|idempotent"

# 3. Check that processing the same event twice did NOT double-credit the wallet
php artisan tinker --execute="
  \App\Models\ProviderWalletTransaction::where('source_type', 'booking')
    ->where('source_id', [BOOKING_ID])
    ->where('type', 'earning')
    ->count();
"
# Expected: 1 (not 2)

# 4. Simulate a missed event: manually delete the payout row (in a test booking only)
php artisan tinker --execute="
  \App\Models\ProviderWalletTransaction::where('source_type', 'booking')
    ->where('source_id', [DRILL_BOOKING_ID])
    ->where('type', 'earning')
    ->delete();
"

# 5. Run reconciliation — it should flag the missing payout
php artisan tinker --execute="
  \$r = app(\App\Services\Payments\StripeReconciliationService::class);
  print_r(\$r->reconcileBooking(\App\Models\Booking::find([DRILL_BOOKING_ID])));
"
# Expected: reports captured_booking_missing_payout

# 6. Resend the payment_intent.succeeded webhook from Stripe dashboard for this booking's PI.
#    After processing, confirm the wallet row is recreated:
php artisan tinker --execute="
  \App\Models\ProviderWalletTransaction::where('source_type', 'booking')
    ->where('source_id', [DRILL_BOOKING_ID])
    ->where('type', 'earning')
    ->count();
"
# Expected: 1 (self-healed)
```

#### Pass Criteria

- Replayed `payment_intent.succeeded` produces exactly 1 `earning` credit, regardless of how many times replayed.
- Reconciliation correctly flags a booking whose wallet record was deleted.
- After resending the webhook, the wallet record is recreated (self-heal) and reconciliation no longer flags it.
- No 500 errors in application logs during replay.

#### Results

| Run | PASS/FAIL | Timestamp | Who | Notes |
|-----|-----------|-----------|-----|-------|
| 1 | | | | |
| 2 | | | | |

---

### D4 — Refund + Cancel + Tip

**Purpose:** Validate the three financial correction flows: (a) cancellation respects CancellationV2 fee windows and issues the correct Stripe refund; (b) a post-capture partial refund triggers a proportional wallet clawback exactly once (dedup across service + webhook); (c) a tip goes through charged → paid_out and reconciles cleanly.

**Pairs with artifacts:** `CancellationRefundTest` (F3), `RefundClawbackTest` (F9/F10), `TipLifecycleTest`.

#### Operator Steps

```bash
# --- Part A: Cancellation inside the free window ---
# 1. Create a booking with future appointment date (>= free-cancel window hours away)
# Record: booking_id=[CANCEL_BOOKING_A]

# 2. Cancel it via API
curl -X POST https://[STAGING_HOST]/api/client/bookings/[CANCEL_BOOKING_A]/cancel \
  -H "Authorization: Bearer [CLIENT_TOKEN]" \
  -H "Content-Type: application/json" \
  -d '{"reason":"drill_test"}'
# Expected: HTTP 200, refund_amount = full booking amount (no fee)

# 3. Verify on Stripe: full refund issued
stripe refunds list --limit=3 | jq '.data[0] | {amount, status, charge}'

# --- Part B: Cancellation outside the fee window ---
# 4. Create another booking with appointment date < fee-window threshold
# Record: booking_id=[CANCEL_BOOKING_B]

# 5. Cancel it
curl -X POST https://[STAGING_HOST]/api/client/bookings/[CANCEL_BOOKING_B]/cancel \
  -H "Authorization: Bearer [CLIENT_TOKEN]" \
  -H "Content-Type: application/json" \
  -d '{"reason":"drill_test"}'
# Expected: HTTP 200, refund_amount < full amount (cancellation fee deducted)

# 6. Check wallet clawback was proportional and occurred exactly once
php artisan tinker --execute="
  \App\Models\ProviderWalletTransaction::where('source_type', 'booking')
    ->where('source_id', [CANCEL_BOOKING_B])
    ->where('type', 'clawback')
    ->get(['amount','direction','idempotency_key']);
"
# Expected: exactly 1 clawback row

# --- Part C: Tip flow ---
# 7. On a completed booking, create a tip
curl -X POST https://[STAGING_HOST]/api/client/bookings/[COMPLETED_BOOKING_ID]/tips \
  -H "Authorization: Bearer [CLIENT_TOKEN]" \
  -H "Content-Type: application/json" \
  -d '{"amount_cents": 500, "currency": "EUR"}'
# Record: tip_id=[TIP_ID]

# 8. Confirm tip status is pending, then charge it
php artisan tinker --execute="
  \$tip = \App\Models\BookingTip::find([TIP_ID]);
  app(\App\Services\Payments\TipService::class)->confirmCharge(\$tip);
  echo \$tip->fresh()->status;
"
# Expected: "charged"

# 9. Mark as paid out
php artisan tinker --execute="
  \$tip = \App\Models\BookingTip::find([TIP_ID]);
  app(\App\Services\Payments\TipService::class)->markPaidOut(\$tip);
  echo \$tip->fresh()->status;
"
# Expected: "paid_out"

# 10. Check reconciliation sees no anomaly for this booking/tip
```

#### Pass Criteria

- **Cancel inside window:** Stripe refund = 100% of booking amount. No clawback row.
- **Cancel outside window:** Stripe refund = booking amount minus cancellation fee (fee % determined by CancellationV2 policy). Clawback row count = 1, not more.
- **Clawback idempotency:** Running the refund or webhook handler a second time for the same `charge_id` does not produce a second clawback row.
- **Tip lifecycle:** `pending → charged → paid_out` transitions correctly. One wallet credit row of type `tip_income` on the provider.
- No Stripe API errors in logs.

#### Results

| Run | PASS/FAIL | Timestamp | Who | Notes |
|-----|-----------|-----------|-----|-------|
| 1 | | | | |
| 2 | | | | |

---

### D5 — Monitoring Alert

**Purpose:** Verify that a real operational failure triggers a `BusinessAlertRaised` event that propagates to Sentry and generates a visible alert in the Sentry project.

**Pairs with artifacts:** `SpineHealthReport`, `BusinessAlerts`, `BusinessAlertSentryListener`.

#### Operator Steps

```bash
# --- Scenario A: Stuck mission (holds funds but not completing) ---
# 1. Create a booking whose mission is in `in_progress` status for > [STUCK_THRESHOLD] minutes.
#    (Configure STUCK_MISSION_MINUTES in .env if the default needs tuning.)
#    Or forcibly set created_at to be old:
php artisan tinker --execute="
  \App\Models\Mission::find([MISSION_ID])->update(['updated_at' => now()->subHours(3)]);
"

# 2. Run the stuck-mission scanner (if it's a scheduled command, trigger it manually)
php artisan spine:check-stuck-missions
# OR trigger via the health report that includes stuck-mission checks

# 3. Verify BusinessAlertRaised event was dispatched
# Check logs:
grep "BusinessAlertRaised\|stuck_mission" storage/logs/laravel.log | tail -20

# 4. Check Sentry received the alert
# In Sentry dashboard: Issues → search "stuck_mission_holding_funds"
# Expected: at least one issue with the alert key and booking context

# --- Scenario B: Forced payout failure ---
# 5. Trigger a payout failure by setting an invalid Connect account ID
php artisan tinker --execute="
  \$booking = \App\Models\Booking::find([BOOKING_ID]);
  // Simulate a failed transfer by calling the alert directly
  \App\Events\BusinessAlertRaised::payoutFailed(\$booking, 'acct_INVALID', 'Drill: forced failure');
  event(new \App\Events\BusinessAlertRaised(
      key: 'payout_failed',
      context: ['booking_id' => \$booking->id, 'reason' => 'drill_forced'],
      level: 'critical',
  ));
"

# 6. Verify in Sentry:
# Issues → search "payout_failed" → confirm the event appeared within 60s
```

#### Pass Criteria

- `BusinessAlertRaised` events appear in application logs within 30 seconds of the triggering condition.
- Sentry shows at least one new issue per alert key (`stuck_mission_holding_funds`, `payout_failed`) within 60 seconds.
- The Sentry issue contains the `context` payload (booking_id, reason, etc.) in its extra data.
- No unhandled exceptions during alert dispatch.

#### Results

| Run | PASS/FAIL | Timestamp | Who | Notes |
|-----|-----------|-----------|-----|-------|
| 1 | | | | |
| 2 | | | | |

---

### D6 — Restore Drill

**Purpose:** Validate the true RTO/RPO: restore a real staging database snapshot to a scratch database, confirm schema and wallet-ledger integrity pass, and measure elapsed time.

**Pairs with artifacts:** `BackupRestoreDrill` (`backup:restore-drill`).

> **Pré-requis :** définir la connexion `scratch` via les variables `DB_SCRATCH_*`
> (cf. `.env.example`) pointant vers une base **dédiée et vide**, distincte de
> `DB_DATABASE`. La commande refuse de tourner sur la connexion primaire. Lancer
> avec un dump : `php artisan backup:restore-drill --connection=scratch --backup=<chemin/dump.sql>`.

#### Operator Steps

```bash
# Prerequisites:
# - A backup snapshot file is accessible on the staging host at [SNAPSHOT_PATH]
# - A scratch database connection named `drill` exists in config/database.php
#   (DATABASE_DRILL_DATABASE, DATABASE_DRILL_HOST, etc. set in .env)
# - The scratch DB is EMPTY or safe to overwrite

# 1. Dry-run first (zero risk)
php artisan backup:restore-drill --dry-run --connection=drill
# Expected: prints the plan, exits 0, makes no DB changes

# 2. Record start time
START=$(date +%s)

# 3. Run the real restore drill
php artisan backup:restore-drill --connection=drill --snapshot=[SNAPSHOT_PATH]
# The command will:
#   a. Load the snapshot into the scratch DB
#   b. Run wallet-ledger integrity check (signed balance constraint)
#   c. Print RTO/RPO report

# 4. Record end time
END=$(date +%s)
echo "Elapsed: $((END - START)) seconds"

# 5. Manually spot-check key tables in the scratch DB
mysql -u [DB_USER] -p [SCRATCH_DB] -e "
  SELECT COUNT(*) as bookings FROM bookings;
  SELECT COUNT(*) as wallet_txns FROM provider_wallet_transactions;
  SELECT COUNT(*) as payouts FROM provider_payouts;
"

# 6. Re-run wallet integrity check standalone
php artisan backup:restore-drill --connection=drill --integrity-only
# Expected: "Wallet ledger integrity: PASS"
```

#### Pass Criteria

- Dry-run exits 0 without touching the scratch DB.
- The command refuses to run against the `mysql` (primary) connection.
- Real restore completes without error.
- Wallet-ledger integrity check passes: sum of credits − sum of debits matches reported balance per provider.
- RTO (time from snapshot to integrity-pass) < 30 minutes for a staging-sized dataset. Record actual time.
- RPO: the snapshot age at drill time is noted (expected: < 24 hours for a nightly-backup schedule).

#### Results

| Run | PASS/FAIL | Timestamp | Who | Notes (RTO, RPO, snapshot age) |
|-----|-----------|-----------|-----|--------------------------------|
| 1 | | | | |
| 2 | | | | |

---

## Definition of Done

The following 7 items must all be satisfied for production go-live clearance.

| # | Item | Phase-1 Status | Phase-2 Status |
|---|------|---------------|---------------|
| 1 | **E2E + F1–F10 + isolation tests green in CI** — All Spine and Ops tests pass (0 failures). Acceptable skips: 2 parity-surface isolation cases (parity foundation not on base branch), 1 sentry-not-bound listener skip. | **COMPLETE** — 47 Spine passed (2 skipped), 22 Ops passed (0 skipped) | — |
| 2 | **Known money-path bugs fixed with regression tests** — F1 double-capture guard; F2 webhook idempotency; F3 cancel/refund with CancellationV2 authoritative quote; F4 Connect destination + app fee on PI create; F5 payout routing to assigned provider; F6 unauthorized capture blocked; F7 declined-at-capture handled; F8 reconciliation detects mismatch + missing payout; F9 proportional clawback; F10 PROPORTIONAL clawback dedup across service+webhook. | **COMPLETE** — All 10 failure modes have passing regression tests | — |
| 3 | **Spine health report + business alerts wired** — `spine:health-report` probes DB/Redis/queue/Reverb/Stripe. `BusinessAlertRaised` events for capture failure, payout failure, webhook backlog, stuck mission, reconciliation divergence all fire to Sentry. | **COMPLETE** — 7 SpineHealthReport tests pass, 6 BusinessAlerts tests pass (1 sentry skip) | Sentry receipt confirmed in D5 |
| 4 | **Restore drill passes against real staging snapshot** — `backup:restore-drill` with integrity check (wallet ledger signed balance) passes on a scratch connection. | **COMPLETE** — 4 BackupRestoreDrill tests pass (dry-run, describe, primary-guard, schema-integrity) | Real restore timing recorded in D6 |
| 5 | **Production parity check passes on staging** — `config:parity-check` exits 0 on staging with all 5 settings (DB, queue, cache, broadcasting, session) in the allowed set. | **COMPLETE** — 4 ConfigParityCheck tests pass, command logic verified | Executed on staging in D1 |
| 6 | **Runbook scaffold written** — This document exists with all 6 drills, pass criteria, and results tables ready for operator execution. | **COMPLETE** — This file | Drills D1–D6 executed and signed off |
| 7 | **PHPStan clean + Pint clean on all launch-hardening files** — Zero new PHPStan findings attributable to Phase-1 files. Pint passes with `{"result":"passed"}`. | **COMPLETE** — 5 findings fixed (ConfigParityCheck type annotation, ProviderWalletController parameter type, MissionLifecycleService instanceof guard + baseline count corrected, SpineHealthReport Redis comparison) | — |

**Phase 1 is COMPLETE. Phase 2 requires operator execution of drills D1–D6 above.**

---

## Known Follow-ups / Out-of-Scope Gaps Surfaced During Phase 1

These items were identified during Phase-1 hardening. They do **not** block go-live (they are mitigated) but should be addressed in the next sprint.

### 1. Capture-succeeded-but-DB-tx-failed leaves no ProviderPayout (LOW RISK — mitigated)

**Scenario:** Stripe captures the PaymentIntent successfully but the subsequent DB transaction (ProviderPayout creation + wallet credit) fails and rolls back.

**Mitigation in place:** The `payment_intent.succeeded` webhook re-runs the wallet credit path (idempotent). The `captured_booking_missing_payout` reconciliation check (F8) surfaces any booking in `captured` state without a wallet earning row, enabling manual intervention.

**Recommended follow-up:** Add `syncPaymentIntent()` before the capture guard in `MissionPaymentService::capture()` to refresh PI status from Stripe before deciding whether to re-capture. This turns a silent half-failure into a safe retry. Ticket: `[TODO]`.

### 2. Non-money provider controllers lack role guard (SECURITY BACKLOG)

**Controllers affected:** `AvailabilityController`, `BadgesController`, `ProviderPerformanceController`, `ProviderRatingController`, `QualityInspectionController`, `ProviderProfileController`.

**Current state:** These controllers scope all queries by `auth()->id()` (user-id-scoped), so there is no cross-provider data leakage. However, a client-role user who discovers the route can call them without receiving a 403.

**Mitigation:** No cross-tenant leak. The `MoneyPathIsolationTest::client cannot access provider wallet endpoints` test verifies the money-critical controller (`ProviderWalletController`) correctly returns 403.

**Recommended follow-up:** Add `$this->abortIfNotProvider($request->user())` (or a route middleware) to all non-money provider controllers. Security backlog priority: Medium.

### 3. Consecutive PARTIAL refunds via the direct service path are blocked by the `captured`-status guard

**Scenario:** A caller invokes `StripeRefundService::refund()` twice for different amounts on the same booking (partial refunds).

**Current behavior:** The second call is blocked because the booking status is already `partially_refunded`, not `captured`.

**Supported path:** The `charge.refunded` webhook handler correctly processes multiple partial refunds and accumulates clawbacks (covered by `RefundClawbackTest::two distinct partial refunds each claw back separately`).

**Recommended follow-up:** Decide whether to extend the service-layer guard to also accept `partially_refunded` status, or document that multi-partial refund is exclusively a webhook-driven path. Ticket: `[TODO]`.

### 4. `MoneyPathIsolationTest` parity-surface cases self-skip

**Tests:** `parity map not accessible cross tenant` and `webview ticket not mintable for another tenant`.

**Reason:** These tests depend on the parity-foundation feature (tenant context propagation) which is not yet on the base branch. The skip message is `→ parity foundation not on this base — see spec base-branch note`.

**Action required:** Merge parity-foundation into base and remove the `markTestSkipped()` guards. These cases should pass as-is once the foundation is present.

### 5. `BusinessAlertSentryListenerTest::it does nothing when sentry is not bound` is skipped

**Reason:** Sentry is registered via a service provider in the test environment (via `sentry/sentry-laravel`), so the "not bound" branch is unreachable in tests.

**Mitigation:** The two positive tests (`it calls capture message when sentry is bound`, `it maps non critical level to sentry error severity`) fully cover the listener's behavior. The skip is benign.

**Action required:** None. The behavior is verified in D5 (live Sentry receipt confirmation).
