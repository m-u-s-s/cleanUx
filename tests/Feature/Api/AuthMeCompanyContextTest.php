<?php

namespace Tests\Feature\Api;

use App\Enums\OrganizationRole;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** CE QUE `/api/auth/me` DOIT DIRE POUR QUE L'AIGUILLAGE SOCIÉTÉ MOBILE SOIT JUSTE. */
class AuthMeCompanyContextTest extends TestCase
{
    use RefreshDatabase;

    private function membre(OrganizationAccount $org, OrganizationRole $role, bool $currentOrgRenseigne = true): User
    {
        $user = User::factory()->create([
            'organization_account_id' => $org->id,
            'current_organization_id' => $currentOrgRenseigne ? $org->id : null,
        ]);

        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $user->id,
            'role' => $role->value,
            'status' => 'active',
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        return $user;
    }

    #[Test]
    public function le_type_d_organisation_survit_a_un_current_organization_id_absent(): void
    {
        // La forme exacte qu'écrivaient les seeders : rattachement par `organization_account_id`
        // seul. `currentOrganization` est nulle, et l'ancien code en concluait « pas d'organisation ».
        $org = OrganizationAccount::factory()->providerCompany()->create();
        $patron = $this->membre($org, OrganizationRole::OWNER, currentOrgRenseigne: false);

        Sanctum::actingAs($patron, ['*']);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('organization_type', 'provider_company')
            ->assertJsonPath('organization_account_id', $org->id);
    }

    #[Test]
    public function le_membre_d_une_societe_prestataire_n_est_jamais_marque_entreprise(): void
    {
        // Le piège qui a condamné les cinq écrans société de l'application prestataire.
        $org = OrganizationAccount::factory()->providerCompany()->create();
        $patron = $this->membre($org, OrganizationRole::OWNER);

        Sanctum::actingAs($patron, ['*']);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('is_entreprise', false)
            ->assertJsonPath('organization_type', 'provider_company');
    }

    #[Test]
    public function le_patron_peut_piloter_la_societe(): void
    {
        $org = OrganizationAccount::factory()->providerCompany()->create();

        Sanctum::actingAs($this->membre($org, OrganizationRole::OWNER), ['*']);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('can_manage_company', true);
    }

    #[Test]
    public function le_dispatcheur_et_le_chef_d_equipe_aussi(): void
    {
        $org = OrganizationAccount::factory()->providerCompany()->create();

        foreach ([OrganizationRole::DISPATCHER, OrganizationRole::TEAM_LEAD, OrganizationRole::OPERATIONS_MANAGER] as $role) {
            Sanctum::actingAs($this->membre($org, $role), ['*']);

            $this->getJson('/api/auth/me')
                ->assertOk()
                ->assertJsonPath('can_manage_company', true);
        }
    }

    #[Test]
    public function le_nettoyeur_ne_pilote_rien_et_garde_son_espace_terrain(): void
    {
        // LE TEST QUI PROTÈGE L'EMPLOYÉ.
        $org = OrganizationAccount::factory()->providerCompany()->create();

        Sanctum::actingAs($this->membre($org, OrganizationRole::WORKER), ['*']);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('organization_type', 'provider_company')
            ->assertJsonPath('can_manage_company', false);
    }

    #[Test]
    public function un_prestataire_independant_n_a_ni_type_ni_pilotage(): void
    {
        $solo = User::factory()->create([
            'organization_account_id' => null,
            'current_organization_id' => null,
        ]);

        Sanctum::actingAs($solo, ['*']);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('organization_type', null)
            ->assertJsonPath('can_manage_company', false);
    }
}
