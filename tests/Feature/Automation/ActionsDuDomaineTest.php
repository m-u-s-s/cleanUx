<?php

namespace Tests\Feature\Automation;

use App\Enums\ProviderType;
use App\Livewire\Admin\Automation\ReglagesDActionsEcran;
use App\Models\AutomationAction;
use App\Models\AutomationRule;
use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\ProviderProfile;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\User;
use App\Notifications\MissionCheckInPingNotification;
use App\Services\Automation\Actions\EnvoyerLePingAuClient;
use App\Services\Automation\Actions\ImposerDOffice;
use App\Services\Automation\Actions\RelancerLaRecherche;
use App\Services\Automation\ArmementRefuse;
use App\Services\Automation\Catalogue;
use App\Services\Automation\EtatDeRegle;
use App\Services\Automation\ReglagesDActions;
use App\Services\Automation\RuleRunner;
use App\Support\Domain\BookingStatus;
use App\Support\Domain\MissionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Feature\Dispatch\Concerns\OuvreLeCatalogue;
use Tests\TestCase;

/** Les deux premieres actions qui ecrivent dans le domaine — chacune par un service existant. */
class ActionsDuDomaineTest extends TestCase
{
    use ArmeSesRegles;
    use OuvreLeCatalogue;
    use RefreshDatabase;
    use RendSesActionsAutonomes;

    private const LAT = 50.8467;

    private const LNG = 4.3525;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Queue::fake();
    }

    // ─── Fabriques ───────────────────────────────────────────────────────────────────────────

    /**
     * Une mission en cours, son client joignable : le terrain du ping. L'etat est celui que le
     * domaine produit vraiment — `setArrived()` porte la reservation a `sur_place`, pas ailleurs.
     */
    private function missionEnCours(): Mission
    {
        $client = User::factory()->create();
        $prestataire = User::factory()->employe()->create(['is_active' => true, 'status' => 'active']);

        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'customer_user_id' => $client->id,
            'employe_id' => $prestataire->id,
            'status' => BookingStatus::SUR_PLACE,
        ]);

        // `sur_place` fait NAITRE la mission (RendezVousObserver) : la retrouver, jamais en creer
        // une seconde — la regle en verrait deux pour la meme reservation.
        $mission = Mission::query()->where('booking_id', $booking->id)->firstOrFail();

        $mission->forceFill([
            'status' => MissionStatus::ARRIVED,
            'lead_employee_id' => $prestataire->id,
            'lead_provider_user_id' => $prestataire->id,
        ])->save();

        // La synchronisation a DEJA pose la ligne du lead : on l'avance, on n'en cree pas une
        // seconde — la table porte un index unique (mission, prestataire).
        MissionAssignment::query()->updateOrCreate(
            ['mission_id' => $mission->id, 'user_id' => $prestataire->id],
            [
                'role_on_mission' => 'lead',
                'assignment_status' => 'arrived',
                'assigned_at' => now()->subHour(),
                'accepted_at' => now()->subHour(),
                'arrived_at' => now()->subMinutes(5),
            ],
        );

        return $mission->fresh();
    }

    /** Une mission planifiee que personne ne tient : le terrain de la relance. */
    private function missionSansIntervenant(bool $avecCandidat = true): Mission
    {
        $zone = ServiceZone::create([
            'name' => 'Zone automatisation', 'slug' => 'zone-automatisation', 'code' => 'AUT',
            'status' => 'active', 'is_bookable' => true, 'is_visible' => true,
            'priority' => 10, 'coverage_type' => 'city_cluster',
        ]);

        $trade = Trade::create([
            'slug' => 'plomberie-automatisation', 'code' => 'PLB-A', 'name' => 'Plomberie',
            'is_active' => true, 'sort_order' => 1, 'allows_asap' => true,
        ]);

        $this->ouvrirAuCatalogue($trade, $zone);

        if ($avecCandidat) {
            $prestataire = User::factory()->create([
                'role' => User::ROLE_EMPLOYE,
                'is_active' => true,
                'primary_service_zone_id' => $zone->id,
            ]);

            ProviderProfile::create([
                'user_id' => $prestataire->id,
                'provider_type' => ProviderType::INDEPENDENT->value,
                'status' => 'active',
                'verification_status' => 'verified',
                'current_lat' => self::LAT,
                'current_lng' => self::LNG,
            ]);

            $prestataire->trades()->syncWithoutDetaching([$trade->id]);
        }

        $booking = Booking::factory()->create([
            'employe_id' => null,
            'assigned_employee_id' => null,
            'service_zone_id' => $zone->id,
            'trade_id' => $trade->id,
            'destination_lat' => self::LAT,
            'destination_lng' => self::LNG,
            'status' => 'confirme',
        ]);

        // `confirme` fait NAITRE la mission (RendezVousObserver) : en creer une seconde donnerait
        // deux missions pour la meme reservation, et la regle en verrait deux.
        $mission = Mission::query()->where('booking_id', $booking->id)->firstOrFail();

        $mission->forceFill([
            'status' => MissionStatus::PLANNED,
            'lead_employee_id' => null,
            'lead_provider_user_id' => null,
        ])->save();

        return $mission->fresh();
    }

    /** @param array<string, mixed> $attributs */
    private function regleSurLaMission(Mission $mission, string $actionCle, array $attributs = []): AutomationRule
    {
        return AutomationRule::create(array_merge([
            'nom' => 'Règle du domaine',
            'entite' => 'mission',
            'declencheur' => 'cadence',
            'cadence' => 'quart_heure',
            // Sur la reservation, pas sur le statut : une mission etrangere ne doit pas entrer.
            'conditions' => ['field' => 'reservation_id', 'op' => 'eq', 'value' => (int) $mission->booking_id],
            'actions' => [['cle' => $actionCle, 'parametres' => []]],
            'etat' => AutomationRule::ETAT_ARMEE,
        ], $attributs));
    }

    private function compter(string $resultat): int
    {
        return AutomationAction::query()->where('mode', 'armee')->where('resultat', $resultat)->count();
    }

    // ─── Le ping au client ───────────────────────────────────────────────────────────────────

    public function test_le_ping_appelle_le_service_et_marque_la_reservation(): void
    {
        $mission = $this->missionEnCours();

        $resultat = app(EnvoyerLePingAuClient::class)->executer($mission, []);

        $this->assertTrue($resultat->reussie);
        $this->assertNotNull($mission->booking->fresh()->checkin_ping_sent_at);
        Notification::assertSentTo($mission->booking->client, MissionCheckInPingNotification::class);
    }

    /** Le service refuse un second ping : l'action ECHOUE, elle ne fait pas semblant. */
    public function test_le_ping_echoue_quand_le_service_refuse(): void
    {
        $mission = $this->missionEnCours();
        $mission->booking->forceFill(['checkin_ping_sent_at' => now()->subHour()])->save();
        $envoye = $mission->booking->fresh()->checkin_ping_sent_at;

        $resultat = app(EnvoyerLePingAuClient::class)->executer($mission, []);

        $this->assertFalse($resultat->reussie);
        $this->assertNotNull($resultat->message);
        $this->assertEquals($envoye, $mission->booking->fresh()->checkin_ping_sent_at);
        Notification::assertNothingSent();
    }

    // ─── La relance de la recherche ──────────────────────────────────────────────────────────

    public function test_la_relance_appelle_le_moteur_et_pose_une_offre(): void
    {
        $mission = $this->missionSansIntervenant();

        $resultat = app(RelancerLaRecherche::class)->executer($mission, []);

        $this->assertTrue($resultat->reussie);
        $this->assertStringContainsString('Offre', (string) $resultat->message);
        $this->assertDatabaseHas('mission_assignments', [
            'mission_id' => $mission->id,
            'assignment_status' => 'assigned',
        ]);
    }

    /**
     * LA RELANCE NE CONTRAINT JAMAIS. Zone mince, profondeur 1 : le seul candidat a laisse filer
     * son offre. Le moteur imposerait d'office ; l'action refuse cette porte et le dit.
     */
    public function test_la_relance_n_impose_jamais_une_mission(): void
    {
        $mission = $this->missionSansIntervenant();

        $offerte = app(RelancerLaRecherche::class)->executer($mission, []);

        $this->assertTrue($offerte->reussie);
        $this->assertStringContainsString('Offre', (string) $offerte->message);

        // L'offre expire sans reponse : le seul candidat de la zone devient « deja sollicite ».
        MissionAssignment::query()
            ->where('mission_id', $mission->id)
            ->update(['expires_at' => now()->subMinute()]);

        $epuisee = app(RelancerLaRecherche::class)->executer($mission->fresh(), []);

        $this->assertFalse($epuisee->reussie);
        $this->assertStringNotContainsString('imposée', (string) $epuisee->message);

        // RIEN N'A ETE CONTRAINT : ni assignation acceptee, ni mission pourvue.
        $this->assertDatabaseMissing('mission_assignments', [
            'mission_id' => $mission->id,
            'assignment_status' => 'accepted',
        ]);
        $this->assertDatabaseHas('missions', [
            'id' => $mission->id,
            'status' => 'planned',
            'lead_provider_user_id' => null,
        ]);
    }

    /**
     * TEMOIN — le MEME etat epuise, confie a l'action qui assume la contrainte, impose bien.
     * Sans lui, le refus ci-dessus passerait au vert en mesurant une zone vide.
     */
    public function test_temoin_le_meme_etat_epuise_est_bien_impose_par_l_action_dediee(): void
    {
        $mission = $this->missionSansIntervenant();

        app(RelancerLaRecherche::class)->executer($mission, []);

        MissionAssignment::query()
            ->where('mission_id', $mission->id)
            ->update(['expires_at' => now()->subMinute()]);

        $imposee = app(ImposerDOffice::class)->executer($mission->fresh(), []);

        $this->assertTrue($imposee->reussie);
        $this->assertStringContainsString('imposée', (string) $imposee->message);
        $this->assertDatabaseHas('mission_assignments', [
            'mission_id' => $mission->id,
            'assignment_status' => 'accepted',
        ]);
        $this->assertDatabaseHas('missions', ['id' => $mission->id, 'status' => 'assigned']);
    }

    /** Une mission deja pourvue ne se reprend pas : la contrainte ne vole personne. */
    public function test_l_imposition_refuse_une_mission_deja_pourvue(): void
    {
        $mission = $this->missionSansIntervenant();

        app(RelancerLaRecherche::class)->executer($mission, []);
        MissionAssignment::query()
            ->where('mission_id', $mission->id)
            ->update(['expires_at' => now()->subMinute()]);

        $premier = app(ImposerDOffice::class)->executer($mission->fresh(), []);
        $this->assertTrue($premier->reussie);

        $titulaire = (int) $mission->fresh()->lead_provider_user_id;
        $second = app(ImposerDOffice::class)->executer($mission->fresh(), []);

        $this->assertFalse($second->reussie);
        $this->assertSame($titulaire, (int) $mission->fresh()->lead_provider_user_id);
    }

    /** Aucun candidat : le moteur rend `null`, et l'action le rapporte en echec. */
    public function test_la_relance_echoue_quand_le_moteur_ne_trouve_personne(): void
    {
        $mission = $this->missionSansIntervenant(avecCandidat: false);

        $resultat = app(RelancerLaRecherche::class)->executer($mission, []);

        $this->assertFalse($resultat->reussie);
        $this->assertNotNull($resultat->message);
        $this->assertDatabaseCount('mission_assignments', 0);
    }

    // ─── `toucheAuDomaine()`, et ce qu'il change vraiment ────────────────────────────────────

    /**
     * LES DEUX DECLARENT ECRIRE DANS LE DOMAINE — et l'ecran des reglages en tire la seule
     * consequence qui compte : les rendre autonomes passe par une confirmation renforcee.
     */
    public function test_les_deux_touchent_au_domaine_et_leur_autonomie_exige_une_confirmation(): void
    {
        $this->assertTrue(app(EnvoyerLePingAuClient::class)->toucheAuDomaine());
        $this->assertTrue(app(RelancerLaRecherche::class)->toucheAuDomaine());

        $admin = User::factory()->admin()->create([
            'access_scope' => User::ACCESS_SCOPE_ALL,
            'managed_service_zone_id' => null,
            'is_active' => true,
            'permissions' => ['manage-automation'],
        ]);

        foreach (['mission.ping_client', 'mission.relancer_la_recherche'] as $cle) {
            Livewire::actingAs($admin)
                ->test(ReglagesDActionsEcran::class)
                ->call('basculer', $cle, true)
                ->assertSet('actionEnConfirmation', $cle);

            $this->assertFalse(app(ReglagesDActions::class)->estAutonome($cle), $cle);
        }

        // TEMOIN — une action qui ne touche pas au domaine bascule sans confirmation.
        Livewire::actingAs($admin)
            ->test(ReglagesDActionsEcran::class)
            ->call('basculer', 'journaliser', true)
            ->assertSet('actionEnConfirmation', null);

        $this->assertTrue(app(ReglagesDActions::class)->estAutonome('journaliser'));
    }

    // ─── LE TEST QUI COMPTE : armee sans reglage, le domaine ne bouge pas ────────────────────

    public function test_le_ping_pose_par_une_regle_armee_propose_et_ne_touche_a_rien(): void
    {
        $mission = $this->missionEnCours();

        $regle = $this->armer($this->regleSurLaMission($mission, 'mission.ping_client'));
        app(RuleRunner::class)->executer($regle);

        $this->assertSame(1, $this->compter(AutomationAction::RESULTAT_PROPOSEE));
        $this->assertSame(0, $this->compter(AutomationAction::RESULTAT_EXECUTEE));
        // L'EFFET EN BASE, pas un espion : la reservation n'a pas ete marquee.
        $this->assertNull($mission->booking->fresh()->checkin_ping_sent_at);
        Notification::assertNothingSent();
    }

    /** TEMOIN — la meme regle, l'action rendue autonome, marque bien la reservation. */
    public function test_temoin_le_ping_rendu_autonome_marque_la_reservation(): void
    {
        $mission = $this->missionEnCours();
        $this->rendreAutonome('mission.ping_client');

        $regle = $this->armer($this->regleSurLaMission($mission, 'mission.ping_client'));
        app(RuleRunner::class)->executer($regle);

        $this->assertSame(1, $this->compter(AutomationAction::RESULTAT_EXECUTEE));
        $this->assertNotNull($mission->booking->fresh()->checkin_ping_sent_at);
        Notification::assertSentTo($mission->booking->client, MissionCheckInPingNotification::class);
    }

    public function test_la_relance_posee_par_une_regle_armee_propose_et_ne_touche_a_rien(): void
    {
        $mission = $this->missionSansIntervenant();

        $regle = $this->armer($this->regleSurLaMission($mission, 'mission.relancer_la_recherche'));
        app(RuleRunner::class)->executer($regle);

        $this->assertSame(1, $this->compter(AutomationAction::RESULTAT_PROPOSEE));
        $this->assertSame(0, $this->compter(AutomationAction::RESULTAT_EXECUTEE));
        // L'EFFET EN BASE : aucune offre n'a ete emise.
        $this->assertDatabaseCount('mission_assignments', 0);
    }

    /** TEMOIN — la meme regle, l'action rendue autonome, emet bien une offre. */
    public function test_temoin_la_relance_rendue_autonome_emet_une_offre(): void
    {
        $mission = $this->missionSansIntervenant();
        $this->rendreAutonome('mission.relancer_la_recherche');

        $regle = $this->armer($this->regleSurLaMission($mission, 'mission.relancer_la_recherche'));
        app(RuleRunner::class)->executer($regle);

        $this->assertSame(1, $this->compter(AutomationAction::RESULTAT_EXECUTEE));
        $this->assertDatabaseHas('mission_assignments', [
            'mission_id' => $mission->id,
            'assignment_status' => 'assigned',
        ]);
    }

    // ─── Les gardes de contrat ───────────────────────────────────────────────────────────────

    /**
     * `entitesSupportees()` NE SERT PAS QU'A FILTRER LE FORMULAIRE : elargie, une regle sur la
     * reservation appellerait vraiment l'action, avec pour seule protection le retrecissement.
     */
    public function test_ces_actions_ne_sont_pas_proposees_pour_une_reservation(): void
    {
        $offertes = array_keys(app(Catalogue::class)->actions('booking'));

        $this->assertNotContains('mission.ping_client', $offertes);
        $this->assertNotContains('mission.relancer_la_recherche', $offertes);
        // TEMOIN — le filtre rend bien quelque chose : sans lui, tout serait « absent ».
        $this->assertContains('journaliser', $offertes);
        $this->assertContains('mission.ping_client', array_keys(app(Catalogue::class)->actions('mission')));
    }

    /** Cablee malgre tout sur la reservation (une regle importee en JSON), elle ne s'arme jamais. */
    public function test_posee_sur_une_reservation_l_action_ne_peut_meme_pas_etre_armee(): void
    {
        $mission = $this->missionEnCours();
        $this->rendreAutonome('mission.ping_client');

        $regle = AutomationRule::create([
            'nom' => 'Règle mal câblée',
            'entite' => 'booking',
            'declencheur' => 'cadence',
            'cadence' => 'quart_heure',
            'conditions' => ['field' => 'statut', 'op' => 'eq', 'value' => $mission->booking->status],
            'actions' => [['cle' => 'mission.ping_client', 'parametres' => []]],
        ]);

        app(EtatDeRegle::class)->observer($regle);
        app(RuleRunner::class)->executer($regle->fresh());

        // L'OBSERVATION N'A RIEN SIMULE : la garde d'entite ecrit son echec AVANT.
        $this->assertSame(0, AutomationAction::query()->where('resultat', AutomationAction::RESULTAT_SIMULEE)->count());
        $this->assertSame(1, AutomationAction::query()->where('resultat', AutomationAction::RESULTAT_ECHOUEE)->count());

        try {
            app(EtatDeRegle::class)->armer($regle->fresh());
            $this->fail("Une règle qui n'a rien simulé a pourtant été armée.");
        } catch (ArmementRefuse) {
            // Attendu : sans journal d'observation, rien ne s'arme.
        }

        $this->assertNull($mission->booking->fresh()->checkin_ping_sent_at);
        Notification::assertNothingSent();
    }

    /**
     * LE CONTRAT DIT `Model`, PAS `Mission`. Sans le retrecissement des deux actions, ce test
     * leve un TypeError au lieu de rendre un echec — et la file, elle, ne voit qu'un `Throwable`.
     */
    public function test_une_entite_qui_n_est_pas_une_mission_donne_un_echec_propre(): void
    {
        $reservation = Booking::factory()->create();

        foreach ([EnvoyerLePingAuClient::class, RelancerLaRecherche::class] as $classe) {
            $resultat = app($classe)->executer($reservation, []);

            $this->assertFalse($resultat->reussie, $classe);
            $this->assertNotNull($resultat->message, $classe);
        }
    }
}
