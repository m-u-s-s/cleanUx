<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserThemeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_user_settings_with_valid_theme(): void
    {
        $user = User::factory()->create(['role' => 'client', 'is_active' => true]);

        $response = $this->actingAs($user)
            ->postJson('/api/me/theme', ['theme_preference' => 'dark']);

        $response->assertOk();
        $response->assertJson(['ok' => true, 'theme_preference' => 'dark']);

        $user->refresh();
        $this->assertSame('dark', data_get($user->settings, 'theme_preference'));
    }

    public function test_rejects_invalid_theme_value(): void
    {
        $user = User::factory()->create(['role' => 'client', 'is_active' => true]);

        $response = $this->actingAs($user)
            ->postJson('/api/me/theme', ['theme_preference' => 'rainbow']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['theme_preference']);
    }

    public function test_requires_authentication(): void
    {
        $response = $this->postJson('/api/me/theme', ['theme_preference' => 'dark']);

        $response->assertStatus(401);
    }

    public function test_persists_across_requests(): void
    {
        $user = User::factory()->create(['role' => 'client', 'is_active' => true]);

        $this->actingAs($user)->postJson('/api/me/theme', ['theme_preference' => 'light']);
        $this->actingAs($user)->postJson('/api/me/theme', ['theme_preference' => 'dark']);

        $user->refresh();
        $this->assertSame('dark', data_get($user->settings, 'theme_preference'));
    }
}
