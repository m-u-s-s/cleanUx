<?php

namespace Tests\Feature\Api\Auth;

use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * LA REPRISE DE SESSION DOIT DIRE LA MÊME CHOSE QUE LA CONNEXION — CASQUETTE SOCIÉTÉ COMPRISE.
 *
 * `ApiAuthController::serializeUser()` expose déjà `is_entreprise` et `organization_account_id`
 * à la connexion ; `me` construisait sa réponse sur `$user->toArray()`, qui ne porte que des
 * colonnes. Un compte société redevenait donc un particulier à chaque redémarrage de
 * l'application, avec un jeton pourtant valide.
 *
 * C'est exactement le défaut déjà corrigé pour `is_admin` (voir AuthMeAdminFlagTest) : l'aiguillage
 * d'espace serait juste au login et faux ensuite. Sans `organization_type`, l'application ne peut
 * pas non plus distinguer une société CLIENTE d'une société PRESTATAIRE — deux espaces différents.
 */
class AuthMeCompanyFlagsTest extends TestCase
{
    use RefreshDatabase;

    private function membreDe(OrganizationAccount $org): User
    {
        $user = User::factory()->create([
            'current_organization_id' => $org->id,
            'organization_account_id' => $org->id,
        ]);

        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        return $user;
    }

    #[Test]
    public function me_expose_la_casquette_societe_cliente(): void
    {
        $org = OrganizationAccount::factory()->clientCompany()->create();

        Sanctum::actingAs($this->membreDe($org), ['*']);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('is_entreprise', true)
            ->assertJsonPath('organization_type', 'client_company')
            ->assertJsonPath('organization_account_id', $org->id)
            // L'application mobile lit la forme imbriquée : sans elle, le correctif lui échappe.
            ->assertJsonPath('user.is_entreprise', true)
            ->assertJsonPath('user.organization_type', 'client_company');
    }

    #[Test]
    public function me_distingue_une_societe_prestataire(): void
    {
        $org = OrganizationAccount::factory()->providerCompany()->create();

        Sanctum::actingAs($this->membreDe($org), ['*']);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('organization_type', 'provider_company');
    }

    #[Test]
    public function me_ne_donne_pas_de_societe_a_un_particulier(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['*']);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('is_entreprise', false)
            ->assertJsonPath('organization_type', null);
    }
}
