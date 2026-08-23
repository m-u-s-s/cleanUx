<?php

namespace Tests\Feature\Security;

use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Support\Livewire\Concerns\EnforcesActiveOrgMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Livewire\Livewire;
use Tests\TestCase;

/** `EnforcesActiveOrgMembership` garde 28 composants d'espace société : c'est lui qui empêche un membre d'une organisation d'atteindre les données d'une autre. */
class AppartenanceOrganisationActiveTest extends TestCase
{
    use RefreshDatabase;

    private function organisation(): OrganizationAccount
    {
        return OrganizationAccount::factory()->create(['type' => 'client_company']);
    }

    private function membre(OrganizationAccount $org, string $statut, array $colonnes): User
    {
        $user = User::factory()->create($colonnes + ['current_organization_id' => null, 'organization_account_id' => null]);
        OrganizationMember::factory()->create([
            'organization_account_id' => $org->id,
            'user_id' => $user->id,
            'status' => $statut,
        ]);

        return $user->refresh();
    }

    private function passe(User $user): bool
    {
        // Livewire transforme l'`abort` du trait en réponse 403 : c'est le STATUT
        // qu'il faut lire, pas une exception qui ne remonte jamais jusqu'ici.
        try {
            Livewire::actingAs($user)->test(ComposantSousGarde::class)->assertSee('espace société');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /** TÉMOIN POSITIF — le cas déjà couvert continue de passer. */
    public function test_temoin_membre_actif_par_current_organization_id(): void
    {
        $org = $this->organisation();
        $membre = $this->membre($org, 'active', ['current_organization_id' => $org->id]);

        $this->assertTrue($this->passe($membre));
    }

    /** L'autre colonne d'organisation doit ouvrir la même porte. */
    public function test_membre_actif_par_organization_account_id(): void
    {
        $org = $this->organisation();
        $membre = $this->membre($org, 'active', ['organization_account_id' => $org->id]);

        $this->assertTrue($this->passe($membre));
    }

    /** REFUS — l'isolation entre organisations reste entière. */
    public function test_un_non_membre_reste_dehors(): void
    {
        $org = $this->organisation();
        $autre = $this->organisation();
        $intrus = $this->membre($autre, 'active', ['organization_account_id' => $org->id]);

        $this->assertFalse($this->passe($intrus));
    }

    /** REFUS — une adhésion suspendue ne vaut pas une adhésion. */
    public function test_un_membre_non_actif_reste_dehors(): void
    {
        $org = $this->organisation();
        $suspendu = $this->membre($org, 'suspended', ['organization_account_id' => $org->id]);

        $this->assertFalse($this->passe($suspendu));
    }

    /** REFUS — sans aucune organisation, rien ne s'ouvre. */
    public function test_sans_organisation_rien_ne_s_ouvre(): void
    {
        $orphelin = User::factory()->create([
            'current_organization_id' => null,
            'organization_account_id' => null,
        ]);

        $this->assertFalse($this->passe($orphelin));
    }
}

/** Composant nu : on mesure le trait, pas ce qu'un écran ferait autour. */
class ComposantSousGarde extends Component
{
    use EnforcesActiveOrgMembership;

    public function render(): string
    {
        return '<div>espace société</div>';
    }
}
