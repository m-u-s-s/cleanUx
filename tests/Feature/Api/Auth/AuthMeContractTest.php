<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** `/auth/me` et l'application mobile ne parlaient pas la même langue. */
class AuthMeContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_exposes_the_user_under_a_user_key_for_the_mobile_apps(): void
    {
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', $user->email);
    }

    /** Et la forme À PLAT reste, parce que des consommateurs la lisent déjà. */
    public function test_the_flat_shape_is_preserved(): void
    {
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('is_premium', false);
    }

    /** L'ÉTAT DE VÉRIFICATION DE L'ADRESSE, DIT AUX DEUX ENDROITS. */
    public function test_les_deux_reponses_annoncent_la_verification_de_l_adresse(): void
    {
        $nonVerifie = User::factory()->client()->create([
            'email_verified_at' => null,
            'password' => bcrypt('password'),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => $nonVerifie->email,
            'password' => 'password',
        ])->assertOk()->assertJsonPath('user.email_verified', false);

        Sanctum::actingAs($nonVerifie);
        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('email_verified', false)
            ->assertJsonPath('user.email_verified', false);

        $verifie = User::factory()->client()->create([
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => $verifie->email,
            'password' => 'password',
        ])->assertOk()->assertJsonPath('user.email_verified', true);
    }
}
