<?php

namespace Tests\Feature\Relations;

use App\Enums\OrganizationType;
use App\Livewire\Client\BrowseCompanies;
use App\Models\OrganizationAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BrowseCompaniesFilterTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_query_filters_companies_by_name_and_sort_orders_by_rating(): void
    {
        OrganizationAccount::factory()->create(['type' => OrganizationType::PROVIDER_COMPANY->value, 'name' => 'Alpha Clean', 'rating_avg' => 4.2]);
        OrganizationAccount::factory()->create(['type' => OrganizationType::PROVIDER_COMPANY->value, 'name' => 'Beta Services', 'rating_avg' => 4.9]);

        // Sans contexte zone → fallback simple (toutes les PROVIDER_COMPANY notées).
        Livewire::test(BrowseCompanies::class)
            ->set('query', 'alpha')
            ->assertSee('Alpha Clean')
            ->assertDontSee('Beta Services');

        Livewire::test(BrowseCompanies::class)
            ->set('sort', 'rating')
            ->assertSeeInOrder(['Beta Services', 'Alpha Clean']); // 4.9 avant 4.2
    }

    #[Test]
    public function test_selection_event_still_dispatched(): void
    {
        $org = OrganizationAccount::factory()->create(['type' => OrganizationType::PROVIDER_COMPANY->value, 'rating_avg' => 4.0]);
        Livewire::test(BrowseCompanies::class, ['selectionMode' => true])
            ->call('selectCompany', $org->id)
            ->assertDispatched('companySelected', organizationId: $org->id);
    }

    #[Test]
    public function test_reset_filters_clears_query(): void
    {
        Livewire::test(BrowseCompanies::class)
            ->set('query', 'xyz')
            ->call('resetFilters')
            ->assertSet('query', '');
    }
}
