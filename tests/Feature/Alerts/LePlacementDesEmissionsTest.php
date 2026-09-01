<?php

namespace Tests\Feature\Alerts;

use App\Events\BusinessAlertRaised;
use App\Models\Booking;
use App\Models\StripeReconciliationRun;
use App\Models\User;
use App\Services\Payments\StripeReconciliationService;
use App\Services\Payments\Webhooks\StripeWebhookHandlers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Stripe\ApiRequestor;
use Stripe\Stripe;
use Tests\Support\Stripe\FakeStripeHttpClient;
use Tests\Support\Stripe\StripeFakeResponses;
use Tests\TestCase;

/**
 * OU les deux emissions sont posees, pas seulement qu'elles partent. Un ecouteur en panne
 * revele le placement : hors zone dangereuse il remonte, dedans il est avale ou fait tout rater.
 */
class LePlacementDesEmissionsTest extends TestCase
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

    /** Un TROISIEME ecouteur qui leve, sans Event::fake() : les deux ecouteurs reels tournent avant. */
    private function ecouteurEnPanne(): \RuntimeException
    {
        $panne = new \RuntimeException('ecouteur d alerte en panne');

        Event::listen(BusinessAlertRaised::class, function () use ($panne): void {
            throw $panne;
        });

        return $panne;
    }

    /** @return ?\Throwable ce qui est remonte de l'appel, ou null s'il a abouti */
    private function remonteeDe(callable $chemin): ?\Throwable
    {
        try {
            $chemin();
        } catch (\Throwable $e) {
            return $e;
        }

        return null;
    }

    private function reservationPour(string $piId): Booking
    {
        $client = User::factory()->client()->create();
        $booking = Booking::factory()->create(['client_id' => $client->id, 'status' => 'en_attente']);
        $booking->forceFill([
            'stripe_payment_intent_id' => $piId,
            'payment_status' => 'pending',
        ])->save();

        return $booking->fresh();
    }

    /** @return array<string, mixed> */
    private function intentEnEchec(string $piId): array
    {
        return [
            'id' => $piId,
            'amount' => 9000,
            'currency' => 'eur',
            'last_payment_error' => ['message' => 'Card declined', 'code' => 'card_declined'],
        ];
    }

    private function stubUnEcartQuiReclameUneAttention(): void
    {
        // Un PaymentIntent Stripe sans contrepartie locale : severite `error`, donc
        // `requires_attention` >= 1 — la seule condition qui declenche l'emission.
        $this->stripe->stub('GET', '/v1/payment_intents', [
            'object' => 'list',
            'url' => '/v1/payment_intents',
            'has_more' => false,
            'data' => [StripeFakeResponses::paymentIntent('pi_orphelin_placement', 'succeeded')],
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // 1. Webhook : l'emission doit rester HORS du `catch` qui avale
    // ─────────────────────────────────────────────────────────────

    /**
     * TOMBE si l'emission passe dans le `catch (\Throwable)` de la notification client : la panne
     * y serait avalee (rien ne remonte) et journalisee sous le nom d'une panne de notification.
     */
    public function test_un_ecouteur_en_panne_remonte_et_n_est_pas_maquille_en_panne_de_notification(): void
    {
        Notification::fake();
        Log::spy();
        $booking = $this->reservationPour('pi_placement_webhook');
        $panne = $this->ecouteurEnPanne();

        $remontee = $this->remonteeDe(fn () => app(StripeWebhookHandlers::class)
            ->handlePaymentIntentFailed($this->intentEnEchec('pi_placement_webhook')));

        $this->assertSame(
            $panne,
            $remontee,
            "L'emission doit rester HORS du catch de la notification : dedans, la panne serait avalee.",
        );
        Log::shouldNotHaveReceived('warning', ['[payment_failed_webhook] notification failed', Mockery::any()]);

        // Les deux ecouteurs reels ont tourne AVANT le notre : l'alerte est bien partie.
        $this->assertDatabaseHas('business_alertes', [
            'cle' => 'payment_capture_failed',
            'entite_type' => 'booking',
            'entite_id' => $booking->id,
        ]);
    }

    /** TEMOIN : sans ecouteur en panne, ce meme webhook aboutit — le chemin mesure n'est pas casse. */
    public function test_temoin_un_ecouteur_sain_laisse_le_webhook_aboutir(): void
    {
        Notification::fake();
        Log::spy();
        $booking = $this->reservationPour('pi_placement_webhook_temoin');

        $resultat = null;
        $remontee = $this->remonteeDe(function () use (&$resultat): void {
            $resultat = app(StripeWebhookHandlers::class)
                ->handlePaymentIntentFailed($this->intentEnEchec('pi_placement_webhook_temoin'));
        });

        $this->assertNull($remontee);
        $this->assertSame('processed', $resultat['status']);
        $this->assertSame($booking->id, $resultat['details']['booking_id']);
        Log::shouldNotHaveReceived('warning', ['[payment_failed_webhook] notification failed', Mockery::any()]);
        $this->assertDatabaseHas('business_alertes', ['cle' => 'payment_capture_failed']);
    }

    // ─────────────────────────────────────────────────────────────
    // 2. Reconciliation : l'emission doit rester HORS du `try` qui recrit l'etat
    // ─────────────────────────────────────────────────────────────

    /**
     * TOMBE si l'emission passe dans le `try` : le `catch` recrirait le passage en STATUS_FAILED
     * et lui collerait un `error_message`, pour une panne qui n'est pas celle de la reconciliation.
     */
    public function test_un_ecouteur_en_panne_ne_fait_pas_retomber_le_passage_en_echec(): void
    {
        $this->stubUnEcartQuiReclameUneAttention();
        $panne = $this->ecouteurEnPanne();

        $remontee = $this->remonteeDe(fn () => app(StripeReconciliationService::class)
            ->run(StripeReconciliationRun::SCOPE_PAYMENT_INTENTS));

        $this->assertSame($panne, $remontee);

        // `run()` ne rend rien quand la panne remonte : le passage se relit en base.
        $passage = StripeReconciliationRun::query()->latest('id')->firstOrFail();

        $this->assertSame(
            StripeReconciliationRun::STATUS_COMPLETED,
            $passage->status,
            "L'emission doit rester HORS du try : dedans, le catch marquerait FAILED un passage abouti.",
        );
        $this->assertNull($passage->error_message);
        $this->assertSame(1, $passage->requires_attention);
    }

    /** TEMOIN : sans ecouteur en panne, le meme passage aboutit — le chemin mesure n'est pas casse. */
    public function test_temoin_un_ecouteur_sain_laisse_le_passage_en_completed(): void
    {
        $this->stubUnEcartQuiReclameUneAttention();

        $passage = null;
        $remontee = $this->remonteeDe(function () use (&$passage): void {
            $passage = app(StripeReconciliationService::class)
                ->run(StripeReconciliationRun::SCOPE_PAYMENT_INTENTS);
        });

        $this->assertNull($remontee);
        $this->assertSame(StripeReconciliationRun::STATUS_COMPLETED, $passage->status);
        $this->assertNull($passage->error_message);
        $this->assertSame(1, $passage->requires_attention);
        $this->assertDatabaseHas('business_alertes', ['cle' => 'reconciliation_divergence']);
    }
}
