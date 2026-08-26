<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * L'APPLICATION SAVAIT DIRE « ADRESSE NON VÉRIFIÉE » SANS POUVOIR RIEN Y FAIRE.
 *
 * Les trois routes d'e-mail de Fortify — l'invite, le lien signé, le renvoi — sont gardées par
 * `auth:web`. Un porteur de jeton ne les atteint pas. La connexion et `/auth/me` annoncent
 * pourtant `email_verified` : l'écran pouvait poser la question et jamais la résoudre.
 *
 * C'est le préalable technique à toute décision d'imposer `verified` côté API : sans ce point
 * d'entrée, l'exiger enfermerait dehors quiconque n'a qu'un téléphone.
 */
class LApplicationPeutRedemanderLEmailDeConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_une_adresse_non_confirmee_recoit_un_nouvel_email(): void
    {
        Notification::fake();

        $user = User::factory()->client()->unverified()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/auth/email/verification-notification')
            ->assertOk()
            ->assertJsonPath('already_verified', false);

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    /**
     * LE TÉMOIN. Sans lui, le cas ci-dessus passerait au vert le jour où la route enverrait un
     * e-mail à tout le monde — on ne mesurerait plus que « quelque chose part ».
     */
    public function test_temoin_une_adresse_deja_confirmee_ne_declenche_aucun_envoi(): void
    {
        Notification::fake();

        $user = User::factory()->client()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/auth/email/verification-notification')
            ->assertOk()
            ->assertJsonPath('already_verified', true);

        Notification::assertNothingSent();
    }

    public function test_sans_jeton_la_route_refuse(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/email/verification-notification')->assertUnauthorized();

        Notification::assertNothingSent();
    }

    /**
     * `Sanctum::actingAs` FABRIQUE l'authentification et saute le crochet de Sanctum qui refuse
     * les jetons des comptes suspendus. Ce cas-la exige donc un vrai jeton porteur, sans quoi il
     * mesurerait une garde qui n'existe pas sur le chemin reel.
     */
    public function test_un_compte_desactive_ne_peut_pas_s_en_servir(): void
    {
        Notification::fake();

        $user = User::factory()->client()->unverified()->create();
        $jeton = $user->createToken('telephone')->plainTextToken;

        $user->forceFill(['is_active' => false])->save();

        $this->withHeader('Authorization', 'Bearer '.$jeton)
            ->postJson('/api/auth/email/verification-notification')
            ->assertUnauthorized();

        Notification::assertNothingSent();
    }

    /** LE TEMOIN du cas ci-dessus : le meme jeton reel, sur un compte actif, passe. */
    public function test_temoin_le_meme_jeton_reel_passe_si_le_compte_est_actif(): void
    {
        Notification::fake();

        $user = User::factory()->client()->unverified()->create();
        $jeton = $user->createToken('telephone')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$jeton)
            ->postJson('/api/auth/email/verification-notification')
            ->assertOk();

        Notification::assertSentTo($user, VerifyEmail::class);
    }
}
