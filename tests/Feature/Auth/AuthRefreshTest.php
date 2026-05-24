<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\Sanctum\PersonalAccessTokenV2;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for POST /api/auth/refresh — token rotation with grace period.
 *
 * Uses raw Bearer token (not Sanctum::actingAs) so that the actual
 * personal_access_tokens rows are written and the grace period logic
 * can be exercised end-to-end.
 */
class AuthRefreshTest extends TestCase
{
    use RefreshDatabase;

    public function test_refresh_returns_new_token_with_expiry(): void
    {
        $user = User::factory()->create();
        $old = $user->createToken('mobile')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$old}")
            ->postJson('/api/auth/refresh');

        $response->assertOk()
            ->assertJsonStructure(['token', 'expires_at']);

        $this->assertNotEquals($old, $response->json('token'));
    }

    public function test_old_token_still_works_during_grace_period(): void
    {
        $user = User::factory()->create();
        $old = $user->createToken('mobile')->plainTextToken;

        // Perform refresh — old token gets a grace window
        $this->withHeader('Authorization', "Bearer {$old}")
            ->postJson('/api/auth/refresh')
            ->assertOk();

        // Old token should still authorize GET /api/auth/me during grace
        $this->withHeader('Authorization', "Bearer {$old}")
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('id', $user->id);
    }

    public function test_old_token_rejected_after_grace_expires(): void
    {
        $user = User::factory()->create();
        $old = $user->createToken('mobile')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$old}")
            ->postJson('/api/auth/refresh')
            ->assertOk();

        // Force grace_until to the past
        $tokenId = (int) explode('|', $old)[0];
        PersonalAccessTokenV2::query()->where('id', $tokenId)->update([
            'rotation_grace_until' => now()->subMinute(),
        ]);

        $this->withHeader('Authorization', "Bearer {$old}")
            ->getJson('/api/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('error_code', 'token_grace_expired');
    }

    public function test_refresh_requires_auth(): void
    {
        $this->postJson('/api/auth/refresh')
            ->assertUnauthorized();
    }

    public function test_refresh_links_new_token_to_old(): void
    {
        $user = User::factory()->create();
        $old = $user->createToken('mobile')->plainTextToken;
        $oldId = (int) explode('|', $old)[0];

        $response = $this->withHeader('Authorization', "Bearer {$old}")
            ->postJson('/api/auth/refresh')
            ->assertOk();

        $newPlain = $response->json('token');
        $newId = (int) explode('|', $newPlain)[0];

        $newToken = PersonalAccessTokenV2::find($newId);
        $this->assertNotNull($newToken);
        $this->assertEquals($oldId, $newToken->rotated_from_token_id);
    }
}
