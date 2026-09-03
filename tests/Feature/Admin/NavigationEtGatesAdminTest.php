<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\Navigation\ModuleCatalogue;
use App\Support\Platform\PorteDuSiege;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** LA NAVIGATION D'ADMINISTRATION DOIT LIRE LES CAPACITÉS D'ADMINISTRATION. */
class NavigationEtGatesAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_super_admin_voit_la_verification_faciale(): void
    {
        $this->actingAs($this->admin(superAdmin: true));

        $this->assertTrue(
            $this->voitLeModule('admin:admin.face-check.center'),
            'Le module était rangé sous une permission d’ORGANISATION : invisible à tout compte '
            .'sans organisation, donc à tous les administrateurs.',
        );
    }

    /** LE TÉMOIN DANS L'AUTRE SENS — la capacité est réellement consultée. */
    public function test_un_admin_sans_la_capacite_ne_voit_pas_la_tuile(): void
    {
        $this->actingAs($this->admin(superAdmin: false, capacites: ['manage-users']));

        $this->assertFalse($this->voitLeModule('admin:admin.face-check.center'));
    }

    /** Et avec la capacité, la tuile revient : c'est bien elle qui décide. */
    public function test_la_capacite_accordee_fait_reapparaitre_la_tuile(): void
    {
        $this->actingAs($this->admin(superAdmin: false, capacites: ['manage-face-check']));

        $this->assertTrue($this->voitLeModule('admin:admin.face-check.center'));
    }

    /** TÉMOIN DE PORTÉE — ce qui ne déclare RIEN reste visible. */
    public function test_les_modules_sans_gate_restent_visibles(): void
    {
        $this->actingAs($this->admin(superAdmin: false, capacites: ['manage-users']));

        $visibles = collect($this->modulesAdmin())->pluck('key')->all();

        $disparues = array_values(array_diff(
            ['*:profile.show', 'admin:admin.dashboard', 'admin:admin.home'],
            $visibles,
        ));

        $this->assertSame(
            [],
            $disparues,
            'Ces entrees ne declarent aucune capacite et ont pourtant disparu : un administrateur '
            .'au perimetre restreint n aurait plus de porte d entree.',
        );

        // Et le filtre mord toujours : ce qui déclare une capacité qu'il n'a pas reste caché.
        $this->assertNotContains('admin:admin.finance', $visibles);
    }

    /**
     * LES CAPACITÉS QUI NE S'ACCORDENT PAS, avec leur motif.
     *
     * `allowedAdminPermissions()` liste ce qu'on PEUT donner à un administrateur. Le siège de
     * super-administrateur n'en fait pas partie : ce n'est pas une permission qu'on accorde,
     * c'est un fait sur qui l'on est. L'y inscrire le rendrait cochable dans l'écran des
     * permissions — exactement ce que ce siège interdit.
     *
     * @var list<string>
     */
    private const NON_ASSIGNABLES = ['hold-platform-seat'];

    /** TOUTE CAPACITÉ DÉCLARÉE DOIT EXISTER. */
    public function test_chaque_gate_declare_est_une_capacite_reelle(): void
    {
        $connues = array_keys(User::allowedAdminPermissions());

        $declares = collect(config('modules.catalogue'))
            ->pluck('gate')
            ->filter()
            ->unique();

        // Garde-fou du test : sans aucun gate déclaré, l'assertion serait vraie pour rien.
        $this->assertGreaterThan(0, $declares->count());

        // $declares est une Collection : on la ramene a un tableau avant la difference.
        $inconnues = array_values(array_diff($declares->all(), $connues, self::NON_ASSIGNABLES));

        $this->assertSame([], $inconnues, 'Ces capacites declarees par la navigation n existent pas.');
    }

    // ─────────────────────────────────────────────────────────────────────

    /**
     * `pourContexte()` rend des GROUPES par categorie, pas une liste plate : chaque element porte `category`, `label` et `modules`.
     *
     * @return list<array<string, mixed>>
     */
    private function modulesAdmin(): array
    {
        return ModuleCatalogue::pourContexte('admin')
            ->flatMap(fn (array $groupe) => $groupe['modules'])
            ->all();
    }

    private function voitLeModule(string $cle): bool
    {
        foreach ($this->modulesAdmin() as $module) {
            if (($module['key'] ?? null) === $cle) {
                return true;
            }
        }

        return false;
    }

    /** @param  list<string>  $capacites */
    private function admin(bool $superAdmin, array $capacites = []): User
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        PorteDuSiege::ouvrir(fn () => $admin->forceFill([
            'platform_role' => $superAdmin ? 'super_admin' : 'admin',
            // La colonne est `permissions` -- `admin_permissions` n'existe pas, et y ecrire
            // laissait le compte sans aucune capacite : le test mesurait alors un refus par
            // absence, pas le filtre qu'il visait.
            'permissions' => $capacites,
        ])->save());

        return $admin->refresh();
    }
}
