<?php

namespace Tests\Feature\CancellationV2;

use App\Models\Booking;
use App\Models\User;
use App\Services\CancellationV2\CancellationEngine;
use Database\Seeders\CancellationPoliciesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/** L'HEURE du rendez-vous décide des frais d'annulation, pas le jour. */
class ScheduledAtDrivesTheFeeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Sans politiques amorcées, aucun palier ne s'applique et tout est remboursé : le test
        // passerait pour de mauvaises raisons.
        $this->seed(CancellationPoliciesSeeder::class);
        Config::set('cancellation_v2.enabled', true);
        Config::set('cancellation_v2.default_refund_method', 'mock');
        Config::set('cancellation_v2.integrations.stripe_refund', false);
        Config::set('cancellation_v2.integrations.insurance_cancel', false);
    }

    /** Enregistrer une réservation renseigne l'horodatage complet du rendez-vous. */
    public function test_saving_a_booking_fills_the_full_appointment_timestamp(): void
    {
        $booking = $this->bookingAt('2026-09-15', '17:30:00');

        $this->assertNotNull(
            $booking->scheduled_at,
            'L’horodatage complet doit être reconstitué à partir du jour et de l’heure.',
        );
        $this->assertSame('2026-09-15 17:30:00', $booking->scheduled_at->format('Y-m-d H:i:s'));
    }

    /** LA garantie d'argent : l'heure est prise en compte. */
    public function test_the_appointment_hour_decides_the_fee_not_midnight(): void
    {
        // L'HEURE EST FIGÉE, et ce n'est pas du confort.
        $this->travelTo('2026-08-03 10:00:00');

        $client = User::factory()->client()->create();

        // 30 heures d'ici, à une heure tardive : minuit du même jour est, lui, à moins de 24 h.
        $rendezVous = now()->addHours(30)->setTime(21, 0);

        $booking = Booking::create([
            'client_id' => $client->id,
            'date' => $rendezVous,
            'heure' => $rendezVous->format('H:i:s'),
            'status' => 'confirme',
            'devis_estime' => 100.0,
        ]);

        $quote = app(CancellationEngine::class)->quote($booking->id, 'client');

        $this->assertEqualsWithDelta(25.0, $quote->feePercent, 0.01);
        $this->assertSame(2500, $quote->feeAmountCents);
        $this->assertSame(7500, $quote->refundAmountCents);
    }

    /** La même garantie, une annulation faite EN SOIRÉE. */
    public function test_the_tier_does_not_depend_on_the_hour_of_the_cancellation(): void
    {
        $this->travelTo('2026-08-03 21:30:00');

        $client = User::factory()->client()->create();
        $rendezVous = now()->addHours(30);

        $booking = Booking::create([
            'client_id' => $client->id,
            'date' => $rendezVous,
            'heure' => $rendezVous->format('H:i:s'),
            'status' => 'confirme',
            'devis_estime' => 100.0,
        ]);

        $quote = app(CancellationEngine::class)->quote($booking->id, 'client');

        $this->assertEqualsWithDelta(25.0, $quote->feePercent, 0.01);
    }

    /** Un horodatage déjà fourni n'est pas écrasé : l'appelant sait mieux que la reconstitution. */
    public function test_an_explicit_timestamp_is_kept(): void
    {
        $client = User::factory()->client()->create();

        $booking = Booking::create([
            'client_id' => $client->id,
            'date' => '2026-09-15',
            'heure' => '09:00:00',
            'scheduled_at' => '2026-09-15 14:45:00',
            'status' => 'confirme',
            'devis_estime' => 100.0,
        ]);

        $this->assertSame('2026-09-15 14:45:00', $booking->fresh()->scheduled_at->format('Y-m-d H:i:s'));
    }

    private function bookingAt(string $date, string $heure): Booking
    {
        return Booking::create([
            'client_id' => User::factory()->client()->create()->id,
            'date' => $date,
            'heure' => $heure,
            'status' => 'confirme',
            'devis_estime' => 100.0,
        ])->fresh();
    }
}
