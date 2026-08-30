<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\AgendaHebdomadaire;
use App\Models\Booking;
use App\Models\ServiceZone;
use App\Models\User;
use App\Services\Missions\MissionFromRendezVousSyncService;
use App\Support\Domain\BookingStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * AGIR DEPUIS L'AGENDA — la modale d'un rendez-vous et ses boutons.
 *
 * L'agenda montrait la semaine sans donner prise dessus : reperer une mission sans
 * intervenant obligeait a quitter l'ecran. Ce qui se decide ici touche a des donnees
 * reelles — d'ou la portee de zone verifiee A CHAQUE action, et non a l'affichage seul.
 */
class AgendaHebdomadaireActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // La replanification previent le client : on ne poste pas de vraies notifications.
        Notification::fake();
    }

    private function adminGlobal(): User
    {
        return User::factory()->admin()->create([
            'access_scope' => User::ACCESS_SCOPE_ALL,
            'managed_service_zone_id' => null,
            'is_active' => true,
        ]);
    }

    private function adminDeZone(ServiceZone $zone): User
    {
        return User::factory()->admin()->create([
            'access_scope' => 'zone',
            'managed_service_zone_id' => $zone->id,
            'is_active' => true,
        ]);
    }

    private function rdvDeLaSemaine(array $attributs = []): Booking
    {
        return Booking::factory()->create(array_merge([
            'date' => now()->startOfWeek()->addDay()->toDateString(),
            'heure' => '09:00:00',
            'status' => BookingStatus::EN_ATTENTE,
            'priorite' => 'normale',
            'estimated_duration_minutes' => 90,
        ], $attributs));
    }

    private function composant(User $admin)
    {
        return Livewire::actingAs($admin)->test(AgendaHebdomadaire::class, [
            'semaine' => now()->startOfWeek()->toDateString(),
        ]);
    }

    public function test_ouvrir_un_rdv_charge_sa_fiche_et_preremplit_le_formulaire(): void
    {
        $rdv = $this->rdvDeLaSemaine();

        $this->composant($this->adminGlobal())
            ->call('ouvrirRdv', $rdv->id)
            ->assertSet('rdvOuvert', $rdv->id)
            ->assertSet('affectationDate', $rdv->date->format('Y-m-d'))
            ->assertSet('affectationHeure', '09:00')
            ->assertSee($rdv->service_display_name);
    }

    /**
     * LA PORTEE DE ZONE VAUT POUR LES ACTIONS, pas seulement pour l'affichage.
     *
     * L'identifiant vient du navigateur : sans cette porte, un administrateur de zone
     * ouvrait — et replanifiait — la reservation d'une zone voisine.
     */
    public function test_un_admin_de_zone_n_ouvre_pas_un_rdv_d_une_autre_zone(): void
    {
        $saZone = ServiceZone::factory()->create();
        $ailleurs = ServiceZone::factory()->create();

        $rdv = $this->rdvDeLaSemaine(['service_zone_id' => $ailleurs->id]);

        $this->composant($this->adminDeZone($saZone))
            ->call('ouvrirRdv', $rdv->id)
            ->assertSet('rdvOuvert', null);
    }

    /**
     * TEMOIN — le meme administrateur de zone ouvre bien un rendez-vous DE SA ZONE. Sans lui,
     * le refus ci-dessus passerait au vert sur une modale qui ne s'ouvre pour personne.
     */
    public function test_temoin_un_admin_de_zone_ouvre_un_rdv_de_sa_zone(): void
    {
        $saZone = ServiceZone::factory()->create();
        $rdv = $this->rdvDeLaSemaine(['service_zone_id' => $saZone->id]);

        $this->composant($this->adminDeZone($saZone))
            ->call('ouvrirRdv', $rdv->id)
            ->assertSet('rdvOuvert', $rdv->id);
    }

    /**
     * `intervenantId()` LIT D'ABORD LA MISSION. Ecrire la seule colonne `employe_id` aurait
     * laisse l'agenda afficher l'ancien intervenant : c'est la mission qui fait autorite.
     */
    public function test_l_affectation_change_l_intervenant_qui_fait_autorite(): void
    {
        $rdv = $this->rdvDeLaSemaine();
        $nouvel = User::factory()->employe()->create(['is_active' => true]);

        $this->composant($this->adminGlobal())
            ->call('ouvrirRdv', $rdv->id)
            ->set('affectationEmploye', (string) $nouvel->id)
            ->set('affectationDate', $rdv->date->format('Y-m-d'))
            ->set('affectationHeure', '14:00')
            ->call('enregistrerAffectation')
            ->assertHasNoErrors();

        $frais = $rdv->fresh();

        $this->assertSame($nouvel->id, $frais->intervenantId());
        $this->assertSame('14:00', substr((string) $frais->heure, 0, 5));
    }

    /** Deux missions sur le meme intervenant au meme creneau : la seconde est refusee. */
    public function test_un_creneau_en_conflit_est_refuse_et_rien_n_est_ecrit(): void
    {
        $intervenant = User::factory()->employe()->create(['is_active' => true]);
        $jour = now()->startOfWeek()->addDay()->toDateString();

        $occupe = $this->rdvDeLaSemaine([
            'date' => $jour,
            'heure' => '10:00:00',
            'employe_id' => $intervenant->id,
            'status' => BookingStatus::CONFIRME,
        ]);
        // La mission fait autorite pour `intervenantEst` : sans elle, le conflit ne se voit pas.
        app(MissionFromRendezVousSyncService::class)->syncFromRendezVous($occupe->fresh());

        $aPlacer = $this->rdvDeLaSemaine(['date' => $jour, 'heure' => '16:00:00']);

        $this->composant($this->adminGlobal())
            ->call('ouvrirRdv', $aPlacer->id)
            ->set('affectationEmploye', (string) $intervenant->id)
            ->set('affectationDate', $jour)
            ->set('affectationHeure', '10:30')
            ->call('enregistrerAffectation')
            ->assertHasErrors('affectationHeure');

        $this->assertSame('16:00', substr((string) $aPlacer->fresh()->heure, 0, 5));
    }

    /**
     * TEMOIN — le meme enregistrement, sur un creneau libre, passe. Sans lui, le refus
     * ci-dessus resterait vert si `enregistrerAffectation` echouait pour toute autre raison.
     */
    public function test_temoin_hors_conflit_le_meme_enregistrement_passe(): void
    {
        $intervenant = User::factory()->employe()->create(['is_active' => true]);
        $jour = now()->startOfWeek()->addDay()->toDateString();

        $aPlacer = $this->rdvDeLaSemaine(['date' => $jour, 'heure' => '16:00:00']);

        $this->composant($this->adminGlobal())
            ->call('ouvrirRdv', $aPlacer->id)
            ->set('affectationEmploye', (string) $intervenant->id)
            ->set('affectationDate', $jour)
            ->set('affectationHeure', '10:30')
            ->call('enregistrerAffectation')
            ->assertHasNoErrors();

        $this->assertSame('10:30', substr((string) $aPlacer->fresh()->heure, 0, 5));
    }

    /** Le statut vient du navigateur : la liste blanche est la seule garde. */
    public function test_un_statut_hors_liste_blanche_est_refuse(): void
    {
        $rdv = $this->rdvDeLaSemaine();

        $this->composant($this->adminGlobal())
            ->call('ouvrirRdv', $rdv->id)
            ->call('changerStatut', BookingStatus::REFUSE)
            ->assertStatus(422);

        $this->assertSame(BookingStatus::EN_ATTENTE, $rdv->fresh()->status);
    }

    /** TEMOIN — un statut DE la liste passe bien. */
    public function test_temoin_un_statut_de_la_liste_est_applique(): void
    {
        $rdv = $this->rdvDeLaSemaine();

        $this->composant($this->adminGlobal())
            ->call('ouvrirRdv', $rdv->id)
            ->call('changerStatut', BookingStatus::CONFIRME);

        $this->assertSame(BookingStatus::CONFIRME, $rdv->fresh()->status);
    }

    public function test_basculer_l_urgence_va_et_revient(): void
    {
        $rdv = $this->rdvDeLaSemaine();

        $composant = $this->composant($this->adminGlobal())->call('ouvrirRdv', $rdv->id);

        $composant->call('basculerUrgence');
        $this->assertSame('urgente', $rdv->fresh()->priorite);

        $composant->call('basculerUrgence');
        $this->assertSame('normale', $rdv->fresh()->priorite);
    }

    /**
     * LES COMPTEURS DE LA PAGE VIVENT CHEZ LE PARENT. Sans cet evenement, assigner un
     * intervenant laissait « 3 sans employé » affiché juste au-dessus de l'agenda.
     */
    public function test_une_action_previent_le_parent(): void
    {
        $rdv = $this->rdvDeLaSemaine();

        $this->composant($this->adminGlobal())
            ->call('ouvrirRdv', $rdv->id)
            ->call('changerStatut', BookingStatus::CONFIRME)
            ->assertDispatched('planning-mis-a-jour');
    }
}
