<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * « J'AI ÉTÉ PIRATÉ, JE CHANGE MON MOT DE PASSE » — ce geste doit couper l'accès du voleur.
 *
 * Mesuré le 2026-08-16 : après une réinitialisation complète par le vrai parcours web (302 vers
 * /login), l'ancien jeton mobile rendait toujours 200 sur `/auth/me` et `/client/bookings`, et le
 * nombre de jetons ne bougeait pas. Ni `ResetUserPassword` ni `UpdateUserPassword` ne révoquaient
 * quoi que ce soit. Comme `/auth/refresh` reconduit un jeton sans re-authentification, le téléphone
 * du voleur restait connecté indéfiniment — la seule réaction possible de la victime ne servait à
 * rien.
 *
 * Trois portes doivent tomber : les jetons Sanctum, les sessions web enregistrées, et le cookie
 * « se souvenir de moi ». Chaque test vérifie aussi CE QU'ON CONSERVE, sans quoi la correction
 * déconnecterait la personne du geste qu'elle vient de faire.
 */
class ChangerLeMotDePasseCoupeLesAccesTest extends TestCase
{
    use RefreshDatabase;

    // ─── Réinitialisation par e-mail : rien n'est conservé ───────────────────────────────────

    public function test_la_reinitialisation_revoque_tous_les_jetons(): void
    {
        $user = User::factory()->create(['password' => bcrypt('AncienMdp1!')]);
        $jeton = $user->createToken('telephone')->plainTextToken;

        // Le témoin : le jeton vaut quelque chose avant.
        $this->withHeader('Authorization', 'Bearer '.$jeton)->getJson('/api/auth/me')->assertOk();

        $this->reinitialiser($user, 'NouveauMdp2026!');

        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer '.$jeton)->getJson('/api/auth/me')->assertUnauthorized();
        $this->assertSame(0, $user->fresh()->tokens()->count());
    }

    /**
     * La suite tourne avec `SESSION_DRIVER=array` (phpunit.xml) et la production avec `database` :
     * sans cette bascule, le test mesurerait le repli « pilote non interrogeable » au lieu de la
     * suppression. C'est l'angle mort habituel de ce dépôt — un vert qui décrit la configuration de
     * test, pas celle qui sert les clients.
     */
    public function test_la_reinitialisation_supprime_les_sessions_web_enregistrees(): void
    {
        config(['session.driver' => 'database']);

        $user = User::factory()->create(['password' => bcrypt('AncienMdp1!')]);
        $this->poserUneSession($user, 'session-d-un-autre-navigateur');

        $this->reinitialiser($user, 'NouveauMdp2026!');

        $this->assertDatabaseMissing('sessions', ['id' => 'session-d-un-autre-navigateur']);
    }

    public function test_la_reinitialisation_renouvelle_le_jeton_de_memorisation(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('AncienMdp1!'),
            'remember_token' => 'ancien-jeton-de-memorisation',
        ]);

        $this->reinitialiser($user, 'NouveauMdp2026!');

        $this->assertNotSame('ancien-jeton-de-memorisation', $user->fresh()->remember_token);
    }

    /** Le nouveau mot de passe fonctionne : la révocation ne casse pas la connexion. */
    public function test_le_nouveau_mot_de_passe_ouvre_une_session(): void
    {
        $user = User::factory()->create(['password' => bcrypt('AncienMdp1!')]);

        $this->reinitialiser($user, 'NouveauMdp2026!');

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'NouveauMdp2026!',
        ])->assertOk();
    }

    // ─── Changement depuis le profil mobile : le jeton courant survit ────────────────────────

    public function test_le_changement_depuis_le_telephone_garde_l_appareil_courant(): void
    {
        $user = User::factory()->create(['password' => bcrypt('AncienMdp1!')]);
        $courant = $user->createToken('mon-telephone')->plainTextToken;
        $autre = $user->createToken('vieille-tablette')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$courant)
            ->patchJson('/api/profile', [
                'current_password' => 'AncienMdp1!',
                'password' => 'NouveauMdp2026!',
                'password_confirmation' => 'NouveauMdp2026!',
            ])->assertOk();

        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$courant)->getJson('/api/auth/me')->assertOk();

        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$autre)->getJson('/api/auth/me')->assertUnauthorized();
    }

    /**
     * Un mauvais mot de passe actuel ne doit RIEN révoquer : sinon il suffirait de tenter n'importe
     * quoi sur le profil de quelqu'un pour le déconnecter partout.
     */
    public function test_un_mot_de_passe_actuel_faux_ne_revoque_rien(): void
    {
        $user = User::factory()->create(['password' => bcrypt('AncienMdp1!')]);
        $autre = $user->createToken('vieille-tablette')->plainTextToken;
        $courant = $user->createToken('mon-telephone')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$courant)
            ->patchJson('/api/profile', [
                'current_password' => 'ce-n-est-pas-le-bon',
                'password' => 'NouveauMdp2026!',
                'password_confirmation' => 'NouveauMdp2026!',
            ])->assertStatus(422);

        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$autre)->getJson('/api/auth/me')->assertOk();
        $this->assertTrue(Hash::check('AncienMdp1!', $user->fresh()->password));
    }

    /** Une modification de profil SANS mot de passe ne révoque rien non plus. */
    public function test_changer_son_nom_ne_deconnecte_personne(): void
    {
        $user = User::factory()->create(['password' => bcrypt('AncienMdp1!')]);
        $autre = $user->createToken('vieille-tablette')->plainTextToken;
        $courant = $user->createToken('mon-telephone')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$courant)
            ->patchJson('/api/profile', ['name' => 'Nouveau Nom'])
            ->assertOk();

        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$autre)->getJson('/api/auth/me')->assertOk();
    }

    // ─── Fabriques ───────────────────────────────────────────────────────────────────────────

    /** Passe par le VRAI parcours Fortify : jeton de réinitialisation, puis POST du formulaire. */
    private function reinitialiser(User $user, string $nouveau): void
    {
        $jetonDeReinitialisation = Password::broker()->createToken($user);

        $this->post('/reset-password', [
            'token' => $jetonDeReinitialisation,
            'email' => $user->email,
            'password' => $nouveau,
            'password_confirmation' => $nouveau,
        ])->assertSessionHasNoErrors();
    }

    private function poserUneSession(User $user, string $id): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'navigateur-de-test',
            'payload' => base64_encode(serialize([])),
            'last_activity' => now()->getTimestamp(),
        ]);
    }
}
