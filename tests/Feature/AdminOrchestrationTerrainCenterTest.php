<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrchestrationTerrainCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_orchestration_center(): void
    {
        /*
         * `['*']` N'ACCORDAIT RIEN, et deux choses manquaient en meme temps.
         *
         * `canAccessAdminModule()` compare la capacite demandee aux entrees de `permissions` : il
         * n'y a AUCUNE semantique de joker. Et le controle exige `platform_role` parmi
         * « admin » ou « super_admin » -- la colonne `role` ne suffit pas. Le compte etait donc
         * refuse deux fois.
         *
         * On ne rend pas `'*'` significatif : elargir un controle d'autorisation pour faire passer
         * un test transformerait toute ligne portant cette valeur en passe-partout. La fabrique
         * `adminComplet()` dit ce qu'elle est.
         */
        $admin = User::factory()->adminComplet()->create(['is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.orchestration'))
            ->assertOk();
    }
}
