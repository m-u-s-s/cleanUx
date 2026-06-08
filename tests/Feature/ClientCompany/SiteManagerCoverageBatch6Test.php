<?php

namespace Tests\Feature\ClientCompany;

use App\Enums\OrganizationRole;
use App\Livewire\ClientCompany\SiteManager;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\OrganizationSite;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Coverage-focused exercise of App\Livewire\ClientCompany\SiteManager.
 *
 * Drives the mount guard, the create/edit/delete actions (success, guard and
 * cross-org paths), validation, search filtering and the computed properties.
 */
class SiteManagerCoverageBatch6Test extends TestCase
{
    use RefreshDatabase;

    /**
     * Create an OrganizationAccount and a user belonging to it with the given role.
     *
     * @return array{0: OrganizationAccount, 1: User, 2: OrganizationMember}
     */
    private function makeCompanyUser(string $role = OrganizationRole::OWNER->value): array
    {
        $org = OrganizationAccount::factory()->create();

        $user = User::factory()->entreprise()->create([
            'current_organization_id' => $org->id,
            'organization_account_id' => $org->id,
        ]);

        $member = OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => 'active',
            'permissions' => null,
            'invited_by' => null,
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        return [$org, $user, $member];
    }

    #[Test]
    public function non_company_user_is_forbidden_from_mounting(): void
    {
        $personalClient = User::factory()->client()->create([
            'current_organization_id' => null,
            'organization_account_id' => null,
        ]);

        Livewire::actingAs($personalClient)
            ->test(SiteManager::class)
            ->assertForbidden();
    }

    #[Test]
    public function owner_can_mount_and_render_empty_state(): void
    {
        [$org, $user] = $this->makeCompanyUser();

        Livewire::actingAs($user)
            ->test(SiteManager::class)
            ->assertOk()
            ->assertSet('showForm', false)
            ->assertSet('editingId', null);
    }

    #[Test]
    public function open_create_resets_form_and_opens_modal(): void
    {
        [$org, $user] = $this->makeCompanyUser();

        // Seed a verified+active provider so getProvidersProperty / view branch is exercised.
        $providerUser = User::factory()->employe()->create();
        ProviderProfile::factory()->create([
            'user_id' => $providerUser->id,
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        Livewire::actingAs($user)
            ->test(SiteManager::class)
            ->set('name', 'Stale')
            ->call('openCreate')
            ->assertSet('showForm', true)
            ->assertSet('editingId', null)
            ->assertSet('name', '')
            ->assertSet('country', 'BE')
            ->assertSet('cleaningFrequency', OrganizationSite::FREQ_ONE_TIME);
    }

    #[Test]
    public function save_site_creates_a_new_site(): void
    {
        [$org, $user] = $this->makeCompanyUser();

        Livewire::actingAs($user)
            ->test(SiteManager::class)
            ->set('name', 'Siège social')
            ->set('address', 'Rue de la Loi 16')
            ->set('city', 'Bruxelles')
            ->set('postalCode', '1000')
            ->set('country', 'BE')
            ->set('surfaceM2', 250)
            ->set('floorCount', 3)
            ->set('contactEmail', 'contact@example.com')
            ->set('cleaningFrequency', OrganizationSite::FREQ_WEEKLY)
            ->call('saveSite')
            ->assertHasNoErrors()
            ->assertSet('showForm', false);

        $this->assertDatabaseHas('organization_sites', [
            'organization_account_id' => $org->id,
            'name' => 'Siège social',
            'city' => 'Bruxelles',
            'postal_code' => '1000',
            'cleaning_frequency' => OrganizationSite::FREQ_WEEKLY,
            'status' => 'active',
        ]);
    }

    #[Test]
    public function save_site_validates_required_fields_and_email(): void
    {
        [$org, $user] = $this->makeCompanyUser();

        Livewire::actingAs($user)
            ->test(SiteManager::class)
            ->set('name', '')
            ->set('address', '')
            ->set('city', '')
            ->set('postalCode', '')
            ->set('country', 'BEL') // size:2 violation
            ->set('contactEmail', 'not-an-email')
            ->call('saveSite')
            ->assertHasErrors([
                'name' => 'required',
                'address' => 'required',
                'city' => 'required',
                'postalCode' => 'required',
                'country' => 'size',
                'contactEmail' => 'email',
            ]);
    }

    #[Test]
    public function open_edit_loads_site_into_form(): void
    {
        [$org, $user] = $this->makeCompanyUser();

        $site = OrganizationSite::factory()->create([
            'organization_account_id' => $org->id,
            'name' => 'Entrepôt Nord',
            'city' => 'Liège',
            'postal_code' => '4000',
            'cleaning_frequency' => OrganizationSite::FREQ_MONTHLY,
            'contact_name' => 'Jean Dupont',
        ]);

        Livewire::actingAs($user)
            ->test(SiteManager::class)
            ->call('openEdit', $site->id)
            ->assertSet('editingId', $site->id)
            ->assertSet('name', 'Entrepôt Nord')
            ->assertSet('city', 'Liège')
            ->assertSet('postalCode', '4000')
            ->assertSet('cleaningFrequency', OrganizationSite::FREQ_MONTHLY)
            ->assertSet('contactName', 'Jean Dupont')
            ->assertSet('showForm', true);
    }

    #[Test]
    public function save_site_updates_an_existing_site(): void
    {
        [$org, $user] = $this->makeCompanyUser();

        $site = OrganizationSite::factory()->create([
            'organization_account_id' => $org->id,
            'name' => 'Ancien nom',
            'city' => 'Namur',
            'postal_code' => '5000',
        ]);

        Livewire::actingAs($user)
            ->test(SiteManager::class)
            ->call('openEdit', $site->id)
            ->set('name', 'Nouveau nom')
            ->set('address', 'Avenue Neuve 1')
            ->set('city', 'Namur')
            ->set('postalCode', '5000')
            ->call('saveSite')
            ->assertHasNoErrors()
            ->assertSet('showForm', false);

        $this->assertDatabaseHas('organization_sites', [
            'id' => $site->id,
            'name' => 'Nouveau nom',
        ]);
    }

    #[Test]
    public function delete_site_archives_the_site(): void
    {
        [$org, $user] = $this->makeCompanyUser();

        $site = OrganizationSite::factory()->create([
            'organization_account_id' => $org->id,
            'status' => 'active',
        ]);

        Livewire::actingAs($user)
            ->test(SiteManager::class)
            ->call('deleteSite', $site->id);

        $this->assertDatabaseHas('organization_sites', [
            'id' => $site->id,
            'status' => 'archived',
        ]);
    }

    #[Test]
    public function search_query_filters_the_sites_list(): void
    {
        [$org, $user] = $this->makeCompanyUser();

        OrganizationSite::factory()->create([
            'organization_account_id' => $org->id,
            'name' => 'Bureau Alpha',
            'city' => 'Gand',
        ]);
        OrganizationSite::factory()->create([
            'organization_account_id' => $org->id,
            'name' => 'Bureau Beta',
            'city' => 'Anvers',
        ]);

        $component = Livewire::actingAs($user)
            ->test(SiteManager::class)
            ->set('searchQuery', 'Alpha');

        $sites = $component->instance()->sites;

        $this->assertCount(1, $sites);
        $this->assertSame('Bureau Alpha', $sites->first()->name);
    }

    #[Test]
    public function manager_without_delete_permission_is_forbidden(): void
    {
        // MANAGER has sites.view_all (can mount) but not sites.delete.
        [$org, $user] = $this->makeCompanyUser(OrganizationRole::MANAGER->value);

        $site = OrganizationSite::factory()->create([
            'organization_account_id' => $org->id,
            'status' => 'active',
        ]);

        Livewire::actingAs($user)
            ->test(SiteManager::class)
            ->call('deleteSite', $site->id)
            ->assertForbidden();

        $this->assertDatabaseHas('organization_sites', [
            'id' => $site->id,
            'status' => 'active',
        ]);
    }

    #[Test]
    public function open_edit_for_other_org_site_throws_not_found(): void
    {
        [$orgA, $userA] = $this->makeCompanyUser();
        [$orgB] = $this->makeCompanyUser();

        $siteB = OrganizationSite::factory()->create([
            'organization_account_id' => $orgB->id,
        ]);

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($userA)
            ->test(SiteManager::class)
            ->call('openEdit', $siteB->id);
    }
}
