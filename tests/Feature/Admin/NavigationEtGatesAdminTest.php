<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\Navigation\ModuleCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LA NAVIGATION D'ADMINISTRATION DOIT LIRE LES CAPACITÉS D'ADMINISTRATION.
 *
 * `ModuleCatalogue` filtrait sur trois choses : le contexte, l'existence de la route, et la
 * permission d'ORGANISATION. Aucune ne dit ce qu'un administrateur a le droit de faire —
 * `platform_role` porte quinze capacités distinctes.
 *
 * LE DÉFAUT ALLAIT DANS LES DEUX SENS, et c'est ce qui le rendait difficile à voir.
 *
 * TROP PERMISSIF : les quatre-vingt-six tuiles de l'espace admin s'affichaient sans qu'aucune
 * capacité ne soit consultée. Un administrateur au périmètre restreint voyait des cases qui lui
 * répondent 403 — ce que le fichier lui-même appelle « pire qu'une case absente ».
 *
 * TROP RESTRICTIF : le SEUL module admin qui déclarait une clé la déclarait en `permission`, donc
 * évaluée par le service de permissions d'ORGANISATION. Or celui-ci rend `false` dès que le compte
 * n'a pas d'organisation — c'est le cas d'un administrateur plateforme. La vérification faciale
 * était donc invisible pour TOUS les administrateurs, y compris le super-administrateur.
 *
 * Une même racine : une capacité d'administration rangée dans la clé d'un autre mécanisme.
 */
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

    /**
     * LE TÉMOIN DANS L'AUTRE SENS — la capacité est réellement consultée.
     *
     * Sans lui, le test précédent passerait au vert sur un filtre qui laisserait tout passer, et
     * on aurait remplacé un défaut trop restrictif par un défaut trop permissif.
     */
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

    /**
     * TÉMOIN DE PORTÉE — les modules SANS clé `gate` ne sont pas devenus invisibles.
     *
     * Le filtre laisse passer ce qui ne déclare rien. Une erreur ici viderait la navigation
     * d'administration entière, ce qui se verrait — mais seulement après la mise en ligne.
     */
    public function test_les_modules_sans_gate_restent_visibles(): void
    {
        $this->actingAs($this->admin(superAdmin: false, capacites: ['manage-users']));

        $this->assertGreaterThan(
            50,
            count($this->modulesAdmin()),
            'Le filtre ne doit masquer que ce qui déclare une capacité.',
        );
    }

    /**
     * TOUTE CAPACITÉ DÉCLARÉE DOIT EXISTER.
     *
     * Une faute de frappe dans un `gate` masquerait la case pour tout le monde, en silence — la
     * disparition exacte que ce dépôt cherche à rendre visible.
     */
    public function test_chaque_gate_declare_est_une_capacite_reelle(): void
    {
        $connues = array_keys(User::allowedAdminPermissions());

        $declares = collect(config('modules.catalogue'))
            ->pluck('gate')
            ->filter()
            ->unique();

        // Garde-fou du test : sans aucun gate déclaré, l'assertion serait vraie pour rien.
        $this->assertGreaterThan(0, $declares->count());

        foreach ($declares as $gate) {
            $this->assertContains($gate, $connues, "« {$gate} » n’est pas une capacité d’administration connue.");
        }
    }

    // ─────────────────────────────────────────────────────────────────────

    /**
     * `pourContexte()` rend des GROUPES par categorie, pas une liste plate : chaque element porte
     * `category`, `label` et `modules`. Lire `['key']` dessus donnait « Undefined array key », et
     * en compter les elements comptait les douze categories, pas les modules.
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

        $admin->forceFill([
            'platform_role' => $superAdmin ? 'super_admin' : 'admin',
            // La colonne est `permissions` -- `admin_permissions` n'existe pas, et y ecrire
            // laissait le compte sans aucune capacite : le test mesurait alors un refus par
            // absence, pas le filtre qu'il visait.
            'permissions' => $capacites,
        ])->save();

        return $admin->refresh();
    }
}
