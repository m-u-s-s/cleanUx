<?php

namespace Tests\Feature\Api;

use App\Models\OrganizationAccount;
use App\Models\Sanctum\PersonalAccessTokenV2;
use App\Models\User;
use App\Services\ApiTokensV2\ApiTokenManager;
use Database\Seeders\ApiTokenScopesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * UN IDENTIFIANT NE DÉSIGNE PAS UN PROPRIÉTAIRE DANS UNE TABLE POLYMORPHE.
 *
 * `personal_access_tokens` porte `tokenable_type` + `tokenable_id`. `rotateMyToken` et
 * `revokeMyToken` ne comparaient QUE `tokenable_id` à `$request->user()->id` : « l'utilisateur
 * numéro 7 » et « l'organisation numéro 7 » étaient donc le même propriétaire aux yeux de ces deux
 * routes. `listMyTokens()` filtrait déjà sur les deux colonnes — seules ces deux portes n'en
 * regardaient qu'une.
 *
 * L'enjeu n'est pas symbolique : `rotate` REND LE NOUVEAU JETON EN CLAIR dans sa réponse, et
 * `revoke` supprime la ligne. C'est une prise de contrôle d'un côté, une coupure de service de
 * l'autre.
 */
class JetonDUnAutreTypeDePorteurTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ApiTokenScopesSeeder::class);
        Config::set('api_tokens_v2.allowed_scopes', ['read:bookings', 'admin:everything']);
        Config::set('api_tokens_v2.owner_roles', ['api_partner', 'admin']);
        Config::set('api_tokens_v2.default_owner_role', 'api_partner');
        Config::set('api_tokens_v2.default_expiry_days', 365);
    }

    /**
     * Fabrique un jeton porté par une entité d'un AUTRE type, dont l'identifiant coïncide avec
     * celui de l'attaquant. C'est la collision que la garde d'origine ne voyait pas.
     */
    private function jetonDUnAutrePorteur(int $memeIdentifiant): PersonalAccessTokenV2
    {
        $jeton = new PersonalAccessTokenV2;

        $jeton->forceFill([
            'tokenable_type' => (new OrganizationAccount)->getMorphClass(),
            'tokenable_id' => $memeIdentifiant,
            'name' => 'integration-societe',
            'token' => hash('sha256', Str::random(40)),
            'abilities' => ['read:bookings'],
            'owner_role' => 'api_partner',
        ])->save();

        return $jeton->refresh();
    }

    /**
     * ATTAQUE (b) — révoquer le jeton d'un porteur d'un autre type.
     *
     * Sans la vérification de type, la réponse était 200 et la ligne DISPARAISSAIT :
     * `assertStatus(403)` tombe d'abord, et `assertDatabaseHas` juste après. Les deux portent la
     * faille, la seconde en mesure le dégât.
     */
    public function test_revoquer_le_jeton_d_un_autre_type_de_porteur_est_refuse(): void
    {
        $attaquant = User::factory()->create();
        $jetonVise = $this->jetonDUnAutrePorteur($attaquant->id);

        Sanctum::actingAs($attaquant);

        $this->deleteJson("/api/v2/tokens/me/tokens/{$jetonVise->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $jetonVise->id,
            'tokenable_type' => (new OrganizationAccount)->getMorphClass(),
        ]);
    }

    /**
     * ATTAQUE (b bis) — faire tourner le jeton d'un porteur d'un autre type.
     *
     * Sans la vérification de type, l'exécution atteignait `ApiTokenManager::rotate()` : la réponse
     * n'était plus un refus mais une 422 de validation. `assertStatus(403)` tombe — la route
     * cessait de dire « ce jeton n'est pas le vôtre » alors que c'est exactement le cas.
     */
    public function test_faire_tourner_le_jeton_d_un_autre_type_de_porteur_est_refuse(): void
    {
        $attaquant = User::factory()->create();
        $jetonVise = $this->jetonDUnAutrePorteur($attaquant->id);

        Sanctum::actingAs($attaquant);

        $this->postJson("/api/v2/tokens/me/tokens/{$jetonVise->id}/rotate")
            ->assertStatus(403);

        // Aucune fenêtre de grâce n'a été ouverte : la rotation n'a pas commencé.
        $this->assertNull($jetonVise->refresh()->rotation_grace_until);
    }

    /**
     * Le pendant positif : sur son propre jeton, la rotation et la révocation marchent toujours.
     * Sans lui, on ne saurait pas distinguer « la faille est fermée » de « la route est cassée ».
     */
    public function test_le_porteur_legitime_fait_toujours_tourner_et_revoque_son_jeton(): void
    {
        $proprietaire = User::factory()->create();

        $aFaireTourner = app(ApiTokenManager::class)
            ->createForUser($proprietaire, ['name' => 'rotation', 'scopes' => ['read:bookings']])
            ->accessToken;
        $aRevoquer = app(ApiTokenManager::class)
            ->createForUser($proprietaire, ['name' => 'revocation', 'scopes' => ['read:bookings']])
            ->accessToken;

        Sanctum::actingAs($proprietaire);

        $this->postJson("/api/v2/tokens/me/tokens/{$aFaireTourner->id}/rotate")
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->deleteJson("/api/v2/tokens/me/tokens/{$aRevoquer->id}")
            ->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $aRevoquer->id]);
    }
}
