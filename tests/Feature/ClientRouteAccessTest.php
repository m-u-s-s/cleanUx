<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientRouteAccessTest extends TestCase
{
    use RefreshDatabase;

    private array $clientUris = [
        '/dashboard/client',
        '/dashboard/client/rendez-vous/nouveau',
        '/dashboard/client/rendez-vous',
        '/dashboard/client/historique',
        '/dashboard/client/profil',
        '/dashboard/client/favoris-employes',
        '/dashboard/client/finance',
    ];

    public function test_client_can_access_client_pages(): void
    {
        $client = User::factory()->client()->create();

        $this->actingAs($client);

        foreach ($this->clientUris as $uri) {
            $this->get($uri)->assertOk();
        }
    }

    public function test_admin_is_forbidden_from_client_pages(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        foreach ($this->clientUris as $uri) {
            $this->get($uri)->assertForbidden();
        }
    }

    public function test_employe_is_forbidden_from_client_pages(): void
    {
        $employe = User::factory()->employe()->create();

        $this->actingAs($employe);

        foreach ($this->clientUris as $uri) {
            $this->get($uri)->assertForbidden();
        }
    }
}
