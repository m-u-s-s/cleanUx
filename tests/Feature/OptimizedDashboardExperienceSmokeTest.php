<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class OptimizedDashboardExperienceSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_optimized_admin_client_employee_pages_render(): void
    {
        /*
         * QUATRE DES HUIT CAPACITES LISTEES ICI N'EXISTAIENT PAS.
         *
         * `manage-planning`, `manage-missions`, `manage-feedbacks` et `manage-tools` ne figurent
         * dans aucune liste : les vraies s'appellent `manage-calendar`, `manage-orchestration`,
         * `manage-quality` et `manage-platform`. La liste etait donc a moitie fictive -- sans
         * consequence tant que rien ne verifiait les capacites, ce qui etait le cas jusqu'ici.
         *
         * Ce test balaie l'espace d'administration : il lui faut un compte qui peut tout ouvrir, et
         * `adminComplet()` derive sa liste de la source, ce qui rend un nom invente impossible.
         */
        $admin = User::factory()->adminComplet()->create(['is_active' => true]);

        $client = User::factory()->client()->create([
            'is_active' => true,
        ]);

        $employe = User::factory()->employe()->create([
            'is_active' => true,
        ]);

        $this->assertRoutesOkFor($admin, [
            'admin.dashboard',
            'admin.planning',
            'admin.missions',
        ]);

        $this->assertRoutesOkFor($client, [
            'client.dashboard',
            'client.finance',
            'client.rendezvous.index',
        ]);

        $this->assertRoutesOkFor($employe, [
            'employe.dashboard',
            'employe.missions',
            'employe.planning',
        ]);
    }

    private function assertRoutesOkFor(User $user, array $routeNames): void
    {
        $this->actingAs($user);

        foreach ($routeNames as $routeName) {
            if (! Route::has($routeName)) {
                continue;
            }

            $this->get(route($routeName))->assertOk();
        }
    }
}
