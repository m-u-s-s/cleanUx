<?php

namespace Tests\Feature\Missions;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Tests\TestCase;

/** La commande ne lisait que la chaîne `rendezVous` et sautait donc toutes les missions créées via `booking_id` — soit exactement celles qui n'ont jamais eu de destination, puisque leur chemin de création n'en écrivait aucune. */
class BackfillMissionDestinationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sleep::fake();
    }

    public function test_it_backfills_missions_attached_through_the_booking_id_column(): void
    {
        $booking = $this->makeBooking();
        $mission = Mission::create(['booking_id' => $booking->id, 'status' => 'planned']);

        $this->artisan('missions:backfill-destinations')->assertSuccessful();

        // Bruxelles : la réponse Nominatim stubbée par Tests\TestCase.
        $this->assertSame('50.8503000', $mission->refresh()->destination_lat);
    }

    public function test_it_still_backfills_missions_attached_through_the_legacy_column(): void
    {
        $booking = $this->makeBooking();
        $mission = Mission::create(['booking_id' => $booking->id, 'status' => 'planned']);

        $this->artisan('missions:backfill-destinations')->assertSuccessful();

        $this->assertSame('50.8503000', $mission->refresh()->destination_lat);
    }

    public function test_it_leaves_missions_that_already_have_a_destination_alone(): void
    {
        $booking = $this->makeBooking();
        $mission = Mission::create([
            'booking_id' => $booking->id,
            'status' => 'planned',
            'destination_lat' => 48.8566,
            'destination_lng' => 2.3522,
        ]);

        $this->artisan('missions:backfill-destinations')->assertSuccessful();

        $this->assertSame('48.8566000', $mission->refresh()->destination_lat);
    }

    protected function makeBooking(): Booking
    {
        $client = User::factory()->create();

        return Booking::withoutEvents(fn () => Booking::create([
            'booking_reference' => 'CUX-'.strtoupper(Str::random(6)),
            'customer_user_id' => $client->id,
            'client_id' => $client->id,
            'address' => '12 rue du Test',
            'city' => 'Bruxelles',
            'postal_code' => '1000',
            'scheduled_date' => now()->addDay()->toDateString(),
            'scheduled_time' => '10:00:00',
            'status' => 'confirme',
            'currency' => 'EUR',
            'priority' => 'normal',
            'booking_mode' => 'scheduled',
        ]));
    }
}
