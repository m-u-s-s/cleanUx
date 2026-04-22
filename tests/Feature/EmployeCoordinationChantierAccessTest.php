<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeCoordinationChantierAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_employe_can_open_coordination_page(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_EMPLOYE,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('employe.coordination'))
            ->assertOk();
    }
}
