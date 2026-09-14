<?php

namespace Tests\Feature\Cancellation;

use App\Models\Booking;
use App\Models\BookingCancellationV2;
use App\Models\User;
use App\Services\CancellationV2\CancellationEngine;
use App\Services\CancellationV2\CancellationIntegrationsRunner;
use App\Services\Payments\MissionPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * « LE DÉSISTEMENT PRESTATAIRE N'EST PAS UNE ANNULATION » — décision du 2026-08-28. Une route
 * parallèle la contournait : `POST /api/v2/provider/bookings/{id}/cancel` fait passer le
 * désistement par le moteur d'annulation CLIENT, qui déduisait la pénalité du remboursement
 * du client puis la capturait sur SON empreinte.
 */
class LaPenaliteDuPrestataireNeTouchePasLeClientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('cancellation_v2.integrations.stripe_refund', true);
        Config::set('cancellation_v2.integrations.insurance_cancel', false);
        Config::set('services.stripe.secret', '');
    }

    private function reservation(): Booking
    {
        $client = User::factory()->client()->create();
        $quand = now()->addDays(2);

        $reservation = Booking::create([
            'client_id' => $client->id,
            'date' => $quand,
            'heure' => $quand->format('H:i'),
            'scheduled_at' => $quand,
            'status' => 'confirme',
            'devis_estime' => 150.0,
            'stripe_payment_intent_id' => 'pi_test_empreinte',
        ]);

        $reservation->forceFill(['payment_status' => 'authorized'])->save();

        return $reservation;
    }

    private function annulation(Booking $reservation, string $role, int $fraisCents): BookingCancellationV2
    {
        return BookingCancellationV2::create([
            'booking_id' => $reservation->id,
            'cancelled_by_user_id' => $reservation->client_id,
            'actor_role' => $role,
            'fee_percent_applied' => 0,
            'fee_amount_cents' => $fraisCents,
            'refund_amount_cents' => 0,
            'currency' => 'EUR',
            'refund_method' => 'none',
            'idempotency_key' => 'test_'.uniqid(),
            'cancelled_at' => now(),
            'integrations_log' => [],
        ]);
    }

    #[Test]
    public function la_penalite_d_un_desistement_n_est_pas_capturee_sur_l_empreinte_du_client(): void
    {
        $this->mock(MissionPaymentService::class, function ($mock) {
            $mock->shouldNotReceive('capturerLesFraisDAnnulation');
        });

        $ligne = app(CancellationIntegrationsRunner::class)
            ->run($this->annulation($this->reservation(), 'provider', 500));

        $this->assertArrayNotHasKey('stripe_fee_capture', (array) $ligne->fresh()->integrations_log);
    }

    /** LE TÉMOIN POSITIF : les frais d'une annulation CLIENT se capturent toujours. */
    #[Test]
    public function temoin_les_frais_d_une_annulation_client_se_capturent_toujours(): void
    {
        $this->mock(MissionPaymentService::class, function ($mock) {
            $mock->shouldReceive('capturerLesFraisDAnnulation')->once()->andReturnNull();
        });

        $ligne = app(CancellationIntegrationsRunner::class)
            ->run($this->annulation($this->reservation(), 'client', 500));

        $this->assertSame(
            'fee_captured',
            $ligne->fresh()->integrations_log['stripe_fee_capture']['status'] ?? null,
        );
    }

    #[Test]
    public function un_desistement_rembourse_le_client_integralement(): void
    {
        $reservation = $this->reservation();

        $devis = app(CancellationEngine::class)->quote($reservation->id, 'provider');

        $this->assertSame(
            $devis->bookingAmountCents,
            $devis->refundAmountCents,
            'le client subit le désistement : il ne finance pas la pénalité de celui qui se désiste',
        );
    }

    /** LE TÉMOIN : une annulation CLIENT continue de déduire ses frais du remboursement. */
    #[Test]
    public function temoin_une_annulation_client_deduit_toujours_ses_frais(): void
    {
        $reservation = $this->reservation();

        $devis = app(CancellationEngine::class)->quote($reservation->id, 'client');

        $this->assertSame(
            $devis->bookingAmountCents - $devis->feeAmountCents,
            $devis->refundAmountCents,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
