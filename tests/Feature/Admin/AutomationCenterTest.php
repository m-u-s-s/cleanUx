<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\AutomationCenter;
use App\Models\AutomationAction;
use App\Models\AutomationRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * L'ECRAN DE LISTE — /admin/automation cesse d'etre une coquille.
 *
 * La porte est deja posee par le groupe de routes (`role:admin`, `module_gate` avec
 * `manage-automation` dans config/modules.php) : on mesure ce comportement, on ne le double pas.
 */
class AutomationCenterTest extends TestCase
{
    use RefreshDatabase;

    /** Le patron du depot (AgendaHebdomadaireActionsTest), etendu avec la permission du module. */
    private function adminGlobal(): User
    {
        return User::factory()->admin()->create([
            'access_scope' => User::ACCESS_SCOPE_ALL,
            'managed_service_zone_id' => null,
            'is_active' => true,
            'permissions' => ['manage-automation'],
        ]);
    }

    private function regle(array $attributs = []): AutomationRule
    {
        return AutomationRule::create(array_merge([
            'nom' => 'Missions sans intervenant',
            'entite' => 'booking',
            'declencheur' => 'cadence',
            'conditions' => [],
            'actions' => [],
        ], $attributs));
    }

    /** LA COQUILLE TOMBE. Tant qu'aucune classe n'existe, la route sert un gabarit de repli
     *  et l'administrateur clique sur une tuile pour arriver dans le vide. Sert aussi de temoin :
     *  un administrateur qui a la permission atteint bien l'ecran (200), sinon le refus ci-dessous
     *  passerait au vert en mesurant une porte fermee a tout le monde. */
    public function test_la_route_sert_le_composant_et_non_le_gabarit_de_repli(): void
    {
        $this->actingAs($this->adminGlobal())
            ->get(route('admin.automation'))
            ->assertOk()
            ->assertSeeLivewire(AutomationCenter::class);
    }

    /** TEMOIN DE LA PORTE — mesure : `role:admin` refuse un client par `abort(403)` (CheckRole),
     *  avant meme que `module_gate` ne s'execute. Pas une redirection : un 403 net. */
    public function test_un_non_administrateur_n_atteint_pas_l_ecran(): void
    {
        $this->actingAs(User::factory()->client()->create())
            ->get(route('admin.automation'))
            ->assertForbidden();
    }

    /** LA PERMISSION DU MODULE COMPTE AUSSI — un administrateur SANS `manage-automation`
     *  est refuse par `module_gate`, pas par `role:admin`. Sans ce cas, un admin generique
     *  passerait par erreur pour un temoin valide de la porte du module. */
    public function test_un_administrateur_sans_la_permission_du_module_n_atteint_pas_l_ecran(): void
    {
        $sansPermission = User::factory()->admin()->create([
            'access_scope' => User::ACCESS_SCOPE_ALL,
            'managed_service_zone_id' => null,
            'is_active' => true,
            'permissions' => [],
        ]);

        $this->actingAs($sansPermission)
            ->get(route('admin.automation'))
            ->assertForbidden();
    }

    public function test_la_liste_affiche_le_libelle_du_declencheur_pas_sa_cle(): void
    {
        // La cle reelle d'un AlerteMetierDeclencheur est prefixee `alerte.` (AutomationServiceProvider).
        $this->regle([
            'nom' => 'Paiement en echec',
            'entite' => 'alerte',
            'declencheur' => 'alerte.payment_capture_failed',
        ]);

        Livewire::actingAs($this->adminGlobal())
            ->test(AutomationCenter::class)
            ->assertSee("La capture d'un paiement a échoué")
            ->assertDontSee('alerte.payment_capture_failed');
    }

    /** Le compte sur sept jours ignore une ligne posee hors fenetre. */
    public function test_le_compte_sur_sept_jours_ignore_une_ligne_plus_ancienne(): void
    {
        $regle = $this->regle();

        AutomationAction::create([
            'automation_rule_id' => $regle->id,
            'entite_type' => 'booking',
            'entite_id' => 1,
            'action_cle' => 'journaliser',
            'mode' => 'observation',
            'resultat' => AutomationAction::RESULTAT_SIMULEE,
            'pose_le' => now()->subDays(2),
        ]);

        // TEMOIN — cette ligne DANS la fenetre doit etre comptee : sans elle, un compte
        // toujours a zero ferait passer le refus (ligne ancienne exclue) sans rien prouver.
        AutomationAction::create([
            'automation_rule_id' => $regle->id,
            'entite_type' => 'booking',
            'entite_id' => 2,
            'action_cle' => 'journaliser',
            'mode' => 'observation',
            'resultat' => AutomationAction::RESULTAT_SIMULEE,
            'pose_le' => now()->subDays(3),
        ]);

        AutomationAction::create([
            'automation_rule_id' => $regle->id,
            'entite_type' => 'booking',
            'entite_id' => 3,
            'action_cle' => 'journaliser',
            'mode' => 'observation',
            'resultat' => AutomationAction::RESULTAT_SIMULEE,
            'pose_le' => now()->subDays(10),
        ]);

        $regles = Livewire::actingAs($this->adminGlobal())
            ->test(AutomationCenter::class)
            ->viewData('regles');

        $this->assertSame(2, $regles->firstWhere('id', $regle->id)->actions_sept_jours);
    }

    public function test_l_etat_du_moteur_est_affiche_actif(): void
    {
        config()->set('features.automation', true);

        Livewire::actingAs($this->adminGlobal())
            ->test(AutomationCenter::class)
            ->assertSee('activé');
    }

    public function test_l_etat_du_moteur_est_affiche_desactive(): void
    {
        config()->set('features.automation', false);

        Livewire::actingAs($this->adminGlobal())
            ->test(AutomationCenter::class)
            ->assertSee('désactivé');
    }

    public function test_la_liste_est_vide_montre_l_etat_vide(): void
    {
        Livewire::actingAs($this->adminGlobal())
            ->test(AutomationCenter::class)
            ->assertSee('Aucune règle');
    }
}
