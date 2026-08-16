<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * L'ADMINISTRATION NE PROPOSE PAS DE PORTE DONT ON N'A PAS LA CLÉ.
 *
 * Les panneaux de raccourcis filtraient sur `Route::has()`, qui teste l'EXISTENCE de la route et
 * non le DROIT. Trois écrans portent pourtant une permission granulaire en plus du rôle —
 * `manage-modules`, `manage-services`, `manage-entreprises`. Un administrateur sans elles voyait
 * la carte « Modules », cliquait, et tombait sur un 403 nu.
 *
 * C'est exactement le défaut corrigé sur le bandeau de communication : la même erreur, ailleurs.
 * Elle mérite son garde ici, sans quoi elle reviendra au prochain panneau ajouté.
 */
class NavAdminNOffrePasDePorteFermeeTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_un_admin_sans_permission_ne_voit_pas_la_carte_modules(): void
    {
        $html = $this->rendu($this->admin(), 'livewire.admin.pilotage.quick-actions');

        $this->assertStringNotContainsString(route('admin.modules'), $html);

        // Témoin : le panneau rend bien ses autres cartes. Sans lui, un panneau devenu vide
        // ferait passer le refus au vert en mesurant une panne.
        $this->assertStringContainsString(route('admin.alerts'), $html);
    }

    public function test_un_admin_qui_a_la_permission_voit_la_carte(): void
    {
        $html = $this->rendu($this->admin(['manage-modules']), 'livewire.admin.pilotage.quick-actions');

        $this->assertStringContainsString(route('admin.modules'), $html);
    }

    public function test_un_super_admin_voit_tout(): void
    {
        $super = User::factory()->admin()->create([
            'is_active' => true,
            'status' => 'active',
            'platform_role' => 'super_admin',
        ]);

        $html = $this->rendu($super, 'livewire.admin.pilotage.quick-actions');

        $this->assertStringContainsString(route('admin.modules'), $html);
    }

    public function test_le_panneau_readiness_applique_la_meme_regle(): void
    {
        $sans = $this->rendu($this->admin(), 'livewire.admin.readiness.quick-links');
        $this->assertStringNotContainsString(route('admin.modules'), $sans);
        $this->assertStringContainsString(route('admin.alerts'), $sans);

        $avec = $this->rendu($this->admin(['manage-modules']), 'livewire.admin.readiness.quick-links');
        $this->assertStringContainsString(route('admin.modules'), $avec);
    }
}
