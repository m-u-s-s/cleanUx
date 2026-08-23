<?php

namespace Tests\Feature\Organizations;

use App\Enums\OrganizationRole;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** UNE ÉCRITURE SANS SA LECTURE EST UNE CLÉ MORTE. */
class MatriceCoherenteTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, string> écriture → lecture qu'elle suppose */
    private const PAIRES = [
        'members.invite' => 'team.view',
        'members.edit_role' => 'team.view',
        'members.suspend' => 'team.view',
        'members.remove' => 'team.view',
        'members.manage_permissions' => 'team.view',
        'team.create' => 'team.view',
        'team.manage' => 'team.view',
        'missions.assign' => 'missions.view_all',
        'missions.dispatch' => 'missions.view_all',
        'missions.quality' => 'missions.view_all',
        'missions.reschedule' => 'missions.view_all',
        'sites.edit' => 'sites.view_all',
        'sites.delete' => 'sites.view_all',
        'sites.assign_members' => 'sites.view_all',
        'agencies.manage' => 'agencies.view',
        'finance.download' => 'finance.view',
        'finance.manage' => 'finance.view',
        'inventory.manage' => 'inventory.view',
        'quotes.manage' => 'quotes.view',
        'recruitment.manage' => 'recruitment.view',
        'fleet.manage' => 'fleet.view',
        'analytics.export' => 'analytics.view',
    ];

    public function test_aucune_ecriture_n_est_accordee_sans_sa_lecture(): void
    {
        $permissions = app(PermissionService::class);
        $incoherences = [];

        foreach (OrganizationRole::cases() as $role) {
            foreach (self::PAIRES as $ecriture => $lecture) {
                if ($permissions->roleAccordeParDefaut($role->value, $ecriture)
                    && ! $permissions->roleAccordeParDefaut($role->value, $lecture)) {
                    $incoherences[] = "{$role->value} : {$ecriture} sans {$lecture}";
                }
            }
        }

        $this->assertSame([], $incoherences, "Clés mortes dans la matrice :\n".implode("\n", $incoherences));
    }

    /** LE CAS MESURÉ, PAR LA VRAIE ROUTE — parce qu'une matrice cohérente ne prouve pas qu'un écran s'ouvre. */
    public function test_un_gestionnaire_general_ouvre_l_accueil_et_l_effectif_de_sa_societe(): void
    {
        $membre = $this->membreDeSocietePrestataire(OrganizationRole::MANAGER);

        $this->actingAs($membre, 'sanctum')->getJson('/api/provider/company/overview')->assertOk();
        $this->actingAs($membre, 'sanctum')->getJson('/api/provider/company/members')->assertOk();
    }

    /** LE TÉMOIN INVERSE : un exécutant reste dehors. */
    public function test_un_executant_reste_dehors(): void
    {
        $membre = $this->membreDeSocietePrestataire(OrganizationRole::WORKER);

        $this->actingAs($membre, 'sanctum')->getJson('/api/provider/company/overview')->assertForbidden();
        $this->actingAs($membre, 'sanctum')->getJson('/api/provider/company/members')->assertForbidden();
    }

    private function membreDeSocietePrestataire(OrganizationRole $role): User
    {
        $org = OrganizationAccount::factory()->create([
            'type' => 'provider_company',
            'status' => 'active',
        ]);

        $user = User::factory()->create([
            'organization_account_id' => $org->id,
            'current_organization_id' => $org->id,
        ]);

        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $user->id,
            'role' => $role->value,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        ProviderProfile::factory()->create([
            'user_id' => $user->id,
            'organization_account_id' => $org->id,
            'provider_type' => 'company_worker',
            'status' => 'active',
        ]);

        return $user->fresh();
    }
}
