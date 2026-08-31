<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Automation\ConstructeurDeRegle;
use App\Livewire\Admin\Automation\JournalDeRegle;
use App\Livewire\Admin\AutomationCenter;
use App\Models\AutomationRule;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * LE DEFAUT DOMINANT DE CE DEPOT : l'ecran complet que personne ne peut atteindre.
 * Les trois ecrans de la phase se servent par une route, se lient entre eux, et le chemin
 * complet — creer, observer, executer, relire — se parcourt sans raccourci.
 */
class AutomationEcransJoignablesTest extends TestCase
{
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

    /** Le meme administrateur, MOINS la capacite du module — le seul ecart mesure. */
    private function adminSansLaPermission(): User
    {
        return User::factory()->admin()->create([
            'access_scope' => User::ACCESS_SCOPE_ALL,
            'managed_service_zone_id' => null,
            'is_active' => true,
            'permissions' => [],
        ]);
    }

    private function regle(array $attributs = []): AutomationRule
    {
        return AutomationRule::create(array_merge([
            'nom' => 'Réservations en attente',
            'entite' => 'booking',
            'declencheur' => 'cadence',
            'cadence' => 'quart_heure',
            'conditions' => ['field' => 'statut', 'op' => 'eq', 'value' => 'en_attente'],
            'actions' => [['cle' => 'journaliser', 'parametres' => ['message' => 'vue']]],
        ], $attributs));
    }

    // ── Une route sert chaque ecran ──────────────────────────────────────

    public function test_une_route_sert_le_constructeur_pour_une_regle_neuve(): void
    {
        $this->actingAs($this->adminGlobal())
            ->get(route('admin.automation.regles.creer'))
            ->assertOk()
            ->assertSeeLivewire(ConstructeurDeRegle::class)
            ->assertSee('Nouvelle règle');
    }

    public function test_une_route_sert_le_constructeur_pour_une_regle_existante(): void
    {
        $regle = $this->regle();

        // Le titre est conditionne a `$regleId`, qui ne vient QUE du parametre de route : c'est
        // lui qui prouve que la route transmet la regle, pas une chaine cherchee dans l'instantane.
        $this->actingAs($this->adminGlobal())
            ->get(route('admin.automation.regles.modifier', $regle))
            ->assertOk()
            ->assertSeeLivewire(ConstructeurDeRegle::class)
            ->assertSee('Modifier la règle')
            ->assertDontSee('Nouvelle règle');
    }

    public function test_une_route_sert_le_journal_d_une_regle(): void
    {
        $regle = $this->regle();

        $this->actingAs($this->adminGlobal())
            ->get(route('admin.automation.regles.journal', $regle))
            ->assertOk()
            ->assertSeeLivewire(JournalDeRegle::class)
            ->assertSee('Journal — Réservations en attente');
    }

    /** LA LISTE MENE AUX DEUX AUTRES : une route qu'aucun ecran ne lie reste injoignable. */
    public function test_la_liste_porte_les_liens_vers_le_constructeur_et_vers_le_journal(): void
    {
        $regle = $this->regle();

        $html = $this->actingAs($this->adminGlobal())
            ->get(route('admin.automation'))
            ->assertOk()
            ->getContent() ?: '';

        $this->assertStringContainsString(route('admin.automation.regles.creer', absolute: false), $html);
        $this->assertStringContainsString(route('admin.automation.regles.modifier', $regle, absolute: false), $html);
        $this->assertStringContainsString(route('admin.automation.regles.journal', $regle, absolute: false), $html);
    }

    /** LA PORTE DE ROUTE — `module_gate` ne nomme que `admin.automation` : ces trois-la
     *  portent leur capacite explicitement. Temoin : les trois tests ci-dessus rendent 200. */
    public function test_un_administrateur_sans_la_permission_n_atteint_aucun_des_trois_ecrans(): void
    {
        $regle = $this->regle();
        $sans = $this->adminSansLaPermission();

        $this->actingAs($sans)->get(route('admin.automation.regles.creer'))->assertForbidden();
        $this->actingAs($sans)->get(route('admin.automation.regles.modifier', $regle))->assertForbidden();
        $this->actingAs($sans)->get(route('admin.automation.regles.journal', $regle))->assertForbidden();
    }

    // ── Le chemin complet, sans raccourci ────────────────────────────────

    /**
     * DE LA LISTE A LA LIGNE SIMULEE : ouvrir le constructeur, enregistrer, ouvrir le journal,
     * mettre en observation, executer la commande, et relire la ligne posee. Chaque maillon
     * passe par son ecran ou sa route reelle — jamais par un raccourci de modele.
     */
    public function test_le_chemin_complet_de_la_liste_a_la_ligne_simulee(): void
    {
        config()->set('features.automation', true);

        $reservation = Booking::factory()->create(['status' => 'en_attente']);
        $admin = $this->adminGlobal();

        // 1. La liste : elle mene au constructeur.
        $liste = $this->actingAs($admin)->get(route('admin.automation'))->assertOk();
        $this->assertStringContainsString(
            route('admin.automation.regles.creer', absolute: false),
            $liste->getContent() ?: ''
        );

        // 2. Le constructeur, atteint par sa route, enregistre une regle.
        $this->actingAs($admin)->get(route('admin.automation.regles.creer'))->assertOk();

        Livewire::actingAs($admin)
            ->test(ConstructeurDeRegle::class)
            ->set('nom', 'Réservations en attente à relancer')
            ->set('entite', 'booking')
            ->set('declencheur', 'cadence')
            ->set('cadence', 'chaque_minute')
            ->set('conditionsJson', '{"field": "statut", "op": "eq", "value": "en_attente"}')
            ->call('appliquerJson')
            ->assertHasNoErrors()
            ->set('actions', [['cle' => 'journaliser', 'parametres' => ['message' => 'à relancer']]])
            ->call('enregistrer')
            ->assertHasNoErrors();

        $regle = AutomationRule::query()->where('nom', 'Réservations en attente à relancer')->firstOrFail();
        $this->assertSame(AutomationRule::ETAT_BROUILLON, $regle->etat);

        // 3. Son journal s'ouvre par sa route — vide, et il le dit.
        $this->actingAs($admin)
            ->get(route('admin.automation.regles.journal', $regle))
            ->assertOk()
            ->assertSee('Aucun passage');

        // 4. La liste la met en observation.
        Livewire::actingAs($admin)
            ->test(AutomationCenter::class)
            ->call('cibler', $regle->id)
            ->call('observer');

        $this->assertSame(AutomationRule::ETAT_OBSERVATION, $regle->fresh()->etat);

        // 5. La commande passe.
        Artisan::call('automation:executer');

        // 6. Le journal, reouvert par sa route, porte la ligne simulee.
        $this->actingAs($admin)
            ->get(route('admin.automation.regles.journal', $regle))
            ->assertOk()
            ->assertSee('Simulée')
            ->assertSee('booking #'.$reservation->id)
            ->assertDontSee('Aucun passage');
    }

    // ── La capacite garde AUSSI le chemin d'action Livewire ──────────────

    /**
     * `/livewire/update` NE REJOUE AUCUN INTERMEDIAIRE DE ROUTE : sans capacite verifiee sur le
     * composant, un administrateur sans `manage-automation` armerait une regle en appelant sa methode.
     */
    public function test_sans_la_permission_la_liste_refuse_le_montage_et_l_action(): void
    {
        $regle = $this->regle();

        Livewire::actingAs($this->adminSansLaPermission())
            ->test(AutomationCenter::class)
            ->assertForbidden();

        // L'INSTANTANE EST VALIDE, ET IL NE SUFFIT PAS : monte par un administrateur habilite,
        // il est ensuite rejoue par un autre qui ne l'est pas — exactement `/livewire/update`.
        $composant = Livewire::actingAs($this->adminGlobal())->test(AutomationCenter::class);

        Livewire::actingAs($this->adminSansLaPermission());

        $composant->call('cibler', $regle->id)->assertForbidden();
    }

    /** TEMOIN — avec la capacite, la meme action aboutit. */
    public function test_temoin_avec_la_permission_la_liste_cible_une_regle(): void
    {
        $regle = $this->regle();

        Livewire::actingAs($this->adminGlobal())
            ->test(AutomationCenter::class)
            ->call('cibler', $regle->id)
            ->assertSet('regleCiblee', $regle->id);
    }

    public function test_sans_la_permission_le_constructeur_refuse_le_montage_et_l_enregistrement(): void
    {
        Livewire::actingAs($this->adminSansLaPermission())
            ->test(ConstructeurDeRegle::class)
            ->assertForbidden();

        $composant = Livewire::actingAs($this->adminGlobal())->test(ConstructeurDeRegle::class);

        Livewire::actingAs($this->adminSansLaPermission());

        $composant->call('ajouterAction')->assertForbidden();
    }

    /** TEMOIN — avec la capacite, la meme action aboutit. */
    public function test_temoin_avec_la_permission_le_constructeur_ajoute_une_action(): void
    {
        Livewire::actingAs($this->adminGlobal())
            ->test(ConstructeurDeRegle::class)
            ->call('ajouterAction')
            ->assertCount('actions', 1);
    }

    public function test_sans_la_permission_le_journal_refuse_le_montage_et_le_filtre(): void
    {
        $regle = $this->regle();

        Livewire::actingAs($this->adminSansLaPermission())
            ->test(JournalDeRegle::class, ['regleId' => $regle->id])
            ->assertForbidden();

        $composant = Livewire::actingAs($this->adminGlobal())
            ->test(JournalDeRegle::class, ['regleId' => $regle->id]);

        Livewire::actingAs($this->adminSansLaPermission());

        $composant->set('filtreResultat', 'simulee')->assertForbidden();
    }

    /** TEMOIN — avec la capacite, le meme filtre s'applique. */
    public function test_temoin_avec_la_permission_le_journal_filtre(): void
    {
        $regle = $this->regle();

        Livewire::actingAs($this->adminGlobal())
            ->test(JournalDeRegle::class, ['regleId' => $regle->id])
            ->set('filtreResultat', 'simulee')
            ->assertSet('filtreResultat', 'simulee')
            ->assertOk();
    }
}
