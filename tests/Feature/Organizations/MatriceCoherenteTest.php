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

/**
 * UNE ÉCRITURE SANS SA LECTURE EST UNE CLÉ MORTE.
 *
 * Ce dépôt l'a déjà payé : `sites.edit` accordé au responsable de site sans `sites.view_all`, donc
 * le droit de modifier un local sur un écran répondant 403. Le 2026-08-16, en parcourant les onze
 * sous-rôles d'une société prestataire, deux cas identiques sont apparus sur le même rôle — un
 * « gestionnaire général » pouvait INVITER dans une équipe qu'il ne pouvait pas ouvrir, et DÉCALER
 * une mission qu'il ne pouvait pas lister. Il recevait 403 sur l'accueil de sa propre société, là où
 * un répartiteur et un chef d'équipe passaient.
 *
 * CE TEST NE POSTULE PAS DE HIÉRARCHIE. La matrice décrit des SPÉCIALITÉS, pas une échelle : la
 * finance ne voit pas les missions, la qualité ne voit pas la finance, et c'est voulu. Exiger qu'un
 * rang supérieur contienne l'inférieur casserait ce modèle. Ce qu'on vérifie est plus faible et
 * indiscutable : quand un rôle peut MODIFIER quelque chose, il doit pouvoir le VOIR.
 *
 * Les paires sont limitées aux cas où la lecture est la seule porte de l'écran qui porte l'écriture.
 * `bookings.cancel` sans `bookings.view_all` n'en est pas : le « all » désigne la portée société,
 * alors que l'annulation reste bornée au site qu'on gère.
 */
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

    /**
     * LE CAS MESURÉ, PAR LA VRAIE ROUTE — parce qu'une matrice cohérente ne prouve pas qu'un écran
     * s'ouvre. C'est la leçon des tests de joignabilité de ce dépôt : la clé peut être accordée et
     * la garde lire autre chose.
     */
    public function test_un_gestionnaire_general_ouvre_l_accueil_et_l_effectif_de_sa_societe(): void
    {
        $membre = $this->membreDeSocietePrestataire(OrganizationRole::MANAGER);

        $this->actingAs($membre, 'sanctum')->getJson('/api/provider/company/overview')->assertOk();
        $this->actingAs($membre, 'sanctum')->getJson('/api/provider/company/members')->assertOk();
    }

    /**
     * LE TÉMOIN INVERSE : un exécutant reste dehors. Sans lui, le test ci-dessus passerait aussi
     * bien si la garde avait disparu — et c'est précisément ce qui était arrivé à ces quatre
     * lectures, ouvertes à tous avant qu'une permission ne leur soit posée.
     */
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
