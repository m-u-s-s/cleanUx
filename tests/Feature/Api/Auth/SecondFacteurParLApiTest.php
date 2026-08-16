<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\RecoveryCode;
use Tests\TestCase;

/**
 * LA 2FA OBLIGATOIRE DES ADMINISTRATEURS NE GARDAIT QUE LE NAVIGATEUR.
 *
 * Mesuré le 2026-08-16 avec `ENFORCE_2FA_FOR_ADMINS=true`, c'est-à-dire la configuration de
 * production, sur le même serveur et au même instant :
 *
 *   WEB : /admin/dashboard → 302 vers /user/profile « activez la 2FA avant d'accéder à l'espace admin »
 *   API : /api/auth/login → jeton → /api/admin/overview 200
 *                                 → /api/admin/accounting-v2/entries 200
 *
 * La console d'administration étant entièrement native, ce n'était pas un contournement théorique :
 * c'était le chemin normal. Deux verrous s'ajoutent — le code est réclamé à la connexion de tout
 * compte qui a activé la 2FA, et l'accès administrateur exige que la 2FA soit activée.
 */
class SecondFacteurParLApiTest extends TestCase
{
    use RefreshDatabase;

    // ─── La connexion réclame le code ────────────────────────────────────────────────────────

    public function test_un_compte_avec_2fa_ne_recoit_pas_de_jeton_sur_le_seul_mot_de_passe(): void
    {
        $user = $this->compteAvec2fa();

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertForbidden()->assertJsonPath('error_code', 'two_factor_required');

        $this->assertSame(0, $user->tokens()->count(), 'Un jeton a été émis sans le second facteur.');
    }

    public function test_le_bon_code_ouvre_la_session(): void
    {
        $user = $this->compteAvec2fa();

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'two_factor_code' => $this->codeValidePour($user),
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_un_mauvais_code_est_refuse(): void
    {
        $user = $this->compteAvec2fa();

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'two_factor_code' => '000000',
        ])->assertStatus(422)->assertJsonValidationErrors('two_factor_code');

        $this->assertSame(0, $user->tokens()->count());
    }

    /**
     * Sans code de secours, une 2FA transforme un téléphone cassé en compte définitivement perdu.
     */
    public function test_un_code_de_secours_ouvre_la_session_et_est_consomme(): void
    {
        $user = $this->compteAvec2fa();
        $codes = $user->recoveryCodes();

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'recovery_code' => $codes[0],
        ])->assertOk();

        $this->assertNotContains(
            $codes[0],
            $user->fresh()->recoveryCodes(),
            'Un code de secours doit être remplacé après usage.'
        );
    }

    public function test_un_code_de_secours_inconnu_est_refuse(): void
    {
        $user = $this->compteAvec2fa();

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'recovery_code' => 'ceci-nest-pas-un-code',
        ])->assertStatus(422)->assertJsonValidationErrors('recovery_code');
    }

    /** LE TÉMOIN : un compte SANS 2FA se connecte comme avant, sans rien de plus à saisir. */
    public function test_un_compte_sans_2fa_se_connecte_normalement(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();
    }

    /**
     * Le refus ne doit pas dire à un inconnu que ce compte existe ET porte une 2FA : sur un mauvais
     * mot de passe, la réponse reste celle des identifiants incorrects.
     */
    public function test_un_mauvais_mot_de_passe_ne_revele_pas_la_presence_d_une_2fa(): void
    {
        $user = $this->compteAvec2fa();

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'mauvais',
        ])->assertStatus(422)->assertJsonMissing(['error_code' => 'two_factor_required']);
    }

    // ─── La console d'administration exige l'enrôlement ──────────────────────────────────────

    public function test_un_administrateur_sans_2fa_est_refuse_par_l_api_admin(): void
    {
        config(['auth.enforce_2fa_for_admins' => true]);

        $admin = User::factory()->admin()->create(['password' => bcrypt('password')]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/overview')
            ->assertForbidden()
            ->assertJsonPath('error_code', 'two_factor_enrollment_required');
    }

    /** LE TÉMOIN : le même administrateur, 2FA activée, entre. */
    public function test_un_administrateur_avec_2fa_atteint_l_api_admin(): void
    {
        config(['auth.enforce_2fa_for_admins' => true]);

        $admin = $this->compteAvec2fa(['password' => bcrypt('password')], admin: true);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/overview')
            ->assertOk();
    }

    /**
     * Le drapeau éteint rend le comportement d'avant : la suite tourne ainsi
     * (`ENFORCE_2FA_FOR_ADMINS=false` en test), et des dizaines de tests d'administration en
     * dépendent. Ce test épingle ce repli plutôt que de le laisser implicite.
     */
    public function test_le_drapeau_eteint_laisse_l_administrateur_passer(): void
    {
        config(['auth.enforce_2fa_for_admins' => false]);

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/overview')->assertOk();
    }

    // ─── Fabriques ───────────────────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $attributs */
    private function compteAvec2fa(array $attributs = [], bool $admin = false): User
    {
        $fabrique = User::factory();

        if ($admin) {
            $fabrique = $fabrique->admin();
        }

        $user = $fabrique->create(array_merge(['password' => bcrypt('password')], $attributs));

        /*
         * `Crypt::encrypt` et NON `encryptString` : Fortify relit ces deux colonnes avec
         * `decrypt()`, qui désérialise. Chiffrées en chaîne brute, elles font échouer la lecture sur
         * « unserialize(): Error at offset 0 » — une panne de test qui ressemble à un bug de code.
         */
        $user->forceFill([
            'two_factor_secret' => Crypt::encrypt(
                app(TwoFactorAuthenticationProvider::class)->generateSecretKey()
            ),
            'two_factor_recovery_codes' => Crypt::encrypt(json_encode(
                collect(range(1, 8))->map(fn (): string => RecoveryCode::generate())->all()
            )),
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $user->fresh();
    }

    private function codeValidePour(User $user): string
    {
        $secret = decrypt($user->two_factor_secret);

        // Le fournisseur de Fortify enveloppe pragmarx/google2fa : on lui demande le code courant
        // plutôt que d'en recalculer un à la main — deux implémentations de TOTP finiraient par
        // diverger d'une fenêtre de trente secondes, et le test échouerait une fois sur deux.
        return app(\PragmaRX\Google2FA\Google2FA::class)->getCurrentOtp($secret);
    }
}
