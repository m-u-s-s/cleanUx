<?php

namespace Tests\Feature\Navigation;

use App\Models\CustomerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** POURQUOI CE FICHIER EXISTE. */
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
