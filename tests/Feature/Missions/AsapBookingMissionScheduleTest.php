<?php

namespace Tests\Feature\Missions;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\ServiceCatalog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Une réservation `confirme` réveille RendezVousObserver, qui synchronise une mission via
 * MissionFromRendezVousSyncService. Ce chemin écrivait planned_start_at = 1970-01-01 00:00:00,
 * valeur qu'une colonne TIMESTAMP MySQL refuse en mode strict (erreur 1292) : le chemin de
 * réservation ASAP échouait donc en production, alors que SQLite acceptait la valeur sans
 * broncher et laissait la suite aveugle.
 *
 * Ces tests portent sur la VALEUR de planned_start_at, pas sur l'absence d'exception : c'est le
 * seul angle qui rend le défaut visible sur SQLite.
 */
class AsapBookingMissionScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_confirmed_booking_syncs_a_mission_with_the_booking_schedule(): void
    {
        $booking = $this->makeConfirmedBooking('2026-07-29', '10:00:00');

        $mission = Mission::where('booking_id', $booking->id)->firstOrFail();

        $this->assertSame(
            '2026-07-29 10:00:00',
            $mission->planned_start_at?->format('Y-m-d H:i:s'),
            'planned_start_at doit porter la date et heure de la réservation.'
        );
    }

    /**
     * Anti-régression ciblée : l'epoch est ce que produisait `date(..., strtotime(false))`, et
     * c'est précisément ce que MySQL rejette. Aucune mission ne doit jamais le porter.
     */
    public function test_the_synced_mission_never_carries_the_unix_epoch_as_a_schedule(): void
    {
        $booking = $this->makeConfirmedBooking('2026-07-29', '10:00:00');

        $mission = Mission::where('booking_id', $booking->id)->firstOrFail();

        $this->assertNotSame('1970-01-01', $mission->planned_start_at?->format('Y-m-d'));
        $this->assertNotSame('1970-01-01', $mission->planned_end_at?->format('Y-m-d'));
    }

    public function test_the_synced_mission_gets_an_end_time_after_its_start(): void
    {
        $booking = $this->makeConfirmedBooking('2026-07-29', '10:00:00', 90);

        $mission = Mission::where('booking_id', $booking->id)->firstOrFail();

        $this->assertNotNull($mission->planned_end_at);
        $this->assertTrue(
            $mission->planned_end_at->greaterThan($mission->planned_start_at),
            'planned_end_at doit suivre planned_start_at.'
        );
    }

    /**
     * La source du défaut : syncLegacyAliases() recopie scheduled_time (casté datetime:H:i, donc
     * un Carbon) dans `heure`, colonne TIME sans cast. Tout le code qui lit cet alias fait
     * `substr((string) $heure, 0, 5|8)` en supposant "10:00:00" — sur un Carbon rendu
     * "2026-07-28 10:00:00", ce découpage produit "2026-07-", d'où un datetime ininterprétable.
     */
    public function test_the_legacy_time_alias_holds_a_clock_time_not_a_datetime(): void
    {
        $booking = new Booking(['scheduled_date' => '2026-07-29', 'scheduled_time' => '10:00:00']);

        $booking->syncLegacyAliases();

        $this->assertSame('10:00:00', (string) $booking->heure);
        // `date` porte un cast `date` : rester un Carbon y est légitime, il se reformate seul.
        $this->assertSame('2026-07-29', $booking->date->format('Y-m-d'));
        // Le découpage que pratique tout le code appelant doit rendre une heure lisible.
        $this->assertSame('10:00', substr((string) $booking->heure, 0, 5));
    }

    protected function makeConfirmedBooking(string $date, string $time, ?int $duration = null): Booking
    {
        $client = User::factory()->create();
        $catalog = ServiceCatalog::factory()->create();

        return Booking::create([
            'booking_reference' => 'CUX-'.strtoupper(Str::random(6)),
            'customer_user_id' => $client->id,
            'client_id' => $client->id,
            'service_catalog_id' => $catalog->id,
            'address' => 'Rue de la Loi 1',
            'city' => 'Bruxelles',
            'postal_code' => '1000',
            'scheduled_date' => $date,
            'scheduled_time' => $time,
            'estimated_duration_minutes' => $duration,
            'status' => 'confirme',
            'currency' => 'EUR',
            'priority' => 'urgent',
            'booking_mode' => 'asap',
        ]);
    }
}
