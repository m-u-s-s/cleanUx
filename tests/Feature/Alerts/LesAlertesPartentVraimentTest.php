<?php

namespace Tests\Feature\Alerts;

use App\Events\BusinessAlertRaised;
use App\Models\Booking;
use App\Models\ProviderPayout;
use App\Models\StripeReconciliationRun;
use App\Models\User;
use App\Services\Payments\StripeReconciliationService;
use App\Services\Payments\Webhooks\StripeWebhookHandlers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Sentry\State\Scope;
use Stripe\ApiRequestor;
use Stripe\Stripe;
use Tests\Support\Stripe\FakeStripeHttpClient;
use Tests\Support\Stripe\StripeFakeResponses;
use Tests\TestCase;

/**
 * Les trois chemins REELS qui doivent lever une alerte metier, et les trois temoins qui
 * prouvent qu'ils ne la levent pas a chaque passage.
 */
class LesAlertesPartentVraimentTest extends TestCase
{
    use RefreshDatabase;

    private FakeStripeHttpClient $stripe;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('webhooks_v2.enabled', false);
        Config::set('accounting_v2.auto_post_enabled', false);
        Config::set(['cashier.secret' => 'sk_test_fake', 'services.stripe.secret' => 'sk_test_fake']);
        Stripe::setApiKey('sk_test_fake');
        $this->stripe = new FakeStripeHttpClient;
        ApiRequestor::setHttpClient($this->stripe);
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);
        parent::tearDown();
    }

    /**
     * Procede de la tache 1 : pas d'Event::fake(), qui couperait les deux ecouteurs reels.
     * Un TROISIEME ecouteur observe sans rien empecher, et un espion Sentry remplace le client.
     *
     * @return array{captures: list<BusinessAlertRaised>, sentry: object}
     */
    private function observe(callable $chemin): array
    {
        $captures = [];
        Event::listen(BusinessAlertRaised::class, function (BusinessAlertRaised $e) use (&$captures): void {
            $captures[] = $e;
        });

        $sentry = $this->sentrySpy();
        app()->instance('sentry', $sentry);

        $chemin();

        return ['captures' => $captures, 'sentry' => $sentry];
    }

    /** Espion Sentry minimal : rejoue le callback avec un vrai Scope, capture chaque message. */
    private function sentrySpy(): object
    {
        return new class
        {
            /** @var list<array{message: string, level: mixed}> */
            public array $captured = [];

            public function withScope(callable $callback): void
            {
                $callback(new Scope);
            }

            public function captureMessage(string $message, mixed $level = null): void
            {
                $this->captured[] = ['message' => $message, 'level' => $level];
            }
        };
    }

    /** @param list<BusinessAlertRaised> $captures */
    private function uneCapture(array $captures, string $cle): BusinessAlertRaised
    {
        $trouvees = array_values(array_filter($captures, fn (BusinessAlertRaised $e): bool => $e->key === $cle));

        $this->assertCount(1, $trouvees, "Un seul BusinessAlertRaised attendu pour la cle « {$cle} ».");

        return $trouvees[0];
    }

    /**
     * @param  list<array{message: string, level: mixed}>  $captured
     * @return array{message: string, level: mixed}
     */
    private function messageSentryPour(array $captured, string $cle): array
    {
        $trouves = array_values(array_filter(
            $captured,
            fn (array $appel): bool => str_contains($appel['message'], "[{$cle}]"),
        ));

        $this->assertCount(1, $trouves, "La voie Sentry devrait recevoir exactement un message pour « {$cle} ».");

        return $trouves[0];
    }

    /** @param array{captures: list<BusinessAlertRaised>, sentry: object} $resultat */
    private function assertAucuneAlerte(array $resultat): void
    {
        $cles = array_map(fn (BusinessAlertRaised $e): string => $e->key, $resultat['captures']);

        $this->assertSame([], $cles, 'Ce chemin ne doit lever aucune alerte metier.');
        $this->assertSame([], $resultat['sentry']->captured, 'Ce chemin ne doit rien envoyer a Sentry.');
        $this->assertDatabaseCount('business_alertes', 0);
    }

    private function reservationPour(string $piId, string $statutPaiement = 'pending'): Booking
    {
        $client = User::factory()->client()->create();
        $booking = Booking::factory()->create(['client_id' => $client->id, 'status' => 'en_attente']);
        $booking->forceFill([
            'stripe_payment_intent_id' => $piId,
            'payment_status' => $statutPaiement,
        ])->save();

        return $booking->fresh();
    }

    // ─────────────────────────────────────────────────────────────
    // 1. payment_intent.payment_failed → payment_capture_failed
    // ─────────────────────────────────────────────────────────────

    public function test_un_paiement_en_echec_leve_l_alerte_de_capture(): void
    {
        Notification::fake();
        $booking = $this->reservationPour('pi_echec_capture');

        $resultat = $this->observe(fn () => app(StripeWebhookHandlers::class)->handlePaymentIntentFailed([
            'id' => 'pi_echec_capture',
            'amount' => 9000,
            'currency' => 'eur',
            'last_payment_error' => ['message' => 'Card declined', 'code' => 'card_declined'],
        ]));

        $mesuree = $this->uneCapture($resultat['captures'], 'payment_capture_failed');

        $this->assertSame('critical', $mesuree->level);
        $this->assertEqualsCanonicalizing(
            ['booking_id', 'client_id', 'amount', 'currency'],
            array_keys($mesuree->context),
        );
        $this->assertSame($booking->id, $mesuree->context['booking_id']);
        $this->assertSame($booking->client_id, $mesuree->context['client_id']);
        $this->assertSame('EUR', $mesuree->context['currency']);
        $this->assertNull($mesuree->context['amount']);

        $appel = $this->messageSentryPour($resultat['sentry']->captured, 'payment_capture_failed');
        $this->assertSame('fatal', (string) $appel['level']);

        $this->assertDatabaseHas('business_alertes', [
            'cle' => 'payment_capture_failed',
            'niveau' => 'critical',
            'entite_type' => 'booking',
            'entite_id' => $booking->id,
        ]);

        // Le comportement d'avant reste : la reservation passe bien en `failed`.
        $this->assertSame('failed', $booking->fresh()->payment_status);
    }

    /** TEMOIN : un paiement qui reussit ne doit alerter personne. */
    public function test_un_paiement_reussi_ne_leve_rien(): void
    {
        $booking = $this->reservationPour('pi_succes_capture');
        $this->stripe->stub('GET', '/v1/payment_intents/pi_succes_capture',
            StripeFakeResponses::paymentIntent('pi_succes_capture', 'succeeded'));

        $resultat = $this->observe(fn () => app(StripeWebhookHandlers::class)->handlePaymentIntentSucceeded([
            'id' => 'pi_succes_capture',
            'amount' => 12000,
            'currency' => 'eur',
        ]));

        $this->assertAucuneAlerte($resultat);
        $this->assertSame('captured', $booking->fresh()->payment_status);
    }

    // ─────────────────────────────────────────────────────────────
    // 2. payout.failed → payout_failed
    // ─────────────────────────────────────────────────────────────

    public function test_un_versement_en_echec_leve_l_alerte_de_versement(): void
    {
        $payout = ProviderPayout::factory()->create(['provider_payout_id' => 'po_echec']);

        $resultat = $this->observe(fn () => app(StripeWebhookHandlers::class)->handlePayoutFailed([
            'id' => 'po_echec',
            'failure_code' => 'account_closed',
            'failure_message' => 'bank declined',
        ]));

        $mesuree = $this->uneCapture($resultat['captures'], 'payout_failed');

        $this->assertSame('critical', $mesuree->level);
        $this->assertEqualsCanonicalizing(
            ['payout_id', 'provider_user_id', 'amount', 'currency', 'provider_payout_id'],
            array_keys($mesuree->context),
        );
        $this->assertSame($payout->id, $mesuree->context['payout_id']);
        $this->assertSame($payout->provider_user_id, $mesuree->context['provider_user_id']);
        $this->assertEquals($payout->amount, $mesuree->context['amount']);
        $this->assertSame($payout->currency, $mesuree->context['currency']);
        $this->assertSame('po_echec', $mesuree->context['provider_payout_id']);

        $appel = $this->messageSentryPour($resultat['sentry']->captured, 'payout_failed');
        $this->assertSame('fatal', (string) $appel['level']);

        $this->assertDatabaseHas('business_alertes', ['cle' => 'payout_failed', 'niveau' => 'critical']);

        // Le comportement d'avant reste : le versement est bien marque en echec.
        $this->assertSame(ProviderPayout::STATUS_FAILED, $payout->fresh()->status);
    }

    /** TEMOIN : un versement qui aboutit ne doit alerter personne. */
    public function test_un_versement_reussi_ne_leve_rien(): void
    {
        $payout = ProviderPayout::factory()->create(['provider_payout_id' => 'po_succes']);

        $resultat = $this->observe(fn () => app(StripeWebhookHandlers::class)->handlePayoutPaid([
            'id' => 'po_succes',
        ]));

        $this->assertAucuneAlerte($resultat);
        $this->assertSame(ProviderPayout::STATUS_PAID, $payout->fresh()->status);
    }

    // ─────────────────────────────────────────────────────────────
    // 3. Reconciliation Stripe ↔ DB → reconciliation_divergence
    // ─────────────────────────────────────────────────────────────

    public function test_une_reconciliation_qui_reclame_une_attention_leve_l_alerte(): void
    {
        // Un PaymentIntent Stripe sans contrepartie locale : severite `error`, donc
        // `requires_attention` >= 1 — la variable qui dit que le passage reclame une attention.
        $this->stripe->stub('GET', '/v1/payment_intents', [
            'object' => 'list',
            'url' => '/v1/payment_intents',
            'has_more' => false,
            'data' => [StripeFakeResponses::paymentIntent('pi_orphelin_stripe', 'succeeded')],
        ]);

        $run = null;
        $resultat = $this->observe(function () use (&$run): void {
            $run = app(StripeReconciliationService::class)
                ->run(StripeReconciliationRun::SCOPE_PAYMENT_INTENTS);
        });

        $this->assertSame(StripeReconciliationRun::STATUS_COMPLETED, $run->status);
        $this->assertSame(1, $run->requires_attention);

        $mesuree = $this->uneCapture($resultat['captures'], 'reconciliation_divergence');

        $this->assertSame('critical', $mesuree->level);
        $this->assertEqualsCanonicalizing(['detail'], array_keys($mesuree->context));
        $this->assertSame($run->id, $mesuree->context['detail']['run_id']);
        $this->assertSame(StripeReconciliationRun::SCOPE_PAYMENT_INTENTS, $mesuree->context['detail']['scope']);
        $this->assertSame(1, $mesuree->context['detail']['requires_attention']);
        $this->assertSame(1, $mesuree->context['detail']['mismatches_found']);

        $appel = $this->messageSentryPour($resultat['sentry']->captured, 'reconciliation_divergence');
        $this->assertSame('fatal', (string) $appel['level']);

        $this->assertDatabaseHas('business_alertes', ['cle' => 'reconciliation_divergence', 'niveau' => 'critical']);
    }

    /** TEMOIN : un passage propre ne doit alerter personne. */
    public function test_une_reconciliation_sans_divergence_ne_leve_rien(): void
    {
        $this->stripe->stub('GET', '/v1/payment_intents', [
            'object' => 'list',
            'url' => '/v1/payment_intents',
            'has_more' => false,
            'data' => [],
        ]);
        $this->stripe->stub('GET', '/v1/payouts', [
            'object' => 'list',
            'url' => '/v1/payouts',
            'has_more' => false,
            'data' => [],
        ]);

        $run = null;
        $resultat = $this->observe(function () use (&$run): void {
            $run = app(StripeReconciliationService::class)->run(StripeReconciliationRun::SCOPE_ALL);
        });

        $this->assertSame(StripeReconciliationRun::STATUS_COMPLETED, $run->status);
        $this->assertSame(0, $run->requires_attention);
        $this->assertAucuneAlerte($resultat);
    }

    /** TEMOIN de severite : un ecart de simple avertissement ne reclame pas d'attention. */
    public function test_un_ecart_de_simple_avertissement_ne_leve_rien(): void
    {
        $this->stripe->stub('GET', '/v1/payouts', [
            'object' => 'list',
            'url' => '/v1/payouts',
            'has_more' => false,
            'data' => [[
                'id' => 'po_orphelin',
                'object' => 'payout',
                'status' => 'paid',
                'amount' => 5000,
                'currency' => 'eur',
                'created' => 1700000000,
            ]],
        ]);

        $run = null;
        $resultat = $this->observe(function () use (&$run): void {
            $run = app(StripeReconciliationService::class)->run(StripeReconciliationRun::SCOPE_PAYOUTS);
        });

        $this->assertSame(1, $run->mismatches_found);
        $this->assertSame(0, $run->requires_attention);
        $this->assertAucuneAlerte($resultat);
    }
}
