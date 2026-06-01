<?php

namespace Tests\Feature\Relations;

use App\Enums\OrganizationType;
use App\Livewire\Client\BrowseCompanies;
use App\Livewire\Client\PrendreRendezVous;
use App\Models\CustomerProfile;
use App\Models\OrganizationAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SP3 Task 8 — l'UI web du formulaire de réservation expose la sélection d'une
 * SOCIÉTÉ (palier premium). Miroir EXACT du pattern SP2 (BrowseProviders en mode
 * sélection émet un event capté par PrendreRendezVous), mais pour les sociétés.
 */
class BrowseCompaniesSelectionTest extends TestCase
{
    use RefreshDatabase;

    private function providerCompany(): OrganizationAccount
    {
        return OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value,
            'rating_avg' => 4.7,
            'rating_count' => 9,
            'name' => 'Alpha Clean',
        ]);
    }

    private function premiumProfileClient(): User
    {
        $client = User::factory()->client()->create();
        CustomerProfile::query()->create([
            'user_id' => $client->id,
            'plan_type' => 'premium',
            'plan_status' => 'active',
        ]);

        return $client->fresh();
    }

    #[Test]
    public function browse_companies_in_selection_mode_dispatches_the_company_selected_event(): void
    {
        $org = $this->providerCompany();

        Livewire::test(BrowseCompanies::class, ['selectionMode' => true])
            ->call('selectCompany', $org->id)
            ->assertDispatched('companySelected', organizationId: $org->id);
    }

    #[Test]
    public function browse_companies_without_selection_mode_does_not_dispatch_the_event(): void
    {
        $org = $this->providerCompany();

        Livewire::test(BrowseCompanies::class)
            ->call('selectCompany', $org->id)
            ->assertNotDispatched('companySelected');
    }

    #[Test]
    public function selecting_a_company_via_event_sets_the_org_clears_the_worker_and_closes_the_picker(): void
    {
        $client = $this->premiumProfileClient();
        $org = $this->providerCompany();

        $this->actingAs($client);

        Livewire::test(PrendreRendezVous::class)
            ->set('preferredProviderUserId', 999)
            ->set('showProviderPicker', true)
            ->call('onCompanySelected', $org->id)
            ->assertSet('assignedProviderOrganizationId', $org->id)
            ->assertSet('preferredProviderUserId', null)
            ->assertSet('showProviderPicker', false);
    }
}
