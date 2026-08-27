<?php

namespace Tests\Feature\Employe;

use App\Livewire\Employe\MesRendezVous;
use App\Models\Booking;
use App\Models\Mission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class MesRendezVousComponentTest extends TestCase
{
    use RefreshDatabase;

    /** An admin (non read-only) passes the RendezVous `update` policy, while the component's listing query filters on `employe_id = Auth::id()`. */
    private function actingAdmin(): User
    {
        $admin = User::factory()->admin()->create(['is_active' => true]);
        $this->actingAs($admin);

        return $admin;
    }

    /** Create a booking with an attached Mission so that selecting it resolves a non-null selectedMissionId (the component resolves the mission via rendez_vous_id when a booking is selected). */
    private function bookingWithMission(User $employe, array $attributes = []): Booking
    {
        $booking = Booking::factory()->create(array_merge(['employe_id' => $employe->id], $attributes));
        Mission::factory()->create(['booking_id' => $booking->id]);

        return $booking;
    }

    public function test_employe_can_render_listing_with_seeded_rendez_vous(): void
    {
        $employe = User::factory()->employe()->create();
        $booking = Booking::factory()->create(['employe_id' => $employe->id]);

        $this->actingAs($employe);

        Livewire::test(MesRendezVous::class)
            ->assertOk()
            ->assertSee($booking->client->name);
    }

    public function test_listing_excludes_bookings_assigned_to_other_employes(): void
    {
        $admin = $this->actingAdmin();
        $mine = Booking::factory()->create(['employe_id' => $admin->id]);
        $other = Booking::factory()->create();

        Livewire::test(MesRendezVous::class)
            ->assertOk()
            ->assertSee($mine->client->name)
            ->assertDontSee($other->client->name);
    }

    public function test_filters_search_tri_and_priorite_reset_pagination(): void
    {
        $admin = $this->actingAdmin();
        Booking::factory()->create([
            'employe_id' => $admin->id,
            'status' => 'confirme',
            'priorite' => 'haute',
        ]);

        Livewire::test(MesRendezVous::class)
            ->set('filtreStatus', 'confirme')
            ->assertSet('filtreStatus', 'confirme')
            ->set('priorite', 'haute')
            ->set('tri', 'desc')
            ->set('search', 'nettoyage')
            ->assertOk()
            ->assertHasNoErrors();
    }

    public function test_select_and_clear_rendez_vous_drive_computed_properties(): void
    {
        $admin = $this->actingAdmin();
        $booking = $this->bookingWithMission($admin);

        Livewire::test(MesRendezVous::class)
            ->call('selectRdv', $booking->id)
            ->assertSet('selectedRdvId', $booking->id)
            ->call('clearSelectedRdv')
            ->assertSet('selectedRdvId', null)
            ->assertSet('selectedMissionId', null);
    }

    public function test_mettre_a_jour_statut_transitions_and_timestamps(): void
    {
        Notification::fake();
        $admin = $this->actingAdmin();
        $booking = $this->bookingWithMission($admin, ['status' => 'confirme']);

        Livewire::test(MesRendezVous::class)
            ->call('mettreAJourStatut', $booking->id, 'en_route')
            ->assertDispatched('toast')
            ->call('mettreAJourStatut', $booking->id, 'sur_place')
            ->call('mettreAJourStatut', $booking->id, 'refuse')
            ->assertSet('selectedRdvId', $booking->id);

        $booking->refresh();
        $this->assertSame('refuse', $booking->status);
        $this->assertNotNull($booking->mission_started_at);
        $this->assertNotNull($booking->mission_arrived_at);
    }

    public function test_mettre_a_jour_statut_rejects_unknown_status(): void
    {
        $admin = $this->actingAdmin();
        $booking = Booking::factory()->create(['employe_id' => $admin->id]);

        Livewire::test(MesRendezVous::class)
            ->call('mettreAJourStatut', $booking->id, 'pas_un_statut')
            ->assertStatus(403);
    }

    /**
     * Le refus passait par `refuserRdv`, appele par un ecouteur `refuser-rdv` que RIEN n'emettait
     * et par ce test seul. Le bouton de la vue, lui, a toujours appele `mettreAJourStatut`
     * directement : c'est ce chemin-la, le vrai, qui se mesure ici.
     */
    public function test_refuser_un_rendez_vous_le_marque_refuse(): void
    {
        Notification::fake();
        $admin = $this->actingAdmin();
        $booking = $this->bookingWithMission($admin, ['status' => 'confirme']);

        Livewire::test(MesRendezVous::class)
            ->call('mettreAJourStatut', $booking->id, 'refuse')
            ->assertDispatched('toast');

        $this->assertSame('refuse', $booking->fresh()->status);
    }

    /** Et la porte qui y mene : sans bouton, la capacite redeviendrait injoignable. */
    public function test_un_bouton_de_la_vue_mene_bien_au_refus(): void
    {
        $source = file_get_contents(resource_path('views/livewire/employe/mes-rendez-vous.blade.php'));

        $this->assertStringContainsString("mettreAJourStatut({{ \$rdv->id }}, 'refuse')", $source);
    }

    public function test_check_in_modal_opens_and_prefills_default_checklist(): void
    {
        $admin = $this->actingAdmin();
        $booking = $this->bookingWithMission($admin, ['status' => 'confirme']);

        Livewire::test(MesRendezVous::class)
            ->call('ouvrirCheckInMission', $booking->id)
            ->assertSet('showCheckInModal', true)
            ->assertSet('checkInRdvId', $booking->id)
            ->assertSet('selectedRdvId', $booking->id)
            ->assertSet('terrain_checklist.acces_ok', false)
            ->assertSet('terrain_checklist.client_present', false);
    }

    public function test_check_in_can_be_closed_without_saving(): void
    {
        $admin = $this->actingAdmin();
        $booking = $this->bookingWithMission($admin);

        Livewire::test(MesRendezVous::class)
            ->call('ouvrirCheckInMission', $booking->id)
            ->assertSet('showCheckInModal', true)
            ->call('fermerCheckInMission')
            ->assertSet('showCheckInModal', false)
            ->assertSet('checkInRdvId', null);
    }

    public function test_rapport_fin_mission_opens_and_prefills_duree(): void
    {
        $admin = $this->actingAdmin();
        $booking = $this->bookingWithMission($admin, [
            'status' => 'sur_place',
            'estimated_duration_minutes' => 90,
        ]);

        Livewire::test(MesRendezVous::class)
            ->call('ouvrirRapportFinMission', $booking->id)
            ->assertSet('showRapportModal', true)
            ->assertSet('rapportRdvId', $booking->id)
            ->assertSet('selectedRdvId', $booking->id)
            ->assertSet('duree_reelle', 90);
    }

    public function test_rapport_fin_mission_requires_valid_duree(): void
    {
        $admin = $this->actingAdmin();
        $booking = $this->bookingWithMission($admin, ['status' => 'sur_place']);

        Livewire::test(MesRendezVous::class)
            ->call('ouvrirRapportFinMission', $booking->id)
            ->set('duree_reelle', 5)
            ->call('sauverRapportFinMission')
            ->assertHasErrors('duree_reelle');
    }

    public function test_rapport_modal_can_be_closed(): void
    {
        $admin = $this->actingAdmin();
        $booking = $this->bookingWithMission($admin);

        Livewire::test(MesRendezVous::class)
            ->call('ouvrirRapportFinMission', $booking->id)
            ->assertSet('showRapportModal', true)
            ->call('fermerRapportFinMission')
            ->assertSet('showRapportModal', false)
            ->assertSet('rapportRdvId', null);
    }

    /**
     * TEMOIN POSITIF du refus ci-dessus.
     *
     * `test_rapport_fin_mission_requires_valid_duree` etait le SEUL appel a la sauvegarde, et sa
     * duree invalide arretait la validation avant le `save()`. Le chemin nominal n'etait donc
     * exerce nulle part — il levait `no such column: commentaire_fin_mission` depuis le 2026-05-05.
     */
    public function test_le_rapport_de_fin_de_mission_est_reellement_enregistre(): void
    {
        $admin = $this->actingAdmin();
        $booking = $this->bookingWithMission($admin, [
            'status' => 'sur_place',
            'estimated_duration_minutes' => 90,
        ]);

        Livewire::test(MesRendezVous::class)
            ->call('ouvrirRapportFinMission', $booking->id)
            ->set('duree_reelle', 105)
            ->set('commentaire_fin_mission', 'Sols laves, vitres faites.')
            ->set('incident_terrain', 'Robinet de la cuisine qui goutte.')
            ->call('sauverRapportFinMission')
            ->assertHasNoErrors();

        $frais = $booking->fresh();

        $this->assertSame('termine', $frais->status);
        $this->assertSame(105, $frais->duree_reelle);
        $this->assertNotNull($frais->mission_finished_at);
        $this->assertSame('Sols laves, vitres faites.', $frais->commentaire_fin_mission);
        $this->assertSame('Robinet de la cuisine qui goutte.', $frais->incident_terrain);
    }
}
