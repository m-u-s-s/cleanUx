<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\Navigation\ModuleCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** UNE CAPACITÉ DÉCLARÉE DOIT MASQUER LA TUILE *ET* FERMER LA PORTE. */
class ChaqueEcranAdminADuneCapaciteTest extends TestCase
{
    use RefreshDatabase;

    /** LES DEUX SEULS MODULES VOLONTAIREMENT OUVERTS À TOUT ADMINISTRATEUR. */
    private const OUVERTS_A_TOUS = ['admin:admin.dashboard', 'admin:admin.home'];

    public function test_chaque_module_admin_declare_une_capacite(): void
    {
        $sansGate = [];

        foreach ((array) config('modules.catalogue', []) as $module) {
            if (($module['context'] ?? null) !== 'admin') {
                continue;
            }

            $cle = (string) ($module['key'] ?? '');

            if (in_array($cle, self::OUVERTS_A_TOUS, true)) {
                continue;
            }

            if (($module['gate'] ?? null) === null) {
                $sansGate[] = $cle;
            }
        }

        $this->assertSame(
            [],
            $sansGate,
            'Ces modules d’administration ne déclarent aucune capacité : ils s’affichent pour tout '
            .'administrateur et leur écran s’ouvre pour tout administrateur. Si l’ouverture est '
            .'voulue, l’inscrire dans OUVERTS_A_TOUS avec son motif.'."\n".implode("\n", $sansGate),
        );
    }

    /**
     * TÉMOIN DE PORTÉE — on a bien lu des modules.
     *
     * Le seuil descend de 80 à 70 : neuf cases ont disparu quand l’onboarding et le catalogue
     * ont absorbé leurs pages voisines. Ce n’est pas une perte de couverture — leurs écrans
     * vivent en onglets, et leurs URL en redirections. Le témoin garde ce qu’il gardait :
     * que la liste ne soit pas vide, pas qu’elle ait une taille précise.
     */
    public function test_temoin_le_catalogue_admin_nest_pas_vide(): void
    {
        $admin = collect(config('modules.catalogue', []))
            ->filter(fn (array $m) => ($m['context'] ?? null) === 'admin');

        $this->assertGreaterThan(70, $admin->count());
        $this->assertGreaterThan(70, $admin->filter(fn (array $m) => ($m['gate'] ?? null) !== null)->count());
    }

    /** Toute capacité déclarée doit exister : une faute de frappe masquerait l'écran pour tous. */
    public function test_chaque_capacite_declaree_existe(): void
    {
        $connues = array_keys(User::allowedAdminPermissions());

        $declarees = collect(config('modules.catalogue', []))
            ->pluck('gate')
            ->filter()
            ->unique();

        $this->assertGreaterThan(10, $declarees->count());

        // Une faute de frappe dans un `gate` en amene souvent d'autres : on les nomme toutes.
        $inconnues = array_values(array_diff(
            is_array($declarees) ? $declarees : $declarees->all(),
            is_array($connues) ? $connues : $connues->all(),
        ));

        $this->assertSame([], $inconnues, 'Ces capacites declarees par les ecrans n existent pas.');
    }

    // ── La porte, pas seulement la tuile ─────────────────────────────────

    public function test_la_porte_se_ferme_sur_une_capacite_absente(): void
    {
        $this->actingAs($this->admin(['manage-users']));

        $this->get(route('admin.finance'))->assertForbidden();
    }

    /** TÉMOIN — avec la capacité, la même porte s'ouvre. */
    public function test_temoin_la_capacite_accordee_ouvre_la_porte(): void
    {
        $this->actingAs($this->admin(['manage-finance']));

        $this->get(route('admin.finance'))->assertSuccessful();
    }

    /** LE TABLEAU DE BORD RESTE ATTEIGNABLE — c'est le garde anti-enfermement. */
    public function test_un_admin_au_perimetre_etroit_atteint_toujours_son_tableau_de_bord(): void
    {
        $this->actingAs($this->admin(['manage-accounting']));

        $this->get(route('admin.dashboard'))->assertSuccessful();
    }

    /** Et un super-administrateur passe partout, comme avant. */
    public function test_un_super_admin_passe_partout(): void
    {
        $this->actingAs($this->admin([], superAdmin: true));

        $this->get(route('admin.finance'))->assertSuccessful();
        $this->get(route('admin.gdpr.center'))->assertSuccessful();
    }

    /** LA TUILE ET LA PORTE DISENT LA MÊME CHOSE. */
    public function test_la_tuile_et_la_porte_saccordent(): void
    {
        $comptable = $this->admin(['manage-accounting']);
        $this->actingAs($comptable);

        $visibles = collect(ModuleCatalogue::pourContexte('admin'))
            ->flatMap(fn (array $groupe) => $groupe['modules'])
            ->pluck('key')
            ->all();

        // ON NE COMPTE QUE LES TUILES D'ADMINISTRATION, et cette précision n'est pas cosmétique.
        $tuilesAdmin = array_values(array_filter($visibles, fn (string $cle) => str_starts_with($cle, 'admin:')));

        // Garde-fou : son propre écran, plus les deux pages d'arrivée. Rien d'autre.
        $this->assertCount(3, $tuilesAdmin, implode(', ', $tuilesAdmin));

        $this->assertContains('admin:admin.accounting-v2.center', $visibles);
        $this->assertNotContains('admin:admin.finance', $visibles);

        // Et ce que le menu cache, la porte le refuse.
        $this->get(route('admin.finance'))->assertForbidden();
    }

    /** UN MODULE SANS CAPACITE RESTE OUVERT — l'ajout ne ferme rien de lui-même. */
    public function test_une_route_sans_module_declare_reste_ouverte(): void
    {
        $this->actingAs($this->admin(['manage-users']));

        // Garde-fou du test : cette route existe bien et n'est pas cataloguée avec un gate.
        $this->assertTrue(Route::has('admin.dashboard'));

        $this->get(route('admin.dashboard'))->assertSuccessful();
    }

    // ─────────────────────────────────────────────────────────────────────

    /** @param  list<string>  $capacites */
    private function admin(array $capacites, bool $superAdmin = false): User
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $admin->forceFill([
            'platform_role' => $superAdmin ? 'super_admin' : 'admin',
            'permissions' => $capacites,
        ])->save();

        return $admin->refresh();
    }
}
