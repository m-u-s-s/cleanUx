<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeRouteAccessTest extends TestCase
{
    use RefreshDatabase;

    private array $employeUris = [
        '/dashboard/employe',
        '/dashboard/employe/missions',
        '/dashboard/employe/historique',
    ];

    public function test_employe_can_access_employe_pages(): void
    {
        $employe = User::factory()->employe()->create();

        $this->actingAs($employe);

        foreach ($this->employeUris as $uri) {
            $this->get($uri)->assertOk();
        }
    }

    public function test_admin_is_forbidden_from_employe_pages(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        foreach ($this->employeUris as $uri) {
            $this->get($uri)->assertForbidden();
        }
    }

    public function test_client_is_forbidden_from_employe_pages(): void
    {
        $client = User::factory()->client()->create();

        $this->actingAs($client);

        foreach ($this->employeUris as $uri) {
            $this->get($uri)->assertForbidden();
        }
    }
}
