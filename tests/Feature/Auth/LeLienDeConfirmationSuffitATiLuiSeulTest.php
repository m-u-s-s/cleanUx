<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * LE LIEN REÇU PAR E-MAIL DOIT CONFIRMER D'UN SEUL GESTE.
 *
 * Celui de Fortify exige `auth:web` : mesuré le 2026-08-27, il rendait `302 → /login`. Il fallait
 * donc retaper son mot de passe sur un site pour confirmer une adresse — et depuis que `verified`
 * garde l'API, c'était le SEUL chemin hors du mur d'une application mobile.
 *
 * L'URL signée est la preuve : la signature atteste qu'elle vient de nous, l'expiration la borne,
 * l'empreinte la lie à l'adresse du moment. Ce fichier vérifie les quatre — et surtout ce que la
 * confirmation N'OUVRE PAS.
 */
class LeLienDeConfirmationSuffitATiLuiSeulTest extends TestCase
{
    use RefreshDatabase;

    /** Le lien tel que la notification de Laravel le construit — pas une URL réécrite à la main. */
    private function lienEnvoyeA(User $user): string
    {
        Notification::fake();

        $user->sendEmailVerificationNotification();

        $lien = null;

        Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $notification) use ($user, &$lien) {
            $lien = $notification->toMail($user)->actionUrl;

            return true;
        });

        return (string) $lien;
    }

    public function test_un_lien_valide_confirme_sans_aucune_session(): void
    {
        $user = User::factory()->client()->unverified()->create();

        $this->get($this->lienEnvoyeA($user))->assertOk();

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    /** CE QUE LA CONFIRMATION N'OUVRE PAS. Confirmer n'est pas se connecter. */
    public function test_confirmer_n_ouvre_aucune_session(): void
    {
        $user = User::factory()->client()->unverified()->create();

        $this->get($this->lienEnvoyeA($user));

        $this->assertGuest();
    }

    public function test_l_evenement_part_une_fois_et_une_seule(): void
    {
        $user = User::factory()->client()->unverified()->create();
        $lien = $this->lienEnvoyeA($user);

        Event::fake([Verified::class]);

        $this->get($lien)->assertOk();
        Event::assertDispatchedTimes(Verified::class, 1);

        // Rouvrir un lien déjà consommé n'est pas une erreur, et ne rejoue pas l'événement.
        $this->get($lien)->assertOk();
        Event::assertDispatchedTimes(Verified::class, 1);
    }

    public function test_une_signature_absente_ou_falsifiee_est_refusee(): void
    {
        $user = User::factory()->client()->unverified()->create();

        $nu = url("/email/verify/{$user->id}/".sha1($user->getEmailForVerification()));

        $this->get($nu)->assertStatus(403);
        $this->get($this->lienEnvoyeA($user).'x')->assertStatus(403);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_un_lien_expire_le_dit_plutot_que_de_crier_a_la_faute(): void
    {
        $user = User::factory()->client()->unverified()->create();

        $perime = URL::temporarySignedRoute('verification.verify', now()->subMinute(), [
            'id' => $user->getKey(),
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        $this->get($perime)
            ->assertStatus(403)
            ->assertSee('a expiré', false);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    /** L'empreinte lie le lien à l'adresse du moment : en changer périme ce qui a déjà été envoyé. */
    public function test_changer_d_adresse_perime_les_liens_deja_envoyes(): void
    {
        $user = User::factory()->client()->unverified()->create();
        $lien = $this->lienEnvoyeA($user);

        $user->forceFill(['email' => 'autre@exemple.test'])->save();

        $this->get($lien)->assertStatus(403);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    /** Le lien d'un compte ne confirme pas celui d'un autre, même signé. */
    public function test_l_empreinte_d_un_compte_ne_vaut_pas_pour_un_autre(): void
    {
        $victime = User::factory()->client()->unverified()->create();
        $attaquant = User::factory()->client()->unverified()->create();

        $croise = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $victime->getKey(),
            'hash' => sha1($attaquant->getEmailForVerification()),
        ]);

        $this->get($croise)->assertStatus(403);

        $this->assertFalse($victime->fresh()->hasVerifiedEmail());
    }

    /** NON-RÉGRESSION WEB : un visiteur déjà connecté retrouve son tableau de bord, comme avant. */
    public function test_un_visiteur_deja_connecte_est_ramene_a_son_tableau_de_bord(): void
    {
        $user = User::factory()->client()->unverified()->create();
        $lien = $this->lienEnvoyeA($user);

        $this->actingAs($user)
            ->get($lien)
            ->assertRedirect(config('fortify.home', '/dashboard').'?verified=1');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }
}
