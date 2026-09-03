<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * L'ADMINISTRATION NE PROPOSE PAS DE PORTE DONT ON N'A PAS LA CLÉ.
 *
 * `Route::has()` dit que la porte existe, pas qu'on a la clé : un panneau de raccourcis doit
 * décider sa visibilité sur LE MÊME test que le middleware qui garde la route, faute de quoi
 * l'administrateur clique et tombe sur un 403 nu.
 *
 * Deux panneaux portaient cette règle. Celui de pilotage a quitté le produit le 2026-09-03 avec
 * sa dernière page porteuse ; celui de préparation production la porte toujours, et les pages
 * voisines du centre d'outils l'appliquent à leur tour (voir `LesOutilsNeDupliquentPlusUnePage`).
 */
class NavAdminNOffrePasDePorteFermeeTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_admin_sans_permission_ne_voit_pas_la_carte_modules(): void
    {
        $html = $this->rendu($this->admin(), 'livewire.admin.readiness.quick-links');

        $this->assertStringNotContainsString(route('admin.modules'), $html);

        // TÉMOIN : le panneau rend bien ses autres cartes. Sans lui, un panneau devenu vide
        // ferait passer le refus au vert en mesurant une panne.
        $this->assertStringContainsString(route('admin.alerts'), $html);
    }

    public function test_un_admin_qui_a_la_permission_voit_la_carte(): void
    {
        $html = $this->rendu($this->admin(['manage-modules']), 'livewire.admin.readiness.quick-links');

        $this->assertStringContainsString(route('admin.modules'), $html);
    }

    public function test_un_super_admin_voit_tout(): void
    {
        $super = $this->prendreLeSiege(['role' => 'admin', 'status' => 'active']);

        $html = $this->rendu($super, 'livewire.admin.readiness.quick-links');

        $this->assertStringContainsString(route('admin.modules'), $html);
    }

    /**
     * TÉMOIN — le panneau de pilotage a bien quitté le produit.
     *
     * Sans ce contrôle, les trois tests ci-dessus pourraient être repointés sur un partiel
     * survivant pendant qu'un autre, resté en place, cesserait d'être mesuré.
     */
    public function test_temoin_le_panneau_de_pilotage_a_quitte_le_produit(): void
    {
        $this->assertFalse(view()->exists('livewire.admin.pilotage.quick-actions'),
            'Le panneau de pilotage existe encore : il doit alors être mesuré, comme celui de readiness.');
    }

    private function admin(array $permissions = []): User
    {
        return User::factory()->admin()->create([
            'is_active' => true,
            'status' => 'active',
            'platform_role' => 'admin',
            'permissions' => $permissions,
        ]);
    }

    private function rendu(User $user, string $vue): string
    {
        $this->actingAs($user);

        return view($vue)->render();
    }
}
