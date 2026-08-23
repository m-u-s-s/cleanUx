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

/** RETIRER UN EMPLOYÉ : CE QUI MANQUAIT, ET CE QUI ÉTAIT DÉJÀ LÀ. */
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

        // ON LUI ACCORDE EXPRESSÉMENT LE DROIT DE SUSPENDRE.
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
