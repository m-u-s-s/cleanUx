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

/**
 * CE QUE `/api/auth/me` DOIT DIRE POUR QUE L'AIGUILLAGE SOCIÉTÉ MOBILE SOIT JUSTE.
 *
 * Trois défauts distincts se sont logés ici, et chacun rendait un espace inatteignable :
 *
 *   1. `organization_type` était lu sur `currentOrganization`, donc sur la seule colonne
 *      `current_organization_id` — que les seeders ne renseignaient pas. Type `null`, espace fermé.
 *   2. L'application prestataire combinait `is_entreprise` et `organization_type` en ET, deux
 *      termes qui s'excluent : `is_entreprise` désigne une société CLIENTE.
 *   3. Rien ne distinguait le patron de l'employé : les deux portent
 *      `organization_type = 'provider_company'`, et aiguiller dessus aurait privé le nettoyeur de
 *      ses missions.
 */
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
        /*
         * Le piège qui a condamné les cinq écrans société de l'application prestataire.
         *
         * `is_entreprise` délègue à `isClientCompany()` : il vaut `false` ici, et c'est CORRECT.
         * Ce test fige ce fait pour qu'on cesse de le prendre pour un bogue et de le combiner en ET
         * avec `organization_type === 'provider_company'` — une conjonction insatisfiable.
         */
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
        /*
         * LE TEST QUI PROTÈGE L'EMPLOYÉ.
         *
         * Un nettoyeur est membre de la même organisation que son patron : `organization_type` vaut
         * `provider_company` pour lui aussi. Aiguiller sur ce champ l'aurait envoyé dans un espace
         * de pilotage où aucun de ses écrans — missions, revenus, présence — n'existe.
         */
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
