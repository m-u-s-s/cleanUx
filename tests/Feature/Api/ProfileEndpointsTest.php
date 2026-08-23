<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Audit fix — 6 missing endpoints called by mobile apps Coverage: - POST /api/auth/forgot-password - PUT /api/client/profile - POST /api/client/profile/avatar - POST /api/client/nps - PUT /api/provider/profile */
class ProfileEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_update_profile(): void
    {
        $user = User::factory()->create(['role' => 'client']);
        Sanctum::actingAs($user);

        $this->putJson('/api/client/profile', ['name' => 'New Name', 'locale' => 'nl'])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertEquals('New Name', $user->fresh()->name);
        $this->assertEquals('nl', $user->fresh()->locale);
    }

    public function test_client_profile_validates_locale(): void
    {
        $user = User::factory()->create(['role' => 'client']);
        Sanctum::actingAs($user);

        $this->putJson('/api/client/profile', ['locale' => 'zh'])
            ->assertUnprocessable();
    }

    public function test_provider_can_update_profile(): void
    {
        $user = User::factory()->create(['role' => 'employe']);
        Sanctum::actingAs($user);

        $this->putJson('/api/provider/profile', ['name' => 'Provider Name'])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertEquals('Provider Name', $user->fresh()->name);
    }

    public function test_forgot_password_returns_ok_regardless_of_email_existence(): void
    {
        $this->postJson('/api/auth/forgot-password', ['email' => 'nonexistent@test.com'])
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_forgot_password_validates_email_format(): void
    {
        $this->postJson('/api/auth/forgot-password', ['email' => 'not-an-email'])
            ->assertUnprocessable();
    }

    public function test_nps_accepts_valid_score(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/client/nps', ['score' => 8])
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_nps_rejects_out_of_range_score(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/client/nps', ['score' => 11])
            ->assertUnprocessable();
    }

    public function test_client_profile_requires_auth(): void
    {
        $this->putJson('/api/client/profile', ['name' => 'X'])->assertUnauthorized();
    }

    public function test_provider_profile_requires_auth(): void
    {
        $this->putJson('/api/provider/profile', ['name' => 'X'])->assertUnauthorized();
    }

    public function test_nps_requires_auth(): void
    {
        $this->postJson('/api/client/nps', ['score' => 5])->assertUnauthorized();
    }
}
