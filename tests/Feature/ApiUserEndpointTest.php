<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiUserEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_sanctum_user_can_access_api_user_endpoint(): void
    {
        $user = User::factory()->client()->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('email', $user->email);
    }

    public function test_guest_cannot_access_api_user_endpoint(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
    }
}
