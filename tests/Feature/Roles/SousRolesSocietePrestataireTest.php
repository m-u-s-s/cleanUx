<?php

namespace Tests\Feature\Roles;

use App\Enums\OrganizationRole;
use App\Enums\OrganizationType;
use App\Enums\Role;
use Tests\TestCase;

/** LES ONZE SOUS-RÔLES APPARTIENNENT À `provider_societe`. */
class SousRolesSocietePrestataireTest extends TestCase
{
    public function test_la_societe_prestataire_dispose_des_onze_sous_roles(): void
    {
        $this->assertCount(11, OrganizationRole::forProviderCompany());
        $this->assertCount(11, OrganizationRole::cases());

        foreach (OrganizationRole::cases() as $sousRole) {
            $this->assertContains($sousRole, OrganizationRole::forProviderCompany(), $sousRole->value);
        }
    }

    public function test_le_role_canonique_expose_les_memes_onze(): void
    {
        $this->assertSame(
            OrganizationRole::forProviderCompany(),
            Role::PROVIDER_SOCIETE->sousRoles(),
            'Deux listes des mêmes sous-rôles finiraient par diverger.'
        );
    }

    public function test_la_societe_cliente_garde_les_siens(): void
    {
        // Six rôles, inchangés : `MembersAccess` les propose à l'écran et les valide en retour.
        $this->assertCount(6, OrganizationRole::forClientCompany());
        $this->assertContains(OrganizationRole::REQUESTER, OrganizationRole::forClientCompany());
        $this->assertContains(OrganizationRole::SITE_MANAGER, OrganizationRole::forClientCompany());
    }

    public function test_le_type_d_organisation_suit_la_meme_liste(): void
    {
        // `OrganizationType::availableRoles()` sert les écrans d'invitation : une liste plus courte
        // que celle du rôle canonique rendrait certains sous-rôles inattribuables.
        $this->assertCount(11, OrganizationType::PROVIDER_COMPANY->availableRoles());
    }

    public function test_les_rangs_restent_comparables_entre_tous(): void
    {
        // `canManage()` compare des rangs.
        foreach (OrganizationRole::cases() as $sousRole) {
            $this->assertGreaterThan(0, $sousRole->rank(), $sousRole->value);
        }

        $this->assertTrue(OrganizationRole::OWNER->canManage(OrganizationRole::MANAGER));
        $this->assertTrue(OrganizationRole::MANAGER->canManage(OrganizationRole::TEAM_LEAD));
        $this->assertFalse(OrganizationRole::WORKER->canManage(OrganizationRole::TEAM_LEAD));
    }
}
