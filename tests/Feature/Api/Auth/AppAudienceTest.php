<?php

namespace Tests\Feature\Api\Auth;

use App\Models\CustomerProfile;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Chaque application n'accepte que le public qu'elle sert. */
class AppAudienceTest extends TestCase
{
    use RefreshDatabase;

    private const APP_HEADER = 'X-Brio-App';

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
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['token', 'user' => ['id', 'email']]);

        $this->assertSame(1, $user->tokens()->count(), 'Aucun jeton n’a été émis malgré l’accès autorisé.');
    }

    /** Une double casquette entre PARTOUT. */
    public function test_a_dual_role_account_is_welcome_in_both(): void
    {
        $user = $this->providerAccount();
        $this->giveClientProfile($user);

        foreach (['client', 'provider'] as $app) {
            $this->withHeader(self::APP_HEADER, $app)
                ->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])
                ->assertOk()
                ->assertJsonPath('ok', true);
        }

        // Une session par application : `assertOk` seul ne disait pas qu'elles s'ouvraient.
        $this->assertSame(2, $user->tokens()->count(), 'Les deux applications n’ont pas ouvert de session.');
    }

    /** L'administrateur entre partout. */
    public function test_an_admin_is_welcome_in_both(): void
    {
        $user = User::factory()->admin()->create(['password' => bcrypt('password')]);

        foreach (['client', 'provider'] as $app) {
            $this->withHeader(self::APP_HEADER, $app)
                ->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])
                ->assertOk();
        }
    }

    /** Sans en-tête, RIEN ne change. Les applications déjà installées ne le connaissent pas. */
    public function test_an_app_that_does_not_declare_itself_is_still_served(): void
    {
        $user = $this->providerAccount();

        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['token']);

        $this->assertSame(1, $user->tokens()->count(), 'Aucun jeton pour une application anonyme.');
    }

    // ─── Session déjà ouverte ────────────────────────────────────────────────────────────────

    /** Le contrôle vaut aussi à la REPRISE de session. */
    public function test_a_stored_session_is_rejected_by_the_wrong_app(): void
    {
        Sanctum::actingAs($this->providerAccount());

        $this->withHeader(self::APP_HEADER, 'client')
            ->getJson('/api/auth/me')
            ->assertForbidden();
    }

    /** Le RENOUVELLEMENT est une porte lui aussi. */
    public function test_the_wrong_app_cannot_refresh_its_token(): void
    {
        Sanctum::actingAs($this->providerAccount());

        $this->withHeader(self::APP_HEADER, 'client')
            ->postJson('/api/auth/refresh')
            ->assertForbidden();
    }

    public function test_a_stored_session_still_works_in_the_right_app(): void
    {
        $prestataire = $this->providerAccount();
        Sanctum::actingAs($prestataire);

        // C'est le BON compte qui repond, pas seulement « une » reponse 200.
        $this->withHeader(self::APP_HEADER, 'provider')
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.email', $prestataire->email);
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
