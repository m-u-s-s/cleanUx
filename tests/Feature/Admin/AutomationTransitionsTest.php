<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\AutomationCenter;
use App\Models\AutomationRule;
use App\Models\Booking;
use App\Models\User;
use App\Services\Automation\EtatDeRegle;
use App\Services\Automation\RuleRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Feature\Automation\ArmeSesRegles;
use Tests\TestCase;

/**
 * LES TRANSITIONS DEPUIS LA LISTE — un administrateur observe, arme, suspend, desactive.
 *
 * `EtatDeRegle::armer()` refuse une regle au journal vide : c'est la garde fondatrice du
 * moteur, et l'ecran doit la montrer, pas planter ni l'avaler.
 */
class AutomationTransitionsTest extends TestCase
{
    use ArmeSesRegles;
    use RefreshDatabase;

    private function adminGlobal(): User
    {
        return User::factory()->admin()->create([
            'access_scope' => User::ACCESS_SCOPE_ALL,
            'managed_service_zone_id' => null,
            'is_active' => true,
            'permissions' => ['manage-automation'],
        ]);
    }

    /** Meme forme que MachineAEtatsTest::regle() : une entite, une condition, une action. */
    private function regle(array $attributs = []): AutomationRule
    {
        return AutomationRule::create(array_merge([
            'nom' => 'Missions sans intervenant',
            'entite' => 'booking',
            'declencheur' => 'cadence',
            'cadence' => 'quart_heure',
            'conditions' => ['field' => 'statut', 'op' => 'eq', 'value' => 'en_attente'],
            'actions' => [['cle' => 'journaliser', 'parametres' => ['message' => 'vue']]],
        ], $attributs));
    }

    /**
     * LE POINT QUI COMPTE — armer une regle sans observation echoue, et le message se voit.
     * Sans le try/catch dans le composant, ArmementRefuse remonterait en 500.
     */
    public function test_armer_une_regle_sans_observation_echoue_et_affiche_le_motif(): void
    {
        $regle = $this->regle();

        $composant = Livewire::actingAs($this->adminGlobal())
            ->test(AutomationCenter::class)
            ->call('cibler', $regle->id)
            ->call('armer');

        $composant->assertSee("Cette règle n'a rien observé");

        $this->assertSame(AutomationRule::ETAT_BROUILLON, $regle->fresh()->etat);
    }

    /**
     * TEMOIN — la meme regle, apres un vrai passage d'observation (via RuleRunner, le chemin
     * reel), s'arme depuis l'ecran. Sans lui, le refus ci-dessus passerait au vert sur un
     * armement casse pour tout le monde, pas seulement pour une regle qui n'a rien observe.
     */
    public function test_temoin_apres_un_passage_d_observation_la_meme_regle_s_arme(): void
    {
        Booking::factory()->create(['status' => 'en_attente']);
        $regle = $this->regle();

        app(EtatDeRegle::class)->observer($regle);
        app(RuleRunner::class)->executer($regle->fresh());

        $composant = Livewire::actingAs($this->adminGlobal())
            ->test(AutomationCenter::class)
            ->call('cibler', $regle->fresh()->id)
            ->call('armer');

        $composant->assertDontSee("Cette règle n'a rien observé");

        $this->assertSame(AutomationRule::ETAT_ARMEE, $regle->fresh()->etat);
    }

    public function test_observer_mene_a_l_etat_observation(): void
    {
        $regle = $this->regle();

        Livewire::actingAs($this->adminGlobal())
            ->test(AutomationCenter::class)
            ->call('cibler', $regle->id)
            ->call('observer');

        $this->assertSame(AutomationRule::ETAT_OBSERVATION, $regle->fresh()->etat);
    }

    public function test_suspendre_avec_un_motif_mene_a_l_etat_suspendue(): void
    {
        $regle = $this->regle();

        Livewire::actingAs($this->adminGlobal())
            ->test(AutomationCenter::class)
            ->call('cibler', $regle->id)
            ->set('motifSuspension', 'Emballement detecte sur le passage precedent')
            ->call('suspendre')
            ->assertHasNoErrors();

        $this->assertSame(AutomationRule::ETAT_SUSPENDUE, $regle->fresh()->etat);
    }

    /** Un motif vide ne suspend rien : la validation le refuse avant EtatDeRegle. */
    public function test_suspendre_sans_motif_est_refuse_et_ne_change_pas_l_etat(): void
    {
        $regle = $this->regle(['etat' => AutomationRule::ETAT_ARMEE]);

        Livewire::actingAs($this->adminGlobal())
            ->test(AutomationCenter::class)
            ->call('cibler', $regle->id)
            ->set('motifSuspension', '')
            ->call('suspendre')
            ->assertHasErrors('motifSuspension');

        $this->assertSame(AutomationRule::ETAT_ARMEE, $regle->fresh()->etat);
    }

    public function test_desactiver_mene_a_l_etat_desactivee(): void
    {
        $regle = $this->regle();

        Livewire::actingAs($this->adminGlobal())
            ->test(AutomationCenter::class)
            ->call('cibler', $regle->id)
            ->call('desactiver');

        $this->assertSame(AutomationRule::ETAT_DESACTIVEE, $regle->fresh()->etat);
    }

    /**
     * CHAQUE TRANSITION ECRIT SA LIGNE DE JOURNAL — c'est EtatDeRegle::poser() qui s'en
     * charge ; ce test protege contre un appel direct au modele qui la sauterait.
     */
    public function test_chaque_transition_ecrit_une_ligne_de_journal(): void
    {
        Booking::factory()->create(['status' => 'en_attente']);
        $regle = $this->regle();
        $admin = $this->adminGlobal();

        Livewire::actingAs($admin)->test(AutomationCenter::class)
            ->call('cibler', $regle->id)
            ->call('observer');

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'automation.regle_observation',
            'target_id' => $regle->id,
        ]);

        app(RuleRunner::class)->executer($regle->fresh());

        Livewire::actingAs($admin)->test(AutomationCenter::class)
            ->call('cibler', $regle->fresh()->id)
            ->call('armer');

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'automation.regle_armee',
            'target_id' => $regle->id,
        ]);

        Livewire::actingAs($admin)->test(AutomationCenter::class)
            ->call('cibler', $regle->fresh()->id)
            ->set('motifSuspension', 'Test de journalisation')
            ->call('suspendre');

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'automation.regle_suspendue',
            'target_id' => $regle->id,
        ]);

        Livewire::actingAs($admin)->test(AutomationCenter::class)
            ->call('cibler', $regle->fresh()->id)
            ->call('desactiver');

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'automation.regle_desactivee',
            'target_id' => $regle->id,
        ]);
    }

    /**
     * UN NON-ADMINISTRATEUR NE DECLENCHE AUCUNE TRANSITION — la porte est entierement portee
     * par les routes (`role:admin`, `module_gate`) : sans une reponse HTTP reussie a la page,
     * le navigateur ne recoit jamais l'empreinte Livewire necessaire pour appeler `armer`,
     * `observer`, `suspendre` ou `desactiver`. On mesure ce comportement au niveau HTTP,
     * pas au niveau du composant : un test Livewire::test() ne passe jamais par les routes.
     */
    public function test_un_non_administrateur_n_atteint_jamais_les_boutons_de_transition(): void
    {
        $this->actingAs(User::factory()->client()->create())
            ->get(route('admin.automation'))
            ->assertForbidden();
    }

    /**
     * MESURE — Livewire::test() ne rejoue PAS le middleware de route (`role:admin`,
     * `module_gate`) : on l'a verifie en desactivant temporairement EnforcesAdminAccess, un
     * client appelait alors `cibler` puis `observer` avec succes. C'est `EnforcesAdminAccess`
     * (deja obligatoire sur les 105 autres composants admin, AdminComponentGuardTest en
     * temoigne) qui ferme cette porte-la, pas les routes.
     */
    public function test_un_non_administrateur_est_bloque_au_niveau_du_composant(): void
    {
        $this->actingAs(User::factory()->client()->create());

        Livewire::test(AutomationCenter::class)->assertForbidden();
    }

    /**
     * LA GARDE `#[Locked]` — sans elle, le navigateur retourne `regleCiblee` par `$set` et
     * agit sur une autre regle que celle affichee dans le panneau ouvert.
     */
    public function test_la_propriete_regle_ciblee_est_verrouillee(): void
    {
        $regle = $this->regle();

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($this->adminGlobal())
            ->test(AutomationCenter::class)
            ->set('regleCiblee', $regle->id);
    }
}
