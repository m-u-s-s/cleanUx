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
        $admin = User::factory()->admin()->create([
            'is_active' => true,
            'permissions' => [
                'manage-planning',
                'manage-missions',
                'manage-users',
                'manage-feedbacks',
                'manage-tools',
                'manage-premium',
                'manage-finance',
                'manage-analytics',
            ],
        ]);

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
