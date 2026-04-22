<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRouteAccessTest extends TestCase
{
    use RefreshDatabase;

    private array $adminUris = [
        '/admin/dashboard',
        '/admin/planning',
        '/admin/missions',
        '/admin/utilisateurs',
        '/admin/feedbacks',
        '/admin/outils',
        '/admin/premium-clients',
    ];

    public function test_admin_can_access_admin_pages(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        foreach ($this->adminUris as $uri) {
            $this->get($uri)->assertOk();
        }
    }

    public function test_client_is_forbidden_from_admin_pages(): void
    {
        $client = User::factory()->client()->create();

        $this->actingAs($client);

        foreach ($this->adminUris as $uri) {
            $this->get($uri)->assertForbidden();
        }
    }

    public function test_employe_is_forbidden_from_admin_pages(): void
    {
        $employe = User::factory()->employe()->create();

        $this->actingAs($employe);

        foreach ($this->adminUris as $uri) {
            $this->get($uri)->assertForbidden();
        }
    }

    public function test_guest_is_redirected_from_admin_pages(): void
    {
        foreach ($this->adminUris as $uri) {
            $this->get($uri)->assertRedirect(route('login'));
        }
    }
}
