<?php

namespace Tests\Feature\Navigation;

use App\Models\CustomerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * POURQUOI CE FICHIER EXISTE. Le 2026-08-05, un compte client promu administrateur continuait de
 * voir l'interface client : « le changement de rôle ne s'effectue pas ». La base était pourtant
 * juste — `role` et `platform_role` à `admin`, les barrières de route et le portail `access-admin`
 * passants. C'est la navigation qui décidait autrement.
 *
 * LES RÔLES NE S'EXCLUENT PAS. Promouvoir un client ne lui retire pas son profil client :
 * `isClient()` et `isAdmin()` sont vrais en même temps. `navigation-menu.blade.php` testait
 * `isClient()` en premier et plaçait `isAdmin()` dans un `elseif` jamais atteint — donc menu
 * client, aucun lien vers l'administration, et un changement de rôle apparemment sans effet.
 *
 * Ailleurs, `routes/authenticated.php` et `HasUserTypeChecks::homeDashboardRoute()` testaient
 * `isAdmin()` D'ABORD. Deux priorités contradictoires : `/dashboard` menait bien à l'administration
 * pendant que le menu, lui, restait client.
 */
class AdminClientNavPrecedenceTest extends TestCase
{
    use RefreshDatabase;

    /** Un compte qui est administrateur ET client — le cas qui a produit le défaut. */
    private function adminAussiClient(): User
    {
        $user = User::factory()->create([
            'platform_role' => 'admin',
            'is_active' => true,
        ]);

        CustomerProfile::factory()->create([
            'user_id' => $user->id,
            'customer_type' => 'personal',
        ]);

        return $user->fresh();
    }

    public function test_le_compte_est_bien_administrateur_et_client(): void
    {
        $user = $this->adminAussiClient();

        // Sans les deux à vrai, le test ci-dessous passerait pour une mauvaise raison.
        $this->assertTrue($user->isAdmin(), 'le compte doit être administrateur');
        $this->assertTrue($user->isClient(), 'le compte doit AUSSI rester client');
    }

    public function test_un_administrateur_aussi_client_voit_le_menu_administration(): void
    {
        $this->actingAs($this->adminAussiClient());

        $html = view('navigation-menu')->render();

        $this->assertStringContainsString(
            route('admin.dashboard'),
            $html,
            'le menu doit mener à l’administration : sans quoi le compte est promu sans pouvoir y accéder',
        );
    }

    public function test_un_client_simple_garde_son_menu_client(): void
    {
        $user = User::factory()->create(['platform_role' => 'client', 'is_active' => true]);
        CustomerProfile::factory()->create(['user_id' => $user->id, 'customer_type' => 'personal']);

        $this->actingAs($user->fresh());

        $html = view('navigation-menu')->render();

        // La correction ne doit pas avoir déplacé le problème sur le cas courant.
        $this->assertStringNotContainsString(route('admin.dashboard'), $html);
        $this->assertStringContainsString(route('client.dashboard'), $html);
    }
}
