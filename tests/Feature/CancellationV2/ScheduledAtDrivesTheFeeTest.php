<?php

namespace Tests\Feature\CancellationV2;

use App\Models\Booking;
use App\Models\User;
use App\Services\CancellationV2\CancellationEngine;
use Database\Seeders\CancellationPoliciesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * L'HEURE du rendez-vous décide des frais d'annulation, pas le jour.
 *
 * `scheduled_at` n'était rempli par aucun chemin — la colonne existait, absente de `$fillable`,
 * donc toute écriture était ignorée en silence. Le moteur d'annulation la lit pourtant en premier
 * et retombait sur `date`, de type DATE sur MySQL : tronquée au jour. Les frais se calculaient
 * contre MINUIT.
 *
 * Concrètement : un client annulant un rendez-vous de 17 h trente heures à l'avance était facturé
 * au palier « moins de 24 h ». Sur une prestation de 100 €, 50 € au lieu de 25 €.
 *
 * Invisible sur SQLite, qui stocke `date` en texte et y conserve l'heure — c'est pour cela que le
 * défaut a vécu aussi longtemps.
 */
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

    /**
     * LA garantie d'argent : l'heure est prise en compte.
     *
     * Le rendez-vous est dans 30 heures, donc dans la tranche 24–48 h à 25 %. Calculé contre
     * minuit, il tomberait sous les 24 h et serait facturé 50 %.
     */
    public function test_the_appointment_hour_decides_the_fee_not_midnight(): void
    {
        /*
         * L'HEURE EST FIGÉE, et ce n'est pas du confort.
         *
         * `now()->addHours(30)->setTime(21, 0)` ne donne pas un écart de 30 heures : il donne un
         * écart qui DÉPEND DE L'HEURE QU'IL EST. Exécuté vers 21 h, l'ajout de 30 heures bascule au
         * surlendemain, `setTime(21:00)` porte l'écart à 48 h, le palier 24–48 h ne s'applique plus
         * et le test tombe à 0 % au lieu de 25 %.
         *
         * Ce test-là était donc VERT le matin et ROUGE le soir, sur une garantie d'argent. Un test
         * qui rougit un tiers de la journée apprend à être ignoré, puis laisse passer le vrai
         * défaut qu'il gardait.
         */
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

    /**
     * La même garantie, une annulation faite EN SOIRÉE.
     *
     * C'est l'heure à laquelle le test précédent tombait avant d'être figé. Le palier ne doit pas
     * dépendre du moment où le client annule, mais de l'écart jusqu'au rendez-vous.
     */
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
