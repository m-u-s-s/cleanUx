<?php

namespace Tests\Feature\Client;

use App\Livewire\Client\ProfilClient;
use App\Models\Booking;
use App\Models\OrganizationAccount;
use App\Models\OrganizationSite;
use App\Models\PostalCode;
use App\Models\ServiceCatalog;
use App\Models\ServiceZone;
use App\Models\User;
use App\Models\ZoneServiceRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Coverage for the client profile Livewire component.
 *
 * Exercises mount/render, the computed stats/address/preference props,
 * and the enterprise-only multi-site CRUD wire actions (create / edit /
 * save / delete) together with their guard branches.
 */
class ProfilClientComponentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build a CLIENT_COMPANY org + an entreprise user attached to it.
     * The org type drives User::isClientCompany() → isEntreprise true.
     *
     * @return array{0: OrganizationAccount, 1: User}
     */
    private function makeEntreprise(): array
    {
        $org = OrganizationAccount::factory()->clientCompany()->create();

        $user = User::factory()->entreprise()->create([
            'organization_account_id' => $org->id,
        ]);

        return [$org, $user];
    }

    #[Test]
    public function personal_client_renders_with_stats_and_no_enterprise_sites(): void
    {
        $client = User::factory()->client()->create();

        // 3 bookings: one terminé, one urgente, one ordinary with an address.
        Booking::factory()->termine()->create([
            'client_id' => $client->id,
            'priorite' => 'normale',
        ]);
        Booking::factory()->create([
            'client_id' => $client->id,
            'priorite' => 'urgente',
            'adresse' => 'Rue de la Loi 16',
            'ville' => 'Bruxelles',
        ]);
        Booking::factory()->create([
            'client_id' => $client->id,
            'priorite' => 'normale',
        ]);

        Livewire::actingAs($client)
            ->test(ProfilClient::class)
            ->assertOk()
            ->assertViewHas('isEntreprise', false)
            ->assertViewHas('stats', function (array $stats) {
                return $stats['total'] === 3
                    && $stats['termine'] === 1
                    && $stats['urgentes'] === 1;
            })
            ->assertViewHas('sites', fn ($sites) => $sites->isEmpty());
    }

    #[Test]
    public function create_site_form_can_be_opened_and_cancelled(): void
    {
        [, $user] = $this->makeEntreprise();

        Livewire::actingAs($user)
            ->test(ProfilClient::class)
            ->assertViewHas('isEntreprise', true)
            ->call('createSite')
            ->assertSet('editingSiteId', 0)
            ->set('site_name', 'Draft')
            ->call('cancelSiteForm')
            ->assertSet('editingSiteId', null)
            ->assertSet('site_name', '')
            ->assertSet('is_active', true);
    }

    #[Test]
    public function save_site_is_a_noop_for_a_personal_client(): void
    {
        $client = User::factory()->client()->create();

        Livewire::actingAs($client)
            ->test(ProfilClient::class)
            ->set('site_name', 'Should not persist')
            ->set('site_address_line_1', 'Nowhere 1')
            ->set('site_city', 'Ghost Town')
            ->set('site_postal_code', '0000')
            ->call('saveSite')
            ->assertNotDispatched('toast');

        $this->assertSame(0, OrganizationSite::count());
    }

    #[Test]
    public function save_site_validates_required_fields(): void
    {
        [, $user] = $this->makeEntreprise();

        Livewire::actingAs($user)
            ->test(ProfilClient::class)
            ->call('createSite')
            ->call('saveSite')
            ->assertHasErrors([
                'site_name' => 'required',
                'site_address_line_1' => 'required',
                'site_city' => 'required',
                'site_postal_code' => 'required',
            ]);

        $this->assertSame(0, OrganizationSite::count());
    }

    #[Test]
    public function enterprise_can_create_a_site_and_zone_is_resolved(): void
    {
        [$org, $user] = $this->makeEntreprise();

        // A resolvable postal code + a national active zone so the
        // resolveZoneForPostalReference() fallback branch is exercised.
        $postal = PostalCode::factory()->create(['code' => '1000']);
        ServiceZone::factory()->create([
            'coverage_type' => 'national',
            'status' => 'active',
            'is_visible' => true,
            'priority' => 900,
        ]);

        Livewire::actingAs($user)
            ->test(ProfilClient::class)
            ->call('createSite')
            ->set('site_name', 'HQ Brussels')
            ->set('site_code', 'HQ-1')
            ->set('contact_name', 'Jane Doe')
            ->set('site_email', 'hq@example.com')
            ->set('site_phone', '+3221234567')
            ->set('site_address_line_1', 'Rue de la Loi 16')
            ->set('site_city', 'Bruxelles')
            ->set('site_postal_code', '1000')
            ->set('access_instructions', 'Ring twice')
            ->set('is_primary', true)
            ->call('saveSite')
            ->assertHasNoErrors()
            ->assertDispatched('toast')
            ->assertSet('editingSiteId', null);

        // Note: site_code / is_primary are not in OrganizationSite::$fillable,
        // so the component's mass-assignment save persists only the fillable
        // columns (name, address, city, contact_name, …).
        $this->assertDatabaseHas('organization_sites', [
            'organization_account_id' => $org->id,
            'name' => 'HQ Brussels',
            'city' => 'Bruxelles',
            'contact_name' => 'Jane Doe',
        ]);
    }

    #[Test]
    public function enterprise_can_edit_and_delete_an_existing_site(): void
    {
        [$org, $user] = $this->makeEntreprise();

        $site = OrganizationSite::factory()->create([
            'organization_account_id' => $org->id,
            'name' => 'Old Name',
            'city' => 'Antwerp',
            'postal_code' => '2000',
        ]);

        $component = Livewire::actingAs($user)
            ->test(ProfilClient::class)
            ->call('editSite', $site->id)
            ->assertSet('editingSiteId', $site->id)
            ->assertSet('site_name', 'Old Name')
            ->assertSet('site_city', 'Antwerp');

        // Editing an id that does not belong to the org is a silent no-op.
        $component->call('editSite', 999999)
            ->assertSet('editingSiteId', $site->id);

        $component->call('deleteSite', $site->id)
            ->assertDispatched('toast');

        $this->assertDatabaseMissing('organization_sites', ['id' => $site->id]);

        // Deleting an unknown id is a guarded no-op.
        $component->call('deleteSite', 424242);
    }

    #[Test]
    public function available_services_for_site_reflects_zone_rules(): void
    {
        [$org, $user] = $this->makeEntreprise();

        $zone = ServiceZone::factory()->create(['status' => 'active']);

        $siteWithZone = OrganizationSite::factory()->create([
            'organization_account_id' => $org->id,
            'service_zone_id' => $zone->id,
        ]);

        $siteWithoutZone = OrganizationSite::factory()->create([
            'organization_account_id' => $org->id,
            'service_zone_id' => null,
        ]);

        $catalog = ServiceCatalog::factory()->create([
            'is_active' => true,
            'name' => 'Office Deep Clean',
        ]);
        ZoneServiceRule::factory()->create([
            'service_zone_id' => $zone->id,
            'service_catalog_id' => $catalog->id,
            'is_enabled' => true,
            'price_multiplier' => 1.0,
        ]);

        $instance = Livewire::actingAs($user)
            ->test(ProfilClient::class)
            ->instance();

        $this->assertSame([], $instance->availableServicesForSite($siteWithoutZone->id));
        $this->assertSame([], $instance->availableServicesForSite(987654));
        $this->assertContains('Office Deep Clean', $instance->availableServicesForSite($siteWithZone->id));
    }
}
