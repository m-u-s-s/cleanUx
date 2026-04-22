<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPagesAndDashboardRedirectsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_can_be_rendered(): void
    {
        $this->get('/')->assertOk();
        $this->get('/premium')->assertOk();
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
    }

    public function test_dashboard_redirects_admin_to_admin_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_dashboard_redirects_client_to_client_dashboard(): void
    {
        $client = User::factory()->client()->create();

        $this->actingAs($client)
            ->get('/dashboard')
            ->assertRedirect(route('client.dashboard'));
    }

    public function test_dashboard_redirects_employe_to_employe_dashboard(): void
    {
        $employe = User::factory()->employe()->create();

        $this->actingAs($employe)
            ->get('/dashboard')
            ->assertRedirect(route('employe.dashboard'));
    }

    public function test_guest_is_redirected_to_login_for_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }
}
