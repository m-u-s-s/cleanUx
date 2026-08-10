<?php

namespace Tests\Feature\Missions;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\User;
use App\Support\Domain\BookingStatus;
use App\Support\Domain\MissionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Une réservation qui démarre sans être passée par « confirmé » obtient quand même sa mission.
 *
 * Seul `confirme` déclenchait la synchronisation. Or une réservation peut atteindre l'exécution
 * sans passer par là — c'est le cas du flux ASAP. Elle n'obtenait alors aucune mission et restait
 * invisible dans l'application prestataire : ni mise en route, ni arrivée, ni codes de démarrage
 * et de fin, ni clôture, donc aucun encaissement.
 *
 * Le piège évité ici : `syncFromRendezVous` réécrit le statut de la mission avec sa valeur
 * INITIALE. L'appeler à chaque sauvegarde pendant l'exécution ramènerait une mission démarrée à
 * son point de départ. On crée donc la manquante sans resynchroniser l'existante.
 */
class MissionCreatedOnExecutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_booking_going_straight_to_en_route_gets_its_mission(): void
    {
        $booking = $this->booking();

        $booking->update(['status' => BookingStatus::EN_ROUTE]);

        $this->assertDatabaseHas('missions', ['booking_id' => $booking->id]);
    }

    public function test_a_booking_going_straight_to_sur_place_gets_its_mission(): void
    {
        $booking = $this->booking();

        $booking->update(['status' => BookingStatus::SUR_PLACE]);

        $this->assertDatabaseHas('missions', ['booking_id' => $booking->id]);
    }

    /**
     * Garantie centrale. Sans elle, chaque sauvegarde de la réservation pendant l'intervention
     * rembobinerait la mission — et avec elle l'heure de démarrage qui alimente la facturation.
     */
    public function test_it_never_rewinds_a_mission_already_underway(): void
    {
        $booking = $this->booking();
        $booking->update(['status' => BookingStatus::CONFIRME]);

        $mission = Mission::query()->where('booking_id', $booking->id)->firstOrFail();
        $startedAt = now()->subHour();
        $mission->forceFill([
            'status' => MissionStatus::STARTED,
            'actual_start_at' => $startedAt,
        ])->save();

        $booking->update(['status' => BookingStatus::SUR_PLACE]);

        $mission->refresh();
        $this->assertSame(MissionStatus::STARTED, $mission->status);
        $this->assertEquals($startedAt->format('Y-m-d H:i'), $mission->actual_start_at->format('Y-m-d H:i'));
    }

    /** Une seule mission par réservation : deux dédoubleraient dispatch, codes et encaissement. */
    public function test_it_creates_only_one_mission(): void
    {
        $booking = $this->booking();

        $booking->update(['status' => BookingStatus::EN_ROUTE]);
        $booking->update(['status' => BookingStatus::SUR_PLACE]);

        $this->assertSame(1, Mission::query()->where('booking_id', $booking->id)->count());
    }

    /** Rien à exécuter tant que la réservation attend : le comportement d'origine est conservé. */
    public function test_a_pending_booking_still_gets_nothing(): void
    {
        $this->booking();

        $this->assertSame(0, Mission::query()->count());
    }

    public function test_a_cancelled_booking_still_gets_nothing(): void
    {
        $booking = $this->booking();

        $booking->update(['status' => BookingStatus::ANNULE]);

        $this->assertSame(0, Mission::query()->count());
    }

    private function booking(): Booking
    {
        return Booking::factory()->create([
            'client_id' => User::factory()->client()->create()->id,
            'status' => BookingStatus::EN_ATTENTE,
        ]);
    }
}
