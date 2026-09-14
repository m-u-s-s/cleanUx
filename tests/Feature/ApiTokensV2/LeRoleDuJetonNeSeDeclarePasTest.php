<?php

namespace Tests\Feature\ApiTokensV2;

use App\Models\User;
use App\Services\ApiTokensV2\ApiTokenManager;
use Database\Seeders\ApiTokenScopesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * `/api/v2/tokens/me/tokens` EST EN SELF-SERVICE, et le rôle du jeton s'y déclarait dans le corps
 * de la requête. `ScopeRegistry` accorde tout à `admin` : un client ordinaire s'y délivrait un
 * jeton estampillé administrateur, et l'inventaire des jetons devenait mensonger.
 */
class LeRoleDuJetonNeSeDeclarePasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ApiTokenScopesSeeder::class);
    }

    /** LE TÉMOIN POSITIF : un vrai administrateur émet toujours son jeton admin. */
    #[Test]
    public function un_administrateur_emet_bien_un_jeton_admin(): void
    {
        $admin = User::factory()->create(['platform_role' => 'admin']);

        $jeton = app(ApiTokenManager::class)->createForUser($admin, [
            'name' => 'Jeton admin',
            'scopes' => ['admin:everything'],
            'owner_role' => 'admin',
        ])->accessToken;

        $this->assertSame('admin', $jeton->owner_role);
        $this->assertContains('admin:everything', (array) $jeton->abilities);
    }

    #[Test]
    public function un_client_ordinaire_ne_s_emet_pas_un_jeton_admin(): void
    {
        $this->expectException(ValidationException::class);

        app(ApiTokenManager::class)->createForUser(User::factory()->create(), [
            'name' => 'Escalade',
            'scopes' => ['admin:everything'],
            'owner_role' => 'admin',
        ]);
    }

    /** Le rôle seul suffit à l'escalade : sans scope demandé, `admin` ouvrait déjà tout. */
    #[Test]
    public function le_role_admin_est_refuse_meme_sans_scope_demande(): void
    {
        $this->expectException(ValidationException::class);

        app(ApiTokenManager::class)->createForUser(User::factory()->create(), [
            'name' => 'Escalade discrete',
            'owner_role' => 'admin',
        ]);
    }

    /**
     * LE SECOND DÉFAUT, INDÉPENDANT DU PREMIER. `EnforceTokenScope` traite `*` comme un
     * contournement complet, et un POST sans `scopes` délivrait exactement cela.
     */
    #[Test]
    public function un_jeton_sans_scope_ne_recoit_pas_le_laissez_passer(): void
    {
        $jeton = app(ApiTokenManager::class)->createForUser(User::factory()->create(), [
            'name' => 'Sans scope',
        ])->accessToken;

        $this->assertNotContains('*', (array) $jeton->abilities);
        $this->assertSame([], (array) $jeton->abilities);
    }

    /** LE TÉMOIN POSITIF DU SCOPE : un scope demandé et permis est bien porté. */
    #[Test]
    public function un_scope_permis_reste_accorde(): void
    {
        $jeton = app(ApiTokenManager::class)->createForUser(User::factory()->create(), [
            'name' => 'Lecture',
            'scopes' => ['read:bookings'],
        ])->accessToken;

        $this->assertSame(['read:bookings'], (array) $jeton->abilities);
    }
}
