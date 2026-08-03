<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * La reprise de session doit dire la même chose que la connexion.
 *
 * `login` sérialise explicitement `is_admin` ; `me` renvoyait les attributs bruts du modèle, qui
 * ne le portent pas. À chaque redémarrage de l'application, l'administrateur redevenait donc un
 * compte ordinaire — et l'aiguillage d'espace l'envoyait là où rien ne lui répond.
 */
class AuthMeAdminFlagTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_expose_la_qualite_d_administrateur(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create(), ['*']);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('is_admin', true)
            // La réponse porte les deux formes — à plat et sous `user`. L'application mobile lit
            // la seconde : si le drapeau n'y était pas, le correctif serait invisible pour elle.
            ->assertJsonPath('user.is_admin', true);
    }

    public function test_me_ne_promeut_personne(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'client']), ['*']);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('is_admin', false)
            ->assertJsonPath('user.is_admin', false);
    }

    public function test_me_expose_aussi_la_casquette_prestataire(): void
    {
        // Sans ce drapeau à la reprise, un compte à double casquette ne pouvait plus choisir son
        // espace : l'application l'enfermait du côté administration, sans retour.
        Sanctum::actingAs(User::factory()->admin()->create(), ['*']);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('is_provider', false)
            ->assertJsonPath('user.is_provider', false);
    }

    public function test_la_connexion_et_la_reprise_s_accordent(): void
    {
        $admin = User::factory()->admin()->create(['password' => 'password']);

        $login = $this->postJson('/api/auth/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertOk();

        $me = $this->withHeader('Authorization', 'Bearer '.$login->json('token'))
            ->getJson('/api/auth/me')
            ->assertOk();

        $this->assertSame($login->json('user.is_admin'), $me->json('is_admin'));
    }
}
