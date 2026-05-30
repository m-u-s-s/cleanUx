# Launch Hardening — Phase 1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Prove the money + mission transaction spine is production-trustworthy with a true full-spine E2E, a F1–F10 failure-mode test suite (fixing real bugs surfaced), money-path isolation tests, spine health + Sentry business alerts, a backup restore-drill, and a production-parity check — all CI-verifiable with Stripe faked at the boundary.

**Architecture:** Test/instrument the EXISTING spine; do not reimplement payment logic. A test-only `FakeStripeHttpClient` (registered via `\Stripe\ApiRequestor::setHttpClient`) intercepts all static `\Stripe\PaymentIntent::create/retrieve/capture` and `\Stripe\Refund` calls with canned test-mode-shaped JSON. A `SpineScenario` builder centralizes all factory/model setup. Every failure mode and the happy path are feature tests on SQLite + the fake. Phase 2 live drills are a runbook skeleton only (operated together later), not build tasks.

**Tech Stack:** Laravel 10, PHPUnit, `stripe-php` SDK (static facade), Sentry, Redis/queue (config only in this phase).

**Spec:** `docs/superpowers/specs/2026-05-30-launch-hardening-design.md`
**Branch:** `feat/launch-hardening` (off `main`). NOTE: `MoneyPathIsolationTest` (Task 9) references sub-project-1 parity surfaces; if those aren't on this base, that task's parity-surface cases are marked and deferred — see Task 9.

---

## Verified spine facts (ground truth for all tasks)

- `MissionPaymentService::authorize(Booking $b, string $pmId): \Stripe\PaymentIntent` — guards: `$b->employe->canReceiveStripeConnectPayments()` must be true and client must have `stripe_id`. Calls `\Stripe\PaymentIntent::create([... capture_method=manual, application_fee_amount, transfer_data.destination=employe.stripe_connect_account_id ...])`. Writes `payment_status='authorized'`, `stripe_payment_intent_id`, `payment_amount_cents`, `platform_fee_cents`, `provider_amount_cents`, `payment_authorized_at`. Fee % from `env('CLEANUX_PLATFORM_FEE_PERCENT', 20)`.
- `MissionPaymentService::capture(Booking): ?\Stripe\PaymentIntent` — returns null unless `stripe_payment_intent_id` set AND `payment_status==='authorized'` (THIS is the F1 double-capture guard). Calls `PaymentIntent::retrieve()->capture()`, sets `payment_status='captured'`, `payment_captured_at`.
- `StripeConnectPaymentService::captureMissionPayment(Mission): ?ProviderPayout` — guards `booking->payment_status==='authorized'` (idempotent). On capture exception: sets booking `payment_status='failed'` then throws `RuntimeException` (F7). On success: sets `captured`, then `createProviderPayout()`.
- `StripeConnectPaymentService::createProviderPayout(Mission, Booking): ProviderPayout` — provider = `mission->lead_provider_user_id` or first `accepted` assignment's `user_id` (throws if none). amount = `booking->provider_amount_cents/100`. Creates `ProviderPayout` with `status=STATUS_PENDING`, `provider='stripe_connect'`, `transfer_data.destination` provider implied by metadata.
- `StripeConnectPaymentService::refundMissionPayment(...)` — total/partial refund of a captured PI (read its signature in Task 8 before use).
- **Wallet ledger is written by the WEBHOOK handler**, not capture: `StripeWebhookHandlers::handlePaymentIntentSucceeded` → `ProviderWalletService::recordEarning(Booking, ?array $intent)` with idempotency key `earning:booking:{id}:pi:{piId}` (returns existing on replay → F2 protection). `recordEarning` writes a `ProviderWalletTransaction` (`TYPE_EARNING`, `DIRECTION_CREDIT`, amount = `provider_amount_cents/100`).
- `StripeWebhookEventProcessor::process(StripeWebhookEvent $event): void` — idempotent: `isTerminal()` short-circuits; locks row `FOR UPDATE`; dispatches by `$event->type` reading `$event->payload['data']['object']`. Statuses: `STATUS_RECEIVED/PROCESSING/PROCESSED/IGNORED/FAILED/DEAD_LETTER`.
- `StripeReconciliationService::run(...)` — read its signature in Task 8.
- `CancellationV2\CancellationEngine::quote(int $bookingId, string $actorRole, ?string $reasonCode=null, ?\DateTimeInterface $at=null): CancellationQuote` (has `feeAmountCents`, `refundAmountCents`, `feePercent`). `execute(...)` — multi-arg, read in Task 10. **Authoritative for refund money.**
- `Tips\TipService`: `create(...)`, `confirmCharge(BookingTip, ?string $piId=null): BookingTip`, `markPaidOut(BookingTip, ?string $transferId=null): BookingTip`, `markFailed`, `cancel`.
- Models: `ProviderPayout` (`STATUS_PENDING/PROCESSING/PAID/FAILED`), `ProviderWalletTransaction` (`TYPE_EARNING`, `DIRECTION_CREDIT`, `idempotency_key`), `Mission` (`lead_provider_user_id`, `assignments()` with `assignment_status`), `Booking`, `BookingTip`, `StripeWebhookEvent`.
- User factory states: `User::factory()->client()`, `User::factory()->employe()` exist. **Making an employe Connect-ready** (so `canReceiveStripeConnectPayments()` is true) is NOT obvious — Task 2 establishes it by reading the method.

---

## File structure

- `tests/Support/Stripe/FakeStripeHttpClient.php` — test double implementing `\Stripe\HttpClient\ClientInterface`. One job: return canned Stripe JSON per (method, path). (Task 1)
- `tests/Support/Stripe/StripeFakeResponses.php` — canned response builders (PaymentIntent authorized/captured, Refund, etc.). (Task 1)
- `tests/Support/Spine/SpineScenario.php` — builder for a connect-ready provider + client + booking + mission in a requested state. Centralizes ALL factory knowledge. (Task 2)
- `tests/Feature/Spine/MoneyMissionSpineTest.php` — happy-path full-spine E2E. (Task 3)
- `tests/Feature/Spine/CaptureGuardsTest.php` — F1, F6, F7. (Task 4)
- `tests/Feature/Spine/WebhookResilienceTest.php` — F2. (Task 5)
- `tests/Feature/Spine/RefundClawbackTest.php` — F3. (Task 6)
- `tests/Feature/Spine/PayoutRoutingTest.php` — F5, F8. (Task 7)
- `tests/Feature/Spine/CancellationRefundTest.php` — F9. (Task 8a)
- `tests/Feature/Spine/TipLifecycleTest.php` — F10. (Task 8b)
- `tests/Feature/Spine/MoneyPathIsolationTest.php` — F4 + role/org isolation. (Task 9)
- `app/Services/Ops/SpineHealthReport.php` + `app/Support/Alerts/BusinessAlerts.php` + test. (Task 10)
- `app/Console/Commands/BackupRestoreDrill.php` + test. (Task 11)
- `app/Console/Commands/ConfigParityCheck.php` + `.env.production.example` + test. (Task 12)
- `docs/runbooks/GO-LIVE-RUNBOOK.md` — Phase 2 drill skeleton. (Task 13)

---

## Task 1: FakeStripeHttpClient seam

**Files:**
- Create: `tests/Support/Stripe/FakeStripeHttpClient.php`
- Create: `tests/Support/Stripe/StripeFakeResponses.php`
- Test: `tests/Feature/Spine/FakeStripeHttpClientTest.php`

The `stripe-php` SDK routes all static calls through `\Stripe\ApiRequestor::httpClient()`, which is a settable singleton implementing `\Stripe\HttpClient\ClientInterface::request($method, $absUrl, $headers, $params, $hasFile): array` returning `[string $body, int $code, array $headers]`. Registering a fake there intercepts everything with zero production change.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Spine;

use Stripe\ApiRequestor;
use Stripe\PaymentIntent;
use Stripe\Stripe;
use Tests\Support\Stripe\FakeStripeHttpClient;
use Tests\Support\Stripe\StripeFakeResponses;
use Tests\TestCase;

class FakeStripeHttpClientTest extends TestCase
{
    public function test_fake_intercepts_payment_intent_create_and_capture(): void
    {
        Stripe::setApiKey('sk_test_fake');
        $fake = new FakeStripeHttpClient();
        $fake->stub('POST', '/v1/payment_intents', StripeFakeResponses::paymentIntent('pi_test_1', 'requires_capture'));
        $fake->stub('POST', '/v1/payment_intents/pi_test_1/capture', StripeFakeResponses::paymentIntent('pi_test_1', 'succeeded'));
        ApiRequestor::setHttpClient($fake);

        $pi = PaymentIntent::create(['amount' => 1000, 'currency' => 'eur', 'capture_method' => 'manual']);
        $this->assertSame('pi_test_1', $pi->id);
        $this->assertSame('requires_capture', $pi->status);

        $captured = PaymentIntent::retrieve('pi_test_1'); // GET stub falls back to last-known
        $captured->capture();
        $this->assertSame('succeeded', $captured->status);

        $this->assertContains('POST /v1/payment_intents', $fake->calls());
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);
        parent::tearDown();
    }
}
```

- [ ] **Step 2: Run → FAIL** (`Class "Tests\Support\Stripe\FakeStripeHttpClient" not found`)

Run: `php artisan test --filter=FakeStripeHttpClientTest`

- [ ] **Step 3: Implement the fake**

Create `tests/Support/Stripe/FakeStripeHttpClient.php`:

```php
<?php

namespace Tests\Support\Stripe;

use Stripe\HttpClient\ClientInterface;

/**
 * Test double for the Stripe SDK HTTP boundary. Register via
 * \Stripe\ApiRequestor::setHttpClient($fake) in a test's setUp; all static
 * Stripe calls (PaymentIntent::create/retrieve/capture, Refund::create, ...)
 * are then served from canned JSON instead of the network. No production code
 * is touched.
 */
class FakeStripeHttpClient implements ClientInterface
{
    /** @var array<string, array{0:array,1:int}> keyed by "METHOD path" */
    private array $stubs = [];

    /** @var string[] */
    private array $calls = [];

    /** Remembers the last object returned per resource path for GET retrieves. */
    private array $lastKnown = [];

    public function stub(string $method, string $path, array $body, int $code = 200): self
    {
        $this->stubs[strtoupper($method).' '.$path] = [$body, $code];

        return $this;
    }

    /** @return string[] */
    public function calls(): array
    {
        return $this->calls;
    }

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        $path = '/'.ltrim((string) parse_url($absUrl, PHP_URL_PATH), '/');
        // Stripe absolute URLs look like https://api.stripe.com/v1/payment_intents
        $path = preg_replace('#^/?api\.stripe\.com#', '', $path);
        if (! str_starts_with($path, '/v1')) {
            $path = '/v1'.$path;
        }
        $key = strtoupper((string) $method).' '.$path;
        $this->calls[] = $key;

        if (isset($this->stubs[$key])) {
            [$body, $code] = $this->stubs[$key];
            $this->rememberLastKnown($path, $body);

            return [json_encode($body), $code, []];
        }

        // GET retrieve fallback: serve the last-known object for this resource.
        if (strtoupper((string) $method) === 'GET' && isset($this->lastKnown[$path])) {
            return [json_encode($this->lastKnown[$path]), 200, []];
        }

        throw new \RuntimeException("FakeStripeHttpClient: no stub for {$key}");
    }

    private function rememberLastKnown(string $path, array $body): void
    {
        $this->lastKnown[$path] = $body;
        if (isset($body['id'])) {
            // also key the canonical resource path (strip trailing /capture etc.)
            $resource = preg_replace('#/(capture|cancel|confirm)$#', '', $path);
            $this->lastKnown[$resource] = $body;
        }
    }
}
```

Create `tests/Support/Stripe/StripeFakeResponses.php`:

```php
<?php

namespace Tests\Support\Stripe;

/**
 * Canned, test-mode-shaped Stripe API objects for the fake HTTP client.
 */
class StripeFakeResponses
{
    public static function paymentIntent(string $id, string $status, array $overrides = []): array
    {
        return array_merge([
            'id' => $id,
            'object' => 'payment_intent',
            'status' => $status, // requires_capture | succeeded | canceled | requires_payment_method
            'amount' => 10000,
            'amount_capturable' => $status === 'requires_capture' ? 10000 : 0,
            'amount_received' => $status === 'succeeded' ? 10000 : 0,
            'currency' => 'eur',
            'capture_method' => 'manual',
            'application_fee_amount' => 2000,
            'transfer_data' => ['destination' => 'acct_provider_test'],
            'latest_charge' => 'ch_test_'.$id,
            'metadata' => [],
        ], $overrides);
    }

    public static function refund(string $id, string $piId, int $amount): array
    {
        return [
            'id' => $id,
            'object' => 'refund',
            'amount' => $amount,
            'currency' => 'eur',
            'payment_intent' => $piId,
            'status' => 'succeeded',
        ];
    }
}
```

- [ ] **Step 4: Run → PASS**

Run: `php artisan test --filter=FakeStripeHttpClientTest`
Expected: PASS. If `ClientInterface::request` signature differs in the installed `stripe-php` version, run `php -r "echo (new ReflectionMethod('Stripe\\HttpClient\\ClientInterface','request'))->__toString();"` and match the parameter list exactly.

- [ ] **Step 5: pint + commit**

```
vendor/bin/pint tests/Support/Stripe tests/Feature/Spine/FakeStripeHttpClientTest.php
git add tests/Support/Stripe tests/Feature/Spine/FakeStripeHttpClientTest.php
git commit -m "test(spine): FakeStripeHttpClient seam to intercept static Stripe calls"
```

---

## Task 2: SpineScenario builder

**Files:**
- Create: `tests/Support/Spine/SpineScenario.php`
- Test: `tests/Feature/Spine/SpineScenarioTest.php`

Centralizes all factory/model setup so every spine test is DRY. This task's real work is DISCOVERY: read `database/factories/UserFactory.php` (the `client()`/`employe()` states), `app/Models/User.php::canReceiveStripeConnectPayments()`, `database/factories/BookingFactory.php`, and `database/factories/MissionFactory.php` (or how Missions are created), then implement the builder so `canReceiveStripeConnectPayments()` returns true and the booking/mission are linked.

- [ ] **Step 1: Write the failing test (the contract the builder must satisfy)**

```php
<?php

namespace Tests\Feature\Spine;

use App\Models\Booking;
use App\Models\Mission;
use Tests\Support\Spine\SpineScenario;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SpineScenarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_builds_a_connect_ready_provider_and_linked_booking_mission(): void
    {
        $s = SpineScenario::make()->withDevis(100.00)->build();

        $this->assertTrue(
            $s->provider->canReceiveStripeConnectPayments(),
            'scenario provider must be Stripe-Connect ready'
        );
        $this->assertNotNull($s->provider->stripe_connect_account_id);
        $this->assertNotNull($s->client->stripe_id);

        $this->assertInstanceOf(Booking::class, $s->booking);
        $this->assertSame($s->client->id, $s->booking->client_id);
        $this->assertSame($s->provider->id, $s->booking->employe_id);

        $this->assertInstanceOf(Mission::class, $s->mission);
        $this->assertSame($s->booking->id, $s->mission->booking->id);
        $this->assertSame($s->provider->id, $s->mission->lead_provider_user_id);
    }
}
```

- [ ] **Step 2: Run → FAIL** (`SpineScenario not found`)

Run: `php artisan test --filter=SpineScenarioTest`

- [ ] **Step 3: Implement the builder**

First READ: `database/factories/UserFactory.php`, `app/Models/User.php` (find `canReceiveStripeConnectPayments` and the fields it checks — likely `stripe_connect_account_id` plus an onboarding/charges-enabled flag or a related `ProviderProfile`/`stripe_connect_*` column), `database/factories/BookingFactory.php`, and how `Mission` rows are created (factory or a service). Set exactly the fields `canReceiveStripeConnectPayments()` requires.

Create `tests/Support/Spine/SpineScenario.php`:

```php
<?php

namespace Tests\Support\Spine;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\User;

/**
 * Builds a complete, Connect-ready money-mission scenario for spine tests:
 * a client (Stripe customer), a Connect-ready provider, a linked Booking, and
 * a Mission whose lead_provider is that provider. All factory/model knowledge
 * for the spine lives HERE so individual tests stay declarative.
 */
class SpineScenario
{
    public User $client;
    public User $provider;
    public Booking $booking;
    public Mission $mission;

    private float $devis = 100.00;

    public static function make(): self
    {
        return new self();
    }

    public function withDevis(float $amount): self
    {
        $this->devis = $amount;

        return $this;
    }

    public function build(): self
    {
        $this->client = User::factory()->client()->create([
            // Booking::authorize requires client->stripe_id; set a fake test customer id.
            'stripe_id' => 'cus_test_'.uniqid(),
        ]);

        // Connect-ready provider. NB: set EXACTLY the fields
        // User::canReceiveStripeConnectPayments() checks (read that method).
        $this->provider = User::factory()->employe()->create([
            'stripe_connect_account_id' => 'acct_provider_test',
            // e.g. 'stripe_connect_charges_enabled' => true / onboarding flag — set per the method.
        ]);

        $this->booking = Booking::factory()->create([
            'client_id' => $this->client->id,
            'employe_id' => $this->provider->id,
            'devis_estime' => $this->devis,
            'payment_status' => 'pending',
        ]);

        $this->mission = Mission::factory()->create([
            'booking_id' => $this->booking->id,
            'lead_provider_user_id' => $this->provider->id,
        ]);

        return $this;
    }
}
```

ADAPT: if `Mission` has no factory, create it the way the app does (check `app/Services/Mission*`/`MissionFactory`). If `canReceiveStripeConnectPayments()` needs more than `stripe_connect_account_id`, set those fields here and document them in a comment. The `SpineScenarioTest` contract above is the gate — make it green without weakening the assertions.

- [ ] **Step 4: Run → PASS**

Run: `php artisan test --filter=SpineScenarioTest`

- [ ] **Step 5: pint + commit**

```
vendor/bin/pint tests/Support/Spine tests/Feature/Spine/SpineScenarioTest.php
git add tests/Support/Spine tests/Feature/Spine/SpineScenarioTest.php
git commit -m "test(spine): SpineScenario builder centralizing money-mission fixtures"
```

---

## Task 3: MoneyMissionSpineTest (full-spine happy-path E2E)

**Files:**
- Create: `tests/Feature/Spine/MoneyMissionSpineTest.php`

Drives the real services end to end with Stripe faked: authorize → capture (via `captureMissionPayment`) → ProviderPayout → simulated `payment_intent.succeeded` webhook → wallet `recordEarning`. Asserts state + ledger at each hop.

- [ ] **Step 1: Write the test (this is the canonical spine assertion)**

```php
<?php

namespace Tests\Feature\Spine;

use App\Models\ProviderPayout;
use App\Models\ProviderWalletTransaction;
use App\Models\StripeWebhookEvent;
use App\Services\Payments\MissionPaymentService;
use App\Services\Payments\StripeConnectPaymentService;
use App\Services\Payments\Webhooks\StripeWebhookEventProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\ApiRequestor;
use Stripe\Stripe;
use Tests\Support\Spine\SpineScenario;
use Tests\Support\Stripe\FakeStripeHttpClient;
use Tests\Support\Stripe\StripeFakeResponses;
use Tests\TestCase;

class MoneyMissionSpineTest extends TestCase
{
    use RefreshDatabase;

    private FakeStripeHttpClient $stripe;

    protected function setUp(): void
    {
        parent::setUp();
        Stripe::setApiKey('sk_test_fake');
        $this->stripe = new FakeStripeHttpClient();
        ApiRequestor::setHttpClient($this->stripe);
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);
        parent::tearDown();
    }

    public function test_full_spine_book_to_settle(): void
    {
        $s = SpineScenario::make()->withDevis(100.00)->build();
        $piId = 'pi_spine_1';

        // 1. Authorize (pre-auth)
        $this->stripe->stub('POST', '/v1/payment_intents', StripeFakeResponses::paymentIntent($piId, 'requires_capture'));
        app(MissionPaymentService::class)->authorize($s->booking, 'pm_card_visa');
        $s->booking->refresh();
        $this->assertSame('authorized', $s->booking->payment_status);
        $this->assertSame($piId, $s->booking->stripe_payment_intent_id);
        $this->assertSame(8000, (int) $s->booking->provider_amount_cents); // 100€ - 20% fee
        $this->assertSame(2000, (int) $s->booking->platform_fee_cents);

        // 2-4. Mission completes → 5. Capture + payout entry
        $this->stripe->stub('POST', "/v1/payment_intents/{$piId}/capture", StripeFakeResponses::paymentIntent($piId, 'succeeded'));
        $payout = app(StripeConnectPaymentService::class)->captureMissionPayment($s->mission->fresh());
        $s->booking->refresh();
        $this->assertSame('captured', $s->booking->payment_status);
        $this->assertInstanceOf(ProviderPayout::class, $payout);
        $this->assertSame($s->provider->id, $payout->provider_user_id);
        $this->assertEqualsWithDelta(80.00, (float) $payout->amount, 0.001);
        $this->assertSame(ProviderPayout::STATUS_PENDING, $payout->status);

        // 6. payment_intent.succeeded webhook → wallet credit
        $event = StripeWebhookEvent::create([
            'stripe_event_id' => 'evt_spine_1',
            'type' => 'payment_intent.succeeded',
            'status' => StripeWebhookEvent::STATUS_RECEIVED,
            'payload' => ['data' => ['object' => StripeFakeResponses::paymentIntent($piId, 'succeeded', [
                'metadata' => ['rendez_vous_id' => $s->booking->id],
            ])]],
        ]);
        app(StripeWebhookEventProcessor::class)->process($event);

        $credit = ProviderWalletTransaction::where('provider_user_id', $s->provider->id)
            ->where('type', ProviderWalletTransaction::TYPE_EARNING)
            ->first();
        $this->assertNotNull($credit, 'wallet earning must be recorded by the succeeded webhook');
        $this->assertEqualsWithDelta(80.00, (float) $credit->amount, 0.001);
    }
}
```

- [ ] **Step 2: Run → likely FAIL on a real detail**

Run: `php artisan test --filter=MoneyMissionSpineTest`
Expected: it will fail somewhere real (e.g. the webhook handler resolves the booking from a different metadata key than `rendez_vous_id`, or `StripeWebhookEvent` has different required columns). DO NOT weaken the assertions. Instead:

- [ ] **Step 3: Make it pass by reading the real wiring**

Read `app/Services/Payments/Webhooks/StripeWebhookHandlers.php::handlePaymentIntentSucceeded` to learn exactly how it locates the `Booking` from the event payload (which metadata key / which column), and fix the test's event payload to match. Read `app/Models/StripeWebhookEvent.php` `$fillable` for required columns. Adjust the TEST setup (payload shape, columns) — not the production code — unless a genuine bug is found (if so, that's a Task-4+ failure-mode fix; note it and continue with the happy path green).

- [ ] **Step 4: Run → PASS**

Run: `php artisan test --filter=MoneyMissionSpineTest`

- [ ] **Step 5: commit**

```
vendor/bin/pint tests/Feature/Spine/MoneyMissionSpineTest.php
git add tests/Feature/Spine/MoneyMissionSpineTest.php
git commit -m "test(spine): full-spine book-to-settle E2E (happy path)"
```

---

## Task 4: Capture guards — F1 double-capture, F6 stuck mission, F7 declined-at-capture

**Files:**
- Create: `tests/Feature/Spine/CaptureGuardsTest.php`

Reuse the `setUp`/`tearDown` Stripe-fake boilerplate from Task 3 (copy it into this class).

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature\Spine;

use App\Services\Payments\StripeConnectPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Stripe\ApiRequestor;
use Stripe\Stripe;
use Tests\Support\Spine\SpineScenario;
use Tests\Support\Stripe\FakeStripeHttpClient;
use Tests\Support\Stripe\StripeFakeResponses;
use Tests\TestCase;

class CaptureGuardsTest extends TestCase
{
    use RefreshDatabase;

    private FakeStripeHttpClient $stripe;

    protected function setUp(): void
    {
        parent::setUp();
        Stripe::setApiKey('sk_test_fake');
        $this->stripe = new FakeStripeHttpClient();
        ApiRequestor::setHttpClient($this->stripe);
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);
        parent::tearDown();
    }

    private function authorizedScenario(string $piId): SpineScenario
    {
        $s = SpineScenario::make()->build();
        $this->stripe->stub('POST', '/v1/payment_intents', StripeFakeResponses::paymentIntent($piId, 'requires_capture'));
        app(\App\Services\Payments\MissionPaymentService::class)->authorize($s->booking, 'pm_card_visa');
        $s->booking->refresh();

        return $s;
    }

    public function test_f1_double_capture_is_a_noop_second_time(): void
    {
        $s = $this->authorizedScenario('pi_f1');
        $this->stripe->stub('POST', '/v1/payment_intents/pi_f1/capture', StripeFakeResponses::paymentIntent('pi_f1', 'succeeded'));

        $svc = app(StripeConnectPaymentService::class);
        $first = $svc->captureMissionPayment($s->mission->fresh());
        $this->assertNotNull($first);

        // Second capture: booking is now 'captured', so guard returns null (no second payout).
        $second = $svc->captureMissionPayment($s->mission->fresh());
        $this->assertNull($second, 'second capture must be a no-op (F1)');
        $this->assertSame(1, \App\Models\ProviderPayout::where('provider_user_id', $s->provider->id)->count());
    }

    public function test_f7_declined_at_capture_marks_failed_and_throws(): void
    {
        $s = $this->authorizedScenario('pi_f7');
        // Stripe returns an error on capture → fake throws a Stripe error code.
        $this->stripe->stub('POST', '/v1/payment_intents/pi_f7/capture', [
            'error' => ['type' => 'card_error', 'code' => 'card_declined', 'message' => 'Your card was declined.'],
        ], 402);

        $svc = app(StripeConnectPaymentService::class);
        try {
            $svc->captureMissionPayment($s->mission->fresh());
            $this->fail('capture should throw on decline (F7)');
        } catch (RuntimeException $e) {
            // expected
        }
        $s->booking->refresh();
        $this->assertSame('failed', $s->booking->payment_status, 'declined capture must mark booking failed (F7)');
        $this->assertSame(0, \App\Models\ProviderPayout::where('provider_user_id', $s->provider->id)->count());
    }

    public function test_f6_unauthorized_booking_cannot_be_captured(): void
    {
        // A mission whose booking never got a pre-auth (payment_status='pending') must not capture.
        $s = SpineScenario::make()->build();
        $svc = app(StripeConnectPaymentService::class);
        $result = $svc->captureMissionPayment($s->mission->fresh());
        $this->assertNull($result, 'capture must no-op when booking is not authorized (F6)');
    }
}
```

- [ ] **Step 2: Run → FAIL/observe**

Run: `php artisan test --filter=CaptureGuardsTest`
Expected: F1 and F6 likely pass immediately (guards exist). F7 depends on the fake raising a proper Stripe error so the service's catch runs. If the 402 stub doesn't trigger a `\Stripe\Exception\*`, the SDK may not throw — in that case have the fake throw `\Stripe\Exception\CardException` directly for error-shaped bodies (see Step 3).

- [ ] **Step 3: If F7 needs it — make the fake raise Stripe errors**

In `FakeStripeHttpClient::request`, before returning, if `$code >= 400` and the body has an `error` key, throw the matching Stripe exception so calling code's try/catch fires:

```php
        if (isset($this->stubs[$key])) {
            [$body, $code] = $this->stubs[$key];
            if ($code >= 400 && isset($body['error'])) {
                throw \Stripe\Exception\ApiErrorException::factory(
                    $body['error']['message'] ?? 'Stripe error',
                    $code,
                    json_encode($body),
                    $body,
                    []
                );
            }
            $this->rememberLastKnown($path, $body);
            return [json_encode($body), $code, []];
        }
```

If `ApiErrorException::factory` is not available in this SDK version, throw `new \Stripe\Exception\CardException($msg, $code)` (verify the constructor via reflection). Re-run.

If any guard test reveals a REAL missing guard (e.g. F6 actually captures a pending booking), that is an in-scope bug: add the minimal guard in the service and keep the test as the regression.

- [ ] **Step 4: Run → PASS** (`php artisan test --filter=CaptureGuardsTest`)

- [ ] **Step 5: commit**

```
vendor/bin/pint tests/Feature/Spine/CaptureGuardsTest.php tests/Support/Stripe
git add tests/Feature/Spine/CaptureGuardsTest.php tests/Support/Stripe
git commit -m "test(spine): F1 double-capture, F6 stuck-mission, F7 declined-at-capture guards"
```

---

## Task 5: Webhook resilience — F2 (replay, out-of-order, missed)

**Files:**
- Create: `tests/Feature/Spine/WebhookResilienceTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature\Spine;

use App\Models\ProviderWalletTransaction;
use App\Models\StripeWebhookEvent;
use App\Services\Payments\Webhooks\StripeWebhookEventProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Spine\SpineScenario;
use Tests\Support\Stripe\StripeFakeResponses;
use Tests\TestCase;

class WebhookResilienceTest extends TestCase
{
    use RefreshDatabase;

    private function succeededEvent(int $bookingId, string $piId, string $eventId): StripeWebhookEvent
    {
        return StripeWebhookEvent::create([
            'stripe_event_id' => $eventId,
            'type' => 'payment_intent.succeeded',
            'status' => StripeWebhookEvent::STATUS_RECEIVED,
            'payload' => ['data' => ['object' => StripeFakeResponses::paymentIntent($piId, 'succeeded', [
                'metadata' => ['rendez_vous_id' => $bookingId],
            ])]],
        ]);
    }

    public function test_f2_replayed_webhook_credits_wallet_once(): void
    {
        $s = SpineScenario::make()->build();
        // Put the booking in the captured/authorized shape the handler expects.
        $s->booking->forceFill([
            'stripe_payment_intent_id' => 'pi_f2',
            'payment_status' => 'captured',
            'provider_amount_cents' => 8000,
            'platform_fee_cents' => 2000,
        ])->save();

        $processor = app(StripeWebhookEventProcessor::class);
        $processor->process($this->succeededEvent($s->booking->id, 'pi_f2', 'evt_a'));
        $processor->process($this->succeededEvent($s->booking->id, 'pi_f2', 'evt_b')); // replay (same PI)

        $count = ProviderWalletTransaction::where('provider_user_id', $s->provider->id)
            ->where('type', ProviderWalletTransaction::TYPE_EARNING)
            ->count();
        $this->assertSame(1, $count, 'replayed succeeded webhook must credit wallet exactly once (F2)');
    }

    public function test_f2_same_event_processed_twice_is_idempotent(): void
    {
        $s = SpineScenario::make()->build();
        $s->booking->forceFill([
            'stripe_payment_intent_id' => 'pi_f2b', 'payment_status' => 'captured',
            'provider_amount_cents' => 8000, 'platform_fee_cents' => 2000,
        ])->save();

        $event = $this->succeededEvent($s->booking->id, 'pi_f2b', 'evt_same');
        $processor = app(StripeWebhookEventProcessor::class);
        $processor->process($event);
        $processor->process($event->fresh()); // terminal → short-circuits

        $this->assertSame(1, ProviderWalletTransaction::where('provider_user_id', $s->provider->id)->count());
    }
}
```

- [ ] **Step 2: Run → observe** (`php artisan test --filter=WebhookResilienceTest`)

Expected: depends on how `handlePaymentIntentSucceeded` finds the booking + whether `recordEarning`'s idempotency key (`earning:booking:{id}:pi:{piId}`) holds across two different event ids with the same PI. The first test is the real F2 proof. If it fails because the handler can't find the booking, fix the TEST payload (metadata key/column) per Task 3's findings. If it fails because the wallet is credited twice, that is a REAL F2 bug — fix `recordEarning`'s idempotency (it already keys on PI; investigate) and keep the test.

- [ ] **Step 3: Fix payload/columns (or a real idempotency bug) until green.**

- [ ] **Step 4: Run → PASS**

- [ ] **Step 5: commit**

```
vendor/bin/pint tests/Feature/Spine/WebhookResilienceTest.php
git add tests/Feature/Spine/WebhookResilienceTest.php
git commit -m "test(spine): F2 webhook replay + idempotency resilience"
```

---

## Task 6: Refund + clawback — F3

**Files:**
- Create: `tests/Feature/Spine/RefundClawbackTest.php`

- [ ] **Step 1: Read first, then write the test**

READ `StripeConnectPaymentService::refundMissionPayment(...)` (signature + what it writes) and `ProviderWalletService::recordRefundClawback(Booking, float $amount, ?string $chargeId)`. Then write a test (in the same Stripe-fake setUp/tearDown style as Task 3) that:
1. Builds a captured scenario (booking `payment_status='captured'`, a wallet earning credit present).
2. Stubs `POST /v1/refunds` with `StripeFakeResponses::refund('re_1', 'pi_x', <amount>)`.
3. Calls `refundMissionPayment(...)` with the real signature.
4. Asserts a clawback `ProviderWalletTransaction` (debit) is written and the net wallet balance == 0 (earning − clawback), proving F3 (no money out without clawback).

Write the concrete test using the exact signature you read (do not guess the arg order). Assert on `ProviderWalletService::balance($providerId)` net.

- [ ] **Step 2: Run → FAIL/observe** (`php artisan test --filter=RefundClawbackTest`)

- [ ] **Step 3: Make green.** If the refund path does NOT write a clawback (real F3 bug), add the minimal `recordRefundClawback` call at the refund site and keep the test as the regression.

- [ ] **Step 4: Run → PASS**

- [ ] **Step 5: commit**

```
vendor/bin/pint tests/Feature/Spine/RefundClawbackTest.php
git add tests/Feature/Spine/RefundClawbackTest.php app/Services/Payments
git commit -m "test(spine): F3 refund-after-capture writes wallet clawback"
```

---

## Task 7: Payout routing + reconciliation — F5, F8

**Files:**
- Create: `tests/Feature/Spine/PayoutRoutingTest.php`

- [ ] **Step 1: Write the tests**

```php
<?php

namespace Tests\Feature\Spine;

use App\Models\ProviderPayout;
use App\Services\Payments\StripeConnectPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\ApiRequestor;
use Stripe\Stripe;
use Tests\Support\Spine\SpineScenario;
use Tests\Support\Stripe\FakeStripeHttpClient;
use Tests\Support\Stripe\StripeFakeResponses;
use Tests\TestCase;

class PayoutRoutingTest extends TestCase
{
    use RefreshDatabase;

    private FakeStripeHttpClient $stripe;

    protected function setUp(): void
    {
        parent::setUp();
        Stripe::setApiKey('sk_test_fake');
        $this->stripe = new FakeStripeHttpClient();
        ApiRequestor::setHttpClient($this->stripe);
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);
        parent::tearDown();
    }

    public function test_f5_payout_goes_to_the_assigned_provider_only(): void
    {
        $s = SpineScenario::make()->build();
        $other = \App\Models\User::factory()->employe()->create(['stripe_connect_account_id' => 'acct_other']);

        $this->stripe->stub('POST', '/v1/payment_intents', StripeFakeResponses::paymentIntent('pi_f5', 'requires_capture'));
        app(\App\Services\Payments\MissionPaymentService::class)->authorize($s->booking, 'pm_card_visa');
        $this->stripe->stub('POST', '/v1/payment_intents/pi_f5/capture', StripeFakeResponses::paymentIntent('pi_f5', 'succeeded'));

        $payout = app(StripeConnectPaymentService::class)->captureMissionPayment($s->mission->fresh());

        $this->assertSame($s->provider->id, $payout->provider_user_id, 'payout must route to the assigned provider (F5)');
        $this->assertNotSame($other->id, $payout->provider_user_id);
        // The PI's transfer_data.destination was the assigned provider's connect account.
        $this->assertSame('acct_provider_test', $s->booking->fresh()->stripe_connect_account_id);
    }
}
```

For **F8 reconciliation**: READ `StripeReconciliationService::run(...)` signature, then add a test that seeds a deliberate DB↔Stripe divergence (e.g. a booking marked `captured` whose faked PI reports `requires_capture`) and asserts `run()` reports/flags the divergence rather than silently passing. Write it concretely against the real signature.

- [ ] **Step 2: Run → observe** (`php artisan test --filter=PayoutRoutingTest`)

- [ ] **Step 3: Make green** (fix test payloads, or a real bug → minimal fix + keep regression).

- [ ] **Step 4: Run → PASS**

- [ ] **Step 5: commit**

```
vendor/bin/pint tests/Feature/Spine/PayoutRoutingTest.php
git add tests/Feature/Spine/PayoutRoutingTest.php
git commit -m "test(spine): F5 payout routing + F8 reconciliation divergence detection"
```

---

## Task 8: Cancellation refund (F9) + Tip lifecycle (F10)

**Files:**
- Create: `tests/Feature/Spine/CancellationRefundTest.php`
- Create: `tests/Feature/Spine/TipLifecycleTest.php`

**8a — F9 (CancellationV2 is authoritative).**

- [ ] **Step 1: Read then write.** READ `CancellationV2\CancellationEngine::quote(...)` and `execute(...)` and `CancellationIntegrationsRunner::run(...)`. Then write `CancellationRefundTest` that:
  1. Builds a captured scenario.
  2. Calls `CancellationEngine::quote($booking->id, 'client', ...)` and captures `refundAmountCents`.
  3. Stubs `POST /v1/refunds` for that exact amount.
  4. Calls `execute(...)` (real signature), runs the integrations runner if that's how the refund fires.
  5. Asserts: the Stripe refund issued == `quote.refundAmountCents`; a wallet clawback is written for the refunded portion; the booking lands in the configured cancelled status. (F9: refund matches the AUTHORITATIVE V2 quote, reconciles, no double-refund on a second `execute` with the same idempotency key.)
- [ ] **Step 2–4:** Run → make green (fix test or minimal real bug) → PASS. `php artisan test --filter=CancellationRefundTest`
- [ ] **Step 5: commit** `git commit -m "test(spine): F9 cancellation-window refund via authoritative CancellationV2"`

**8b — F10 (tips).**

- [ ] **Step 1: Read then write.** READ `TipService::create/confirmCharge/markPaidOut/cancel` and the `BookingTip` model statuses. Then write `TipLifecycleTest` that proves:
  1. `confirmCharge` twice on the same tip does not double-charge (idempotent) → F10.
  2. `markPaidOut` writes a provider wallet credit (tip earning).
  3. A tip on a cancelled/refunded booking is `cancel()`-led and does NOT pay out.
- [ ] **Step 2–4:** Run → make green → PASS. `php artisan test --filter=TipLifecycleTest`
- [ ] **Step 5: commit** `git commit -m "test(spine): F10 tip lifecycle idempotency + payout + cancellation"`

---

## Task 9: MoneyPathIsolationTest — F4 (role + org)

**Files:**
- Create: `tests/Feature/Spine/MoneyPathIsolationTest.php`

- [ ] **Step 1: Discover the money-path endpoints.** Grep `routes/` for the client/provider money + booking endpoints (payment methods, booking detail, payout/wallet, tip). List them.
- [ ] **Step 2: Write isolation tests** proving, for each money-path endpoint: client B gets 403/404 on client A's booking/payment/payout; a provider cannot hit client-only money endpoints; cross-organization access is blocked. Use `Sanctum::actingAs` + `SpineScenario` for two distinct tenants. Concrete example:

```php
public function test_f4_client_cannot_read_another_clients_booking_payment(): void
{
    $a = SpineScenario::make()->build();
    $b = SpineScenario::make()->build();
    \Laravel\Sanctum\Sanctum::actingAs($b->client);

    // Replace with the real money-path route for a booking's payment detail.
    $this->getJson("/api/client/bookings/{$a->booking->id}")
        ->assertStatus(403); // or 404 — assert it is NOT 200 and leaks no money fields
}
```

- [ ] **Step 3: Parity-surface cases.** IF `feat/parity-foundation` is on this base (check `git log --oneline | grep parity`), add isolation cases for `/api/parity-map` and `/api/auth/webview-ticket` (a client cannot mint a ticket for another tenant's admin path). IF NOT present, write those cases guarded with `$this->markTestSkipped('parity foundation not on this base — see spec base-branch note')` and note it in the runbook.
- [ ] **Step 4: Run → make green** (any real leak found is an in-scope F4 fix). `php artisan test --filter=MoneyPathIsolationTest`
- [ ] **Step 5: commit** `git commit -m "test(spine): F4 money-path role + org isolation"`

---

## Task 10: SpineHealthCheck + business alerts

**Files:**
- Create: `app/Services/Ops/SpineHealthReport.php`
- Create: `app/Support/Alerts/BusinessAlerts.php`
- Test: `tests/Feature/Ops/SpineHealthReportTest.php`, `tests/Unit/Alerts/BusinessAlertsTest.php`

- [ ] **Step 1: Read** `app/Services/Ops/ProductionHealthReport.php` + `app/Http/Controllers/HealthCheckController.php` to match the existing health pattern.
- [ ] **Step 2: Write failing tests** — (a) `SpineHealthReport::collect(): array` returns keys `db`, `redis`, `queue_depth`, `stripe`, `reverb`, each `['ok'=>bool,'detail'=>string]`; in the test env it degrades gracefully (no real Redis/Stripe) without throwing. (b) `BusinessAlerts` exposes named emitters `paymentCaptureFailed(Booking)`, `payoutFailed(ProviderPayout)`, `webhookBacklog(int $count)`, `stuckMissionHoldingFunds(Mission)`, `reconciliationDivergence(array $detail)`, each calling the Sentry/`report()` path exactly once with a structured context — assert via a faked reporter/`Event` spy.
- [ ] **Step 3: Implement** both classes (graceful degradation: wrap each probe in try/catch returning `ok=false` instead of throwing; the alerts delegate to `\Sentry\captureMessage` / Laravel `report()` behind a thin interface so the test can spy).
- [ ] **Step 4: Run → PASS** `php artisan test --filter=SpineHealthReportTest; php artisan test --filter=BusinessAlertsTest`
- [ ] **Step 5: commit** `git commit -m "feat(ops): spine health report + Sentry business-alert emitters"`

---

## Task 11: backup:restore-drill command

**Files:**
- Create: `app/Console/Commands/BackupRestoreDrill.php`
- Test: `tests/Feature/Ops/BackupRestoreDrillTest.php`

- [ ] **Step 1: Write the failing test** — registers `backup:restore-drill --dry-run`, asserts it (a) reports the steps it WOULD run (locate backup, restore to scratch DB, integrity check, RTO/RPO), (b) on `--dry-run` makes NO destructive DB calls, (c) exits 0. Use `$this->artisan('backup:restore-drill', ['--dry-run' => true])->assertExitCode(0)->expectsOutputToContain('integrity')`.
- [ ] **Step 2: Run → FAIL** (`command not found`).
- [ ] **Step 3: Implement** the command with a `--dry-run` that prints the plan and a real mode that: locates the latest backup (configurable path/disk), restores into a scratch DB connection, runs an integrity check (table row-count sanity + a spine-ledger consistency query: sum of wallet credits − debits per provider == expected), and reports measured RTO/RPO. The real restore path must refuse to run against the primary connection (safety guard).
- [ ] **Step 4: Run → PASS** `php artisan test --filter=BackupRestoreDrillTest`
- [ ] **Step 5: commit** `git commit -m "feat(ops): backup:restore-drill command with integrity + RTO/RPO report"`

---

## Task 12: config:parity-check + production env template

**Files:**
- Create: `app/Console/Commands/ConfigParityCheck.php`
- Create: `.env.production.example`
- Test: `tests/Feature/Ops/ConfigParityCheckTest.php`

- [ ] **Step 1: Write the failing test** — `config:parity-check` exits non-zero and names offenders when `queue.default==='sync'` or `cache.default==='file'` (the prod-unsafe values), and exits 0 when queue=redis + cache=redis + broadcast=reverb. Drive via `Config::set(...)` then `$this->artisan('config:parity-check')->assertExitCode(1)`.
- [ ] **Step 2: Run → FAIL**.
- [ ] **Step 3: Implement** the command (assert a production profile: `db=mysql`, `queue=redis`, `cache=redis`, `session=database|redis`, `broadcast=reverb`; print a table of expected-vs-actual; non-zero exit on any mismatch). Create `.env.production.example` with the correct production values (Redis queue + workers note, Redis cache, async webhook processing) and a header comment explaining it's the production-parity baseline `config:parity-check` enforces.
- [ ] **Step 4: Run → PASS** `php artisan test --filter=ConfigParityCheckTest`
- [ ] **Step 5: commit** `git commit -m "feat(ops): config:parity-check + .env.production.example baseline"`

---

## Task 13: GO-LIVE-RUNBOOK skeleton + full-suite verification

**Files:**
- Create: `docs/runbooks/GO-LIVE-RUNBOOK.md`

- [ ] **Step 1: Write the runbook skeleton** — a Markdown file with the Phase-2 drills D1–D6 as empty checklist sections, each with: purpose, the artifact it pairs with, "user executes" steps (placeholders for the exact commands to be filled at drill time), "assertion / pass criteria", and a results table (`PASS/FAIL | timestamp | who`). Include the Phase-2 prerequisites (Stripe CLI, test Connect account id, how staging triggers a booking) and the DoD checklist (items 1–7 from the spec). This is documentation, not code — no test.
- [ ] **Step 2: Full Phase-1 suite green**

Run:
```
php artisan test --filter=Spine
php artisan test --filter=Ops
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=1G
```
Expected: all Spine + Ops tests pass, 0 skips except the explicitly-guarded parity-surface cases in Task 9 (if base lacks parity). Pint clean on new files; PHPStan no new findings. Fix new-file issues; do not touch unrelated pre-existing ones.

- [ ] **Step 3: Confirm Phase-1 DoD** — items 1 (E2E + F1–F10 + isolation green), 2 (bugs fixed w/ regression), 3 (health + alerts), 5-partial (parity-check command exists), and the runbook scaffold (item 6 skeleton). Items 4/5-live/6-filled are Phase 2.

- [ ] **Step 4: commit**

```
git add docs/runbooks/GO-LIVE-RUNBOOK.md
git commit -m "docs(ops): GO-LIVE-RUNBOOK Phase-2 drill skeleton + DoD"
```

---

## Self-review notes (already applied)

- **Spec coverage:** artifact #1→Task 3; #2 (F1–F10)→Tasks 4–9 (F1/F6/F7 T4, F2 T5, F3 T6, F5/F8 T7, F9 T8a, F10 T8b, F4 T9); #3 isolation→Task 9; #4 health+alerts→Task 10; #5 restore-drill→Task 11; #6 parity config+check→Task 12; runbook→Task 13. The Stripe-fake seam (Task 1) and SpineScenario (Task 2) are enabling foundations the spec implied ("Stripe faked at the boundary", shared fixtures).
- **Centralized uncertainty:** all factory/model setup unknowns are isolated in Task 2 (`SpineScenario`) behind a tested contract, so later tasks stay declarative. Tasks that depend on un-read signatures (refund, reconciliation, cancellation execute, tips) explicitly say "read the real signature first" before writing concrete asserts — this is discovery, not a placeholder.
- **Type/name consistency:** `FakeStripeHttpClient` (`stub`/`calls`), `StripeFakeResponses` (`paymentIntent`/`refund`), `SpineScenario` (`make`/`withDevis`/`build`/`->client`/`->provider`/`->booking`/`->mission`), `ProviderPayout::STATUS_PENDING`, `ProviderWalletTransaction::TYPE_EARNING`, `payment_status` string values (`pending/authorized/captured/failed`) are used consistently across all tasks.
- **In-scope bug fixes:** Tasks 4–9 each state that a real bug surfaced by a failure-mode test is fixed minimally with the test kept as regression (per spec DoD item 2) — not deferred.
- **Phase 2 is not built:** Task 13 only scaffolds the runbook; D1–D6 remain operate-together.
