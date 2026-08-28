<?php

namespace Tests\Feature\Cancellation;

use App\Models\Booking;
use App\Models\BookingCancellationV2;
use App\Models\User;
use App\Services\CancellationV2\CancellationIntegrationsRunner;
use App\Services\Payments\MissionPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

/**
 * Sur une empreinte non capturee, on PREND les frais ; on ne rembourse pas.
 * Le moteur ne connaissait que le remboursement : les frais restaient sur l'empreinte, jamais pris.
 */
class LesFraisSeCapturentSurLEmpreinteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('cancellation_v2.integrations.stripe_refund', true);
        Config::set('cancellation_v2.integrations.insurance_cancel', false);
        // Pas de secret : le chemin de remboursement rend `manual` sans joindre Stripe.
        Config::set('services.stripe.secret', '');
    }

    private function annulation(string $statutDePaiement, int $fraisCents, int $remboursementCents): BookingCancellationV2
    {
        $client = User::factory()->client()->create();
        $quand = now()->addDays(2);

        $reservation = Booking::create([
            'client_id' => $client->id,
            'date' => $quand,
            'heure' => $quand->format('H:i'),
            'scheduled_at' => $quand,
            'status' => 'annule',
            'devis_estime' => 100.0,
            'stripe_payment_intent_id' => 'pi_test_empreinte',
        ]);

        // Les colonnes d'argent ne sont pas assignables en masse, par garde volontaire.
        $reservation->forceFill(['payment_status' => $statutDePaiement])->save();

        return BookingCancellationV2::create([
            'booking_id' => $reservation->id,
            'cancelled_by_user_id' => $client->id,
            'actor_role' => 'client',
            'fee_percent_applied' => 25,
            'fee_amount_cents' => $fraisCents,
            'refund_amount_cents' => $remboursementCents,
            'currency' => 'EUR',
            'refund_method' => 'stripe',
            'idempotency_key' => 'test_'.uniqid(),
            'cancelled_at' => now(),
            'integrations_log' => [],
        ]);
    }

    public function test_une_empreinte_autorisee_fait_capturer_les_frais(): void
    {
        $this->mock(MissionPaymentService::class, function ($mock) {
            $mock->shouldReceive('capturerLesFraisDAnnulation')
                ->once()
                ->withArgs(fn (Booking $b, int $cents) => $cents === 2500)
                ->andReturnNull();
        });

        $ligne = app(CancellationIntegrationsRunner::class)->run($this->annulation('authorized', 2500, 7500));

        $this->assertSame('fee_captured', $ligne->fresh()->integrations_log['stripe_refund']['status'] ?? null);
    }

    /**
     * TEMOIN — un paiement DEJA encaisse continue de passer par le remboursement. Sans lui, ce
     * test resterait vert si la capture avalait tous les cas.
     */
    public function test_temoin_un_paiement_encaisse_reste_un_remboursement(): void
    {
        $this->mock(MissionPaymentService::class, function ($mock) {
            $mock->shouldNotReceive('capturerLesFraisDAnnulation');
        });

        $ligne = app(CancellationIntegrationsRunner::class)->run($this->annulation('captured', 2500, 7500));

        $this->assertNotSame('fee_captured', $ligne->fresh()->integrations_log['stripe_refund']['status'] ?? null);
    }

    /** Aucun frais a prendre : rien a capturer, on laisse le remboursement faire son travail. */
    public function test_sans_frais_l_empreinte_ne_declenche_aucune_capture(): void
    {
        $this->mock(MissionPaymentService::class, function ($mock) {
            $mock->shouldNotReceive('capturerLesFraisDAnnulation');
        });

        $ligne = app(CancellationIntegrationsRunner::class)->run($this->annulation('authorized', 0, 10000));

        $this->assertNotSame('fee_captured', $ligne->fresh()->integrations_log['stripe_refund']['status'] ?? null);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
