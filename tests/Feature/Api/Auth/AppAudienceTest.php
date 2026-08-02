<?php

namespace Tests\Feature\Api\Auth;

use App\Models\CustomerProfile;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Chaque application n'accepte que le public qu'elle sert.
 *
 * Les deux APK partagent le même point de connexion et les mêmes jetons Sanctum. Un prestataire
 * pouvait donc se connecter à l'application cliente : il obtenait un jeton valide, puis chaque
 * écran appelait des routes `client` que son rôle refuse — une application qui s'ouvre et ne
 * fonctionne nulle part, sans qu'aucun message n'explique pourquoi.
 *
 * L'application se DÉCLARE dans un en-tête, et le serveur tranche. La déclaration est falsifiable,
 * et ce n'est pas grave : ce garde-fou sert le produit, pas les privilèges. Ceux-ci restent tenus
 * par les gardes de rôle sur chaque route — un jeton de prestataire n'ouvre aucune route cliente,
 * en-tête ou pas.
 *
 * PAS de jeton en cas de refus : émettre un jeton puis refuser l'écran laisserait une session
 * valide dans une application qui ne veut pas d'elle.
 */
class AppAudienceTest extends TestCase
{
    use RefreshDatabase;

    private const APP_HEADER = 'X-CleanUx-App';

    // ─── Refus ───────────────────────────────────────────────────────────────────────────────

    public function test_a_client_cannot_log_into_the_provider_app(): void
    {
        $user = $this->clientAccount();

        $response = $this->withHeader(self::APP_HEADER, 'provider')
            ->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password']);

        $response->assertForbidden();
        $this->assertSame(0, $user->tokens()->count(), 'Un jeton a été émis malgré le refus.');
    }

    public function test_a_provider_cannot_log_into_the_client_app(): void
    {
        $user = $this->providerAccount();

        $response = $this->withHeader(self::APP_HEADER, 'client')
            ->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password']);

        $response->assertForbidden();
        $this->assertSame(0, $user->tokens()->count(), 'Un jeton a été émis malgré le refus.');
    }

    /** Le refus DIT quoi faire : renvoyer vers l'autre application, pas « accès refusé ». */
    public function test_the_refusal_names_the_right_application(): void
    {
        $user = $this->clientAccount();

        $this->withHeader(self::APP_HEADER, 'provider')
            ->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertForbidden()
            ->assertJsonFragment(['app' => 'client']);
    }

    // ─── Acceptations ────────────────────────────────────────────────────────────────────────

    public function test_a_client_logs_into_the_client_app(): void
    {
        $user = $this->clientAccount();

        $this->withHeader(self::APP_HEADER, 'client')
            ->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_a_provider_logs_into_the_provider_app(): void
    {
        $user = $this->providerAccount();

        $this->withHeader(self::APP_HEADER, 'provider')
            ->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk();
    }

    /**
     * Une double casquette entre PARTOUT.
     *
     * Un prestataire qui commande aussi des prestations chez lui existe. Le refuser d'un côté
     * l'obligerait à un second compte, avec un second historique et une seconde facturation.
     */
    public function test_a_dual_role_account_is_welcome_in_both(): void
    {
        $user = $this->providerAccount();
        $this->giveClientProfile($user);

        foreach (['client', 'provider'] as $app) {
            $this->withHeader(self::APP_HEADER, $app)
                ->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])
                ->assertOk();
        }
    }

    /**
     * L'administrateur entre partout.
     *
     * Il n'est ni client ni prestataire : la règle appliquée telle quelle l'enfermerait DEHORS des
     * deux applications, alors que le registre de parité lui sert déjà des modules sur mobile.
     */
    public function test_an_admin_is_welcome_in_both(): void
    {
        $user = User::factory()->admin()->create(['password' => bcrypt('password')]);

        foreach (['client', 'provider'] as $app) {
            $this->withHeader(self::APP_HEADER, $app)
                ->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])
                ->assertOk();
        }
    }

    /**
     * Sans en-tête, RIEN ne change.
     *
     * Les applications déjà installées ne le connaissent pas. Refuser en son absence déconnecterait
     * tout le parc jusqu'à la mise à jour, pour un garde-fou de confort.
     */
    public function test_an_app_that_does_not_declare_itself_is_still_served(): void
    {
        $user = $this->providerAccount();

        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk();
    }

    // ─── Session déjà ouverte ────────────────────────────────────────────────────────────────

    /**
     * Le contrôle vaut aussi à la REPRISE de session.
     *
     * Sans cela, un jeton obtenu avant ce garde-fou — ou dans l'autre application — resterait
     * valide indéfiniment : bloquer la porte d'entrée ne sert à rien si la fenêtre reste ouverte.
     */
    public function test_a_stored_session_is_rejected_by_the_wrong_app(): void
    {
        Sanctum::actingAs($this->providerAccount());

        $this->withHeader(self::APP_HEADER, 'client')
            ->getJson('/api/auth/me')
            ->assertForbidden();
    }

    /**
     * Le RENOUVELLEMENT est une porte lui aussi.
     *
     * Bloquer l'entrée sans bloquer le renouvellement laisserait un jeton logé dans la mauvaise
     * application se reconduire indéfiniment, sans jamais repasser par `/auth/me`.
     */
    public function test_the_wrong_app_cannot_refresh_its_token(): void
    {
        Sanctum::actingAs($this->providerAccount());

        $this->withHeader(self::APP_HEADER, 'client')
            ->postJson('/api/auth/refresh')
            ->assertForbidden();
    }

    public function test_a_stored_session_still_works_in_the_right_app(): void
    {
        Sanctum::actingAs($this->providerAccount());

        $this->withHeader(self::APP_HEADER, 'provider')
            ->getJson('/api/auth/me')
            ->assertOk();
    }

    // ─── Fabriques ───────────────────────────────────────────────────────────────────────────

    private function clientAccount(): User
    {
        return User::factory()->client()->create(['password' => bcrypt('password')]);
    }

    private function providerAccount(): User
    {
        $user = User::factory()->employe()->create(['password' => bcrypt('password')]);

        // `isProvider()` regarde le PROFIL, pas la colonne `role` : sans profil, la fabrique
        // produit un compte que le serveur ne reconnaît pas comme prestataire.
        ProviderProfile::factory()->create(['user_id' => $user->id]);

        $fresh = $user->fresh();
        $this->assertTrue($fresh->isProvider(), 'La fabrique ne produit pas un prestataire.');

        return $fresh;
    }

    /** Le même compte porte AUSSI un profil client : c'est ce qui fait la double casquette. */
    private function giveClientProfile(User $user): void
    {
        CustomerProfile::factory()->create(['user_id' => $user->id]);
    }
}
