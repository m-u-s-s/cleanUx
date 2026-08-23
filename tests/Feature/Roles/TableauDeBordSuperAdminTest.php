<?php

namespace Tests\Feature\Roles;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** LE SEUL DES SIX RÔLES QUI N'AVAIT PAS DE TABLEAU DE BORD. */
class TableauDeBordSuperAdminTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->create(['platform_role' => 'super_admin']);
    }

    public function test_chaque_role_pointe_vers_une_route_qui_existe(): void
    {
        // Une route de destination qui n'existe pas laisserait son titulaire sur une erreur après
        // connexion. Le test précédent vérifiait qu'un nom était déclaré ; celui-ci qu'il répond.
        foreach (Role::cases() as $role) {
            $this->assertTrue(
                Route::has($role->routeDuTableauDeBord()),
                "Route absente pour {$role->value} : {$role->routeDuTableauDeBord()}"
            );
        }
    }

    public function test_le_super_admin_ouvre_son_tableau_de_bord(): void
    {
        $reponse = $this->actingAs($this->superAdmin())->get(route('super-admin.dashboard'));

        $reponse->assertOk();
        $reponse->assertSee('Super administration');
    }

    public function test_un_administrateur_ordinaire_est_refuse(): void
    {
        // LA GARDE QUI DONNE SON SENS AU RÔLE.
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('super-admin.dashboard'))
            ->assertForbidden();
    }

    public function test_un_client_est_refuse(): void
    {
        $this->actingAs(User::factory()->client()->create())
            ->get(route('super-admin.dashboard'))
            ->assertForbidden();
    }

    public function test_il_compte_les_comptes_des_six_roles(): void
    {
        User::factory()->admin()->create();
        User::factory()->client()->create();
        User::factory()->client()->create();

        $reponse = $this->actingAs($this->superAdmin())->get(route('super-admin.dashboard'));

        // Ce que le super administrateur vient voir : la population de la plateforme, par rôle.
        foreach (Role::cases() as $role) {
            $reponse->assertSee($role->label());
        }
    }

    public function test_il_mene_aux_leviers_de_la_plateforme(): void
    {
        $reponse = $this->actingAs($this->superAdmin())->get(route('super-admin.dashboard'));

        // Un tableau de bord qui affiche sans permettre d'agir est un rapport, pas un cockpit.
        $reponse->assertSee(route('admin.utilisateurs.manage'), false);
        $reponse->assertSee(route('admin.feature-flags.manager'), false);
    }
}
