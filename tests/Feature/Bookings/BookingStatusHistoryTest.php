<?php

namespace Tests\Feature\Bookings;

use App\Models\Booking;
use App\Models\BookingStatusHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** PERSONNE NE POUVAIT DIRE QUI AVAIT CHANGÉ LE STATUT D'UNE RÉSERVATION. */
class BookingStatusHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_creation_ouvre_l_historique_sur_le_statut_initial(): void
    {
        $reservation = Booking::factory()->create(['status' => 'en_attente']);

        $lignes = BookingStatusHistory::query()->where('booking_id', $reservation->id)->get();

        $this->assertCount(1, $lignes);
        $this->assertNull($lignes->first()->from_status);
        $this->assertSame('en_attente', $lignes->first()->to_status);
    }

    public function test_un_changement_de_statut_est_consigne_avec_son_avant_et_son_apres(): void
    {
        $reservation = Booking::factory()->create(['status' => 'en_attente']);

        $reservation->update(['status' => 'confirme']);

        $derniere = BookingStatusHistory::query()
            ->where('booking_id', $reservation->id)
            ->orderByDesc('id')
            ->first();

        $this->assertSame('en_attente', $derniere->from_status);
        $this->assertSame('confirme', $derniere->to_status);
    }

    public function test_l_auteur_du_changement_est_retenu_quand_il_y_en_a_un(): void
    {
        $acteur = User::factory()->admin()->create();
        $reservation = Booking::factory()->create(['status' => 'en_attente']);

        $this->actingAs($acteur);
        $reservation->update(['status' => 'confirme']);

        $derniere = BookingStatusHistory::query()
            ->where('booking_id', $reservation->id)
            ->orderByDesc('id')
            ->first();

        $this->assertSame($acteur->id, $derniere->changed_by);
    }

    public function test_une_modification_qui_ne_touche_pas_le_statut_n_ecrit_rien(): void
    {
        $reservation = Booking::factory()->create(['status' => 'en_attente']);
        $avant = BookingStatusHistory::query()->where('booking_id', $reservation->id)->count();

        $reservation->update(['customer_comment' => 'Portail au fond de la cour.']);

        $this->assertSame(
            $avant,
            BookingStatusHistory::query()->where('booking_id', $reservation->id)->count(),
            "Un journal qui consigne ce qui n'a pas changé devient illisible."
        );
    }
}
