<?php

namespace Tests\Feature\ProviderCompany;

use App\Enums\OrganizationRole;
use App\Livewire\ProviderCompany\TeamManagement;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * RETIRER UN EMPLOYÉ : CE QUI MANQUAIT, ET CE QUI ÉTAIT DÉJÀ LÀ.
 *
 * Vérification faite avant d'agir, `TeamManagement::setStatus()` possédait DÉJÀ le scoping sur
 * l'organisation (`getOrgMember()` fait un `findOrFail` scopé), le contrôle de permission
 * (`members.suspend` / `members.remove`) et la protection contre l'auto-action. Trois gardes sur
 * cinq — le programme n'annonçait rien d'aussi précis.
 *
 * Il lui manquait exactement ce que son équivalent client possédait :
 *
 *   1. HIÉRARCHIE — `MembersAccess::changeMemberStatus()` refuse d'agir sur un rang égal ou
 *      supérieur ; ici, un chef d'équipe pouvait suspendre le propriétaire.
 *   2. DERNIER PROPRIÉTAIRE — retirer le seul propriétaire actif laissait la société sans personne
 *      pour gérer ses accès, sa facturation ou ses employés. Enfermement définitif.
 *
 * C'est la symétrie inverse de la phase 0, où c'était le client qui manquait ce que le prestataire
 * avait. Aucun des deux écrans n'est « la bonne version » de l'autre : il faut lire les deux.
 */
class TeamMemberRemovalGuardsTest extends TestCase
{
    use RefreshDatabase;

    private function membre(OrganizationAccount $org, OrganizationRole $role): OrganizationMember
    {
        $user = User::factory()->create([
            'current_organization_id' => $org->id,
            'organization_account_id' => $org->id,
        ]);

        return OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $user->id,
            'role' => $role->value,
            'status' => 'active',
            'invited_at' => now(),
            'joined_at' => now(),
        ]);
    }

    #[Test]
    public function un_chef_d_equipe_ne_suspend_pas_le_proprietaire(): void
    {
        $org = OrganizationAccount::factory()->providerCompany()->create();

        $proprietaire = $this->membre($org, OrganizationRole::OWNER);
        $chef = $this->membre($org, OrganizationRole::OPERATIONS_MANAGER);

        /*
         * ON LUI ACCORDE EXPRESSÉMENT LE DROIT DE SUSPENDRE.
         *
         * Sans cela, le test passerait pour la mauvaise raison : le chef d'équipe serait bloqué
         * faute de `members.suspend`, et non par la hiérarchie. C'est précisément le genre de
         * vert trompeur que ce lot cherche à éliminer — on éprouve la garde visée, pas une autre.
         */
        $chef->update(['permissions' => ['members.suspend' => true]]);

        Livewire::actingAs($chef->user)
            ->test(TeamManagement::class)
            ->call('suspend', $proprietaire->id)
            ->assertForbidden();

        $this->assertSame(
            'active',
            $proprietaire->fresh()->status,
            'Un subordonné pouvait suspendre son propre patron.',
        );
    }

    #[Test]
    public function on_ne_retire_pas_le_seul_proprietaire_actif(): void
    {
        $org = OrganizationAccount::factory()->providerCompany()->create();
        $proprietaire = $this->membre($org, OrganizationRole::OWNER);

        $admin = User::factory()->create([
            'platform_role' => 'admin',
            'current_organization_id' => $org->id,
            'organization_account_id' => $org->id,
        ]);

        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $admin->id,
            'role' => OrganizationRole::VIEWER->value,
            'status' => 'active',
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        Livewire::actingAs($admin)
            ->test(TeamManagement::class)
            ->call('remove', $proprietaire->id);

        $this->assertSame(
            'active',
            $proprietaire->fresh()->status,
            'Retirer le seul propriétaire actif enfermerait la société définitivement.',
        );
    }

    #[Test]
    public function un_proprietaire_retire_normalement_un_employe(): void
    {
        $org = OrganizationAccount::factory()->providerCompany()->create();

        $proprietaire = $this->membre($org, OrganizationRole::OWNER);
        $employe = $this->membre($org, OrganizationRole::WORKER);

        Livewire::actingAs($proprietaire->user)
            ->test(TeamManagement::class)
            ->call('remove', $employe->id);

        $this->assertSame('left', $employe->fresh()->status);
    }
}
