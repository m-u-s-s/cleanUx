<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Automation\ConstructeurDeRegle;
use App\Livewire\Admin\Automation\JournalDeRegle;
use App\Livewire\Admin\AutomationCenter;
use App\Models\AutomationRule;
use App\Models\User;
use App\Services\Automation\Catalogue;
use App\Services\FeatureFlag\FeatureFlagService;
use Database\Seeders\ReglesDAlerteMetierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** /ADMIN/AUTOMATION CESSE D'ÊTRE UNE COQUILLE — les cinq règles s'y lisent, s'ouvrent, et l'interrupteur reste fermé. */
class LesCinqReglesSontVisiblesTest extends TestCase
{
    use RefreshDatabase;

    /** Le patron du dépôt (AutomationCenterTest). */
    private function adminGlobal(): User
    {
        return User::factory()->admin()->create([
            'access_scope' => User::ACCESS_SCOPE_ALL,
            'managed_service_zone_id' => null,
            'is_active' => true,
            'permissions' => ['manage-automation'],
        ]);
    }

    public function test_la_liste_montre_les_cinq_regles_avec_leur_nom_et_le_libelle_de_leur_declencheur(): void
    {
        $this->seed(ReglesDAlerteMetierSeeder::class);

        $regles = AutomationRule::query()->get();
        $declencheurs = app(Catalogue::class)->declencheurs();

        // ANCRE — sans elle, une boucle sur zéro règle passerait au vert sans avoir rien mesuré.
        $this->assertNotEmpty($regles);
        $this->assertCount(5, $regles);

        $composant = Livewire::actingAs($this->adminGlobal())->test(AutomationCenter::class);

        foreach ($regles as $regle) {
            $composant->assertSee($regle->nom)
                ->assertSee($declencheurs[$regle->declencheur]['libelle'])
                ->assertDontSee($regle->declencheur);
        }
    }

    public function test_l_administrateur_ouvre_le_journal_d_une_des_cinq_regles(): void
    {
        $this->seed(ReglesDAlerteMetierSeeder::class);

        $regle = AutomationRule::query()->where('declencheur', 'alerte.payment_capture_failed')->firstOrFail();

        Livewire::actingAs($this->adminGlobal())
            ->test(JournalDeRegle::class, ['regleId' => $regle->id])
            ->assertOk()
            ->assertSee($regle->nom)
            ->assertSee('Aucun passage');
    }

    /**
     * LE CONSTRUCTEUR CHARGE VRAIMENT SES CONDITIONS — MESURÉ : `assertSee` sur `nom` ou
     * `conditions` échoue toujours ici (valeur accentuée ou pas), car Livewire retire le
     * wire:snapshot avant de chercher (`stripInitialData` vaut `true` par défaut) et ces champs
     * `wire:model` ne se reflètent nulle part ailleurs dans le HTML servi. `assertSet` lit l'état
     * réel du composant, ce que l'admin verra peint dès que le JS de Livewire s'hydrate.
     */
    public function test_l_administrateur_ouvre_le_constructeur_d_une_des_cinq_regles_et_y_voit_ses_conditions(): void
    {
        $this->seed(ReglesDAlerteMetierSeeder::class);

        $regle = AutomationRule::query()->where('declencheur', 'alerte.payout_failed')->firstOrFail();

        Livewire::actingAs($this->adminGlobal())
            ->test(ConstructeurDeRegle::class, ['regleId' => $regle->id])
            ->assertOk()
            ->assertSet('nom', $regle->nom)
            ->assertSet('entite', 'alerte')
            ->assertSet('declencheur', $regle->declencheur)
            ->assertSet('conditions', $regle->conditions);
    }

    /** LE TÉMOIN QUI PROTÈGE L'ESSENTIEL — rien de cette phase n'ouvre l'interrupteur global. */
    public function test_l_interrupteur_global_reste_ferme_apres_le_seeder(): void
    {
        $this->seed(ReglesDAlerteMetierSeeder::class);

        $this->assertFalse(app(FeatureFlagService::class)->isEnabled('automation'));

        Livewire::actingAs($this->adminGlobal())
            ->test(AutomationCenter::class)
            ->assertSee('désactivé');
    }
}
