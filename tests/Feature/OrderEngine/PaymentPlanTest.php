<?php

namespace Tests\Feature\OrderEngine;

use App\Models\Booking;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\OrderEngine\OrderPaymentPlanner;
use App\Services\Payments\CommissionService;
use App\Support\Domain\BookingStatus;
use App\Support\Domain\PaymentPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;
use Stripe\ApiRequestor;
use Stripe\Stripe;
use Tests\Support\Stripe\FakeStripeHttpClient;
use Tests\TestCase;

/** L'acompte, le solde, et la destination de l'argent. Trois garanties d'argent. */
class PaymentPlanTest extends TestCase
{
    use RefreshDatabase;

    private FakeStripeHttpClient $stripe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stripe = new FakeStripeHttpClient;
        ApiRequestor::setHttpClient($this->stripe);
        Stripe::setApiKey('sk_test_faux');

        Config::set('order_engine.deposit_enabled', true);
        Config::set('order_engine.deposit_threshold_cents', 50000);
        Config::set('order_engine.deposit_rate', 0.30);
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);
        parent::tearDown();
    }

    // ─── Le partage de la commission ─────────────────────────────────────────────────────────

    /** LA garantie comptable : deux commissions valent exactement une. */
    public function test_the_two_fees_add_up_exactly_to_the_single_one(): void
    {
        $planner = app(OrderPaymentPlanner::class);

        // Des montants choisis pour tomber sur des divisions inexactes.
        // Les quatre partages releves ensemble. Sur de l'argent, savoir QUELS cas perdent un
        // centime vaut mieux que d'apprendre qu'un cas le perd : un arrondi fautif se voit a son
        // motif, et le motif n'apparait qu'en comparant plusieurs cas.
        $ecarts = [];

        foreach ([[99999, 30000, 1234], [77777, 23333, 999], [10001, 3000, 301], [3, 1, 2]] as [$total, $deposit, $fee]) {
            $split = $planner->splitFee($total, $deposit, $fee);
            $somme = $split['deposit_fee_cents'] + $split['balance_fee_cents'];

            if ($somme !== $fee) {
                $ecarts[] = sprintf(
                    '%d centimes sur %d/%d : acompte %d + solde %d = %d',
                    $fee, $deposit, $total,
                    $split['deposit_fee_cents'], $split['balance_fee_cents'], $somme,
                );
            }
        }

        $this->assertSame([], $ecarts, 'Ces partages de frais ne retombent pas juste : des centimes se perdent.');
    }

    /** Le reste d'arrondi est porté par le SOLDE, jamais dispersé au hasard. */
    public function test_the_rounding_remainder_lands_on_the_balance(): void
    {
        $split = app(OrderPaymentPlanner::class)->splitFee(1000, 333, 101);

        // 101 × 333 / 1000 = 33,633 → 33 pour l'acompte, le reste pour le solde.
        $this->assertSame(33, $split['deposit_fee_cents']);
        $this->assertSame(68, $split['balance_fee_cents']);
    }

    /** Un total nul ne fait pas exploser le partage : tout va au solde. */
    public function test_a_zero_total_does_not_break_the_split(): void
    {
        $split = app(OrderPaymentPlanner::class)->splitFee(0, 0, 250);

        $this->assertSame(0, $split['deposit_fee_cents']);
        $this->assertSame(250, $split['balance_fee_cents']);
    }

    // ─── Quand l'acompte est proposé ─────────────────────────────────────────────────────────

    /** Sous le seuil, une seule formule : compliquer une petite commande ne sert personne. */
    public function test_below_the_threshold_only_one_option_is_offered(): void
    {
        $booking = $this->booking(120.00);

        $options = app(OrderPaymentPlanner::class)->optionsFor($booking);

        $this->assertCount(1, $options);
        $this->assertSame(PaymentPlan::FULL, $options[0]['plan']);
        $this->assertSame(0, $options[0]['due_now_cents']);
    }

    /** Au-dessus du seuil, l'acompte apparaît — avec ce qu'il coûte aujourd'hui. */
    public function test_above_the_threshold_the_deposit_appears_with_its_cost(): void
    {
        $booking = $this->booking(900.00);

        $options = app(OrderPaymentPlanner::class)->optionsFor($booking);

        $this->assertCount(2, $options);
        $deposit = collect($options)->firstWhere('plan', PaymentPlan::DEPOSIT);

        $this->assertSame(27000, $deposit['due_now_cents']);   // 30 % de 900 €
        $this->assertSame(63000, $deposit['held_cents']);
        $this->assertStringContainsString('270,00', $deposit['detail']);
    }

    /** Jamais d'acompte sur un devis. */
    public function test_a_quote_only_booking_never_offers_a_deposit(): void
    {
        $booking = $this->booking(900.00);

        // Sans montant estimé, mais AVEC un montant technique déjà posé sur la réservation.
        $booking->forceFill([
            'estimated_price' => null,
            'devis_estime' => null,
            'payment_amount_cents' => 200000,
        ])->save();

        $this->assertFalse(app(OrderPaymentPlanner::class)->depositIsAvailable($booking->fresh()));
        $this->assertCount(1, app(OrderPaymentPlanner::class)->optionsFor($booking->fresh()));
    }

    /** La formule se coupe globalement : un incident de paiement doit pouvoir se désamorcer vite. */
    public function test_the_deposit_can_be_switched_off_platform_wide(): void
    {
        Config::set('order_engine.deposit_enabled', false);

        $this->assertCount(1, app(OrderPaymentPlanner::class)->optionsFor($this->booking(900.00)));
    }

    // ─── L'autorisation avec acompte ─────────────────────────────────────────────────────────

    /** L'acompte est DÉBITÉ, le solde seulement BLOQUÉ — et les deux sont distincts en base. */
    public function test_the_deposit_is_captured_and_the_balance_only_held(): void
    {
        $booking = $this->bookingWithProvider(900.00);

        $this->stubIntent('pi_acompte');
        $result = app(OrderPaymentPlanner::class)->authorizeWithDeposit($booking, 'pm_test');

        $this->assertSame(PaymentPlan::DEPOSIT, $result->payment_plan);
        $this->assertSame(27000, (int) $result->deposit_amount_cents);
        $this->assertNotNull($result->deposit_captured_at);
        $this->assertNotNull($result->stripe_payment_intent_id);
        $this->assertSame('authorized', $result->payment_status);

        // DEUX paiements distincts partent chez Stripe, et leurs montants somment au total : c'est
        // ce qui distingue un acompte réel d'un simple libellé sur l'écran.
        $amounts = collect($this->stripe->requests())
            ->filter(fn (array $r) => $r['key'] === 'POST /v1/payment_intents')
            ->map(fn (array $r) => (int) $r['params']['amount'])
            ->values();

        $this->assertSame([27000, 63000], $amounts->all());
        $this->assertSame(90000, $amounts->sum());
    }

    /** Les deux intents envoyés à Stripe portent bien la capture attendue. */
    public function test_stripe_receives_one_immediate_capture_and_one_hold(): void
    {
        $booking = $this->bookingWithProvider(900.00);

        $this->stubIntent('pi_x');
        app(OrderPaymentPlanner::class)->authorizeWithDeposit($booking, 'pm_test');

        $captures = collect($this->stripe->requests())
            ->filter(fn (array $r) => $r['key'] === 'POST /v1/payment_intents')
            ->map(fn (array $r) => $r['params']['capture_method'] ?? null)
            ->values();

        $this->assertSame(['automatic', 'manual'], $captures->all());
    }

    /** Les deux commissions envoyées à Stripe valent exactement celle du calcul unique. */
    public function test_the_fees_sent_to_stripe_match_the_single_source(): void
    {
        $booking = $this->bookingWithProvider(900.00);
        $expected = (int) app(CommissionService::class)->calculateForBooking($booking)['platform_fee_cents'];

        $this->stubIntent('pi_y');
        app(OrderPaymentPlanner::class)->authorizeWithDeposit($booking, 'pm_test');

        $sent = collect($this->stripe->requests())
            ->filter(fn (array $r) => $r['key'] === 'POST /v1/payment_intents')
            ->sum(fn (array $r) => (int) ($r['params']['application_fee_amount'] ?? 0));

        $this->assertSame($expected, $sent);
    }

    /** Sans prestataire raccordé, on refuse : Stripe n'a nulle part où envoyer l'argent. */
    public function test_a_deposit_without_a_connected_provider_is_refused(): void
    {
        $booking = $this->booking(900.00);

        $this->expectException(ValidationException::class);
        app(OrderPaymentPlanner::class)->authorizeWithDeposit($booking, 'pm_test');
    }

    // ─── La destination de l'argent ──────────────────────────────────────────────────────────

    /** LA garantie qui compte : on ne change pas de professionnel avec de l'argent bloqué. */
    public function test_a_booking_cannot_change_provider_while_money_is_held(): void
    {
        $booking = $this->bookingWithProvider(900.00);
        $booking->forceFill([
            'stripe_payment_intent_id' => 'pi_bloque',
            'payment_status' => 'authorized',
        ])->save();

        $this->expectException(ValidationException::class);
        $booking->fresh()->update(['employe_id' => $this->connectedProvider()->id]);
    }

    /** Libérer d'abord, réassigner ensuite : le chemin sanctionné, lui, passe. */
    public function test_releasing_first_makes_reassignment_possible(): void
    {
        $booking = $this->bookingWithProvider(900.00);
        $booking->forceFill(['stripe_payment_intent_id' => 'pi_bloque', 'payment_status' => 'authorized'])->save();

        $this->stripe->stub('GET', '/v1/payment_intents/pi_bloque', ['id' => 'pi_bloque', 'object' => 'payment_intent', 'status' => 'requires_capture']);
        $this->stripe->stub('POST', '/v1/payment_intents/pi_bloque/cancel', ['id' => 'pi_bloque', 'object' => 'payment_intent', 'status' => 'canceled']);

        $released = app(OrderPaymentPlanner::class)->releaseForReassignment($booking->fresh());

        $this->assertTrue($released['released']);
        $this->assertNull($booking->fresh()->stripe_payment_intent_id);

        // Et la réassignation passe désormais sans lever.
        $booking->fresh()->update(['employe_id' => $this->connectedProvider()->id]);
        $this->assertNotNull($booking->fresh()->employe_id);
    }

    /** Une libération qui échoue chez Stripe ne se déclare PAS réussie. */
    public function test_a_failed_release_does_not_pretend_to_have_worked(): void
    {
        $booking = $this->bookingWithProvider(900.00);
        $booking->forceFill(['stripe_payment_intent_id' => 'pi_recalcitrant', 'payment_status' => 'authorized'])->save();

        $this->stripe->stub('GET', '/v1/payment_intents/pi_recalcitrant', ['error' => ['message' => 'indisponible']], 500);

        try {
            app(OrderPaymentPlanner::class)->releaseForReassignment($booking->fresh());
            $this->fail('La libération aurait dû échouer bruyamment.');
        } catch (ValidationException $e) {
            // Attendu.
        }

        $this->assertSame('pi_recalcitrant', $booking->fresh()->stripe_payment_intent_id);
        $this->assertSame('authorized', $booking->fresh()->payment_status);
    }

    /** L'acompte déjà débité est SIGNALÉ : le rembourser est une décision, pas un effet de bord. */
    public function test_an_already_captured_deposit_is_flagged_for_settlement(): void
    {
        $booking = $this->bookingWithProvider(900.00);
        $booking->forceFill([
            'stripe_payment_intent_id' => 'pi_solde',
            'payment_status' => 'authorized',
            'deposit_amount_cents' => 27000,
            'deposit_captured_at' => now(),
        ])->save();

        $this->stripe->stub('GET', '/v1/payment_intents/pi_solde', ['id' => 'pi_solde', 'object' => 'payment_intent', 'status' => 'requires_capture']);
        $this->stripe->stub('POST', '/v1/payment_intents/pi_solde/cancel', ['id' => 'pi_solde', 'object' => 'payment_intent', 'status' => 'canceled']);

        $result = app(OrderPaymentPlanner::class)->releaseForReassignment($booking->fresh());

        $this->assertSame(27000, $result['deposit_to_settle_cents']);
    }

    /** Une première attribution n'est jamais bloquée, même sur une ligne incoherente. */
    public function test_a_first_assignment_is_never_blocked_even_on_an_odd_row(): void
    {
        $booking = $this->booking(900.00);

        // Etat incoherent pose directement, sans passer par l'autorisation.
        Booking::query()->whereKey($booking->id)->update([
            'payment_status' => 'authorized',
            'stripe_payment_intent_id' => 'pi_orphelin',
        ]);

        $booking->fresh()->update(['employe_id' => $this->connectedProvider()->id]);

        $this->assertNotNull($booking->fresh()->employe_id);
    }

    // ─── Fabriques ───────────────────────────────────────────────────────────────────────────

    private function stubIntent(string $id): void
    {
        $this->stripe->stub('POST', '/v1/payment_intents', [
            'id' => $id,
            'object' => 'payment_intent',
            'status' => 'requires_capture',
        ]);
    }

    private function connectedProvider(): User
    {
        $provider = User::factory()->create([
            'role' => User::ROLE_PROVIDER,
            'stripe_connect_account_id' => 'acct_'.uniqid(),
            // Le compte doit être ABOUTI, pas seulement ouvert : c'est ce que vérifie la
            // plateforme avant d'envoyer quoi que ce soit chez Stripe.
            'stripe_connect_status' => 'active',
        ]);

        ProviderProfile::create([
            'user_id' => $provider->id,
            'status' => 'active',
        ]);

        return $provider->fresh();
    }

    private function booking(float $price): Booking
    {
        return Booking::create([
            'booking_reference' => 'CUX-'.strtoupper(uniqid()),
            'client_id' => User::factory()->client()->create(['stripe_id' => 'cus_test'])->id,
            'status' => BookingStatus::EN_ATTENTE,
            'address' => 'Rue de la Loi 1, 1000 Bruxelles',
            'estimated_price' => $price,
            'devis_estime' => $price,
            'pricing_snapshot' => ['currency' => 'EUR'],
        ]);
    }

    private function bookingWithProvider(float $price): Booking
    {
        $booking = $this->booking($price);
        $booking->update(['employe_id' => $this->connectedProvider()->id]);

        return $booking->fresh();
    }
}
