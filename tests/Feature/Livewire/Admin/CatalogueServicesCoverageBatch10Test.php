<?php

namespace Tests\Feature\Livewire\Admin;

use App\Livewire\Admin\CatalogueServices;
use App\Models\ServiceCatalog;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\User;
use App\Models\ZoneServiceRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CatalogueServicesCoverageBatch10Test extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create([
            'permissions' => ['manage-services', 'perform-critical-admin-actions'],
            'access_scope' => User::ACCESS_SCOPE_ALL,
            'is_active' => true,
        ]);
    }

    public function test_mount_renders_with_expected_view_data(): void
    {
        ServiceCatalog::factory()->create();

        Livewire::actingAs($this->admin)
            ->test(CatalogueServices::class)
            ->assertOk()
            ->assertViewHas('services')
            ->assertViewHas('zones')
            ->assertViewHas('serviceTypes')
            ->assertViewHas('trades')
            ->assertViewHas('serviceLogs')
            ->assertViewHas('servicesGroupedByTrade')
            ->assertSet('serviceId', null)
            ->assertSet('service_type', 'standard');
    }

    public function test_updated_name_autofills_slug_and_code_when_creating(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CatalogueServices::class)
            ->set('name', 'My Great Service')
            ->assertSet('slug', 'my-great-service')
            ->assertSet('code', 'MY_GREAT_SERVICE');
    }

    public function test_updated_name_does_not_overwrite_when_editing(): void
    {
        $service = ServiceCatalog::factory()->create([
            'slug' => 'existing-slug',
            'code' => 'EXISTING',
        ]);

        Livewire::actingAs($this->admin)
            ->test(CatalogueServices::class)
            ->call('editService', $service->id)
            ->set('name', 'A Totally Different Name')
            ->assertSet('slug', 'existing-slug')
            ->assertSet('code', 'EXISTING');
    }

    public function test_save_service_creates_a_new_service(): void
    {
        $trade = Trade::factory()->create();

        Livewire::actingAs($this->admin)
            ->test(CatalogueServices::class)
            ->set('name', 'Brand New Service')
            ->set('code', 'NEWCODE')
            ->set('slug', 'brand-new-service')
            ->set('description', 'desc')
            ->set('service_type', 'cleaning')
            ->set('default_duration_minutes', 90)
            ->set('base_price', 120)
            ->set('sort_order', 5)
            ->set('trade_id', $trade->id)
            ->call('saveService')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('service_catalogs', [
            'code' => 'NEWCODE',
            'slug' => 'brand-new-service',
            'service_type' => 'cleaning',
            'trade_id' => $trade->id,
        ]);
    }

    public function test_save_service_updates_existing_service(): void
    {
        $service = ServiceCatalog::factory()->create([
            'name' => 'Old Name',
            'base_price' => 50,
        ]);

        Livewire::actingAs($this->admin)
            ->test(CatalogueServices::class)
            ->call('editService', $service->id)
            ->set('name', 'Updated Name')
            ->set('base_price', 222)
            ->call('saveService')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('service_catalogs', [
            'id' => $service->id,
            'name' => 'Updated Name',
            'base_price' => 222,
        ]);
    }

    public function test_save_service_rejects_duplicate_code_and_slug(): void
    {
        ServiceCatalog::factory()->create([
            'code' => 'DUPCODE',
            'slug' => 'dup-slug',
        ]);

        Livewire::actingAs($this->admin)
            ->test(CatalogueServices::class)
            ->set('name', 'Some Name')
            ->set('code', 'DUPCODE')
            ->set('slug', 'dup-slug')
            ->set('base_price', 10)
            ->set('default_duration_minutes', 60)
            ->call('saveService')
            ->assertHasErrors(['code', 'slug']);
    }

    public function test_save_service_validates_required_fields(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CatalogueServices::class)
            ->set('code', '')
            ->set('name', '')
            ->set('slug', '')
            ->set('default_duration_minutes', 5)
            ->set('base_price', -1)
            ->call('saveService')
            ->assertHasErrors(['code', 'name', 'slug', 'default_duration_minutes', 'base_price']);
    }

    public function test_select_service_loads_form_and_options(): void
    {
        $service = ServiceCatalog::factory()->create([
            'description' => null,
            'is_entreprise' => true,
        ]);

        Livewire::actingAs($this->admin)
            ->test(CatalogueServices::class)
            ->call('selectService', $service->id)
            ->assertSet('selectedServiceId', $service->id)
            ->assertSet('serviceId', $service->id)
            ->assertSet('description', '')
            ->assertSet('is_entreprise', true);
    }

    public function test_toggle_active_flips_state(): void
    {
        $service = ServiceCatalog::factory()->create(['is_active' => true]);

        Livewire::actingAs($this->admin)
            ->test(CatalogueServices::class)
            ->call('toggleActive', $service->id);

        $this->assertFalse((bool) $service->fresh()->is_active);
    }

    public function test_edit_zone_rule_loads_existing_rule(): void
    {
        $service = ServiceCatalog::factory()->create();
        $zone = ServiceZone::factory()->create();
        $rule = ZoneServiceRule::factory()->create([
            'service_catalog_id' => $service->id,
            'service_zone_id' => $zone->id,
            'is_enabled' => false,
            'price_multiplier' => 1.5,
            'minimum_notice_hours' => 12,
        ]);

        Livewire::actingAs($this->admin)
            ->test(CatalogueServices::class)
            ->call('selectService', $service->id)
            ->call('editZoneRule', $zone->id)
            ->assertSet('selectedZoneId', $zone->id)
            ->assertSet('rule_enabled', false)
            ->assertSet('rule_price_multiplier', 1.5)
            ->assertSet('rule_minimum_notice_hours', 12);

        $this->assertNotNull($rule->fresh());
    }

    public function test_edit_zone_rule_returns_early_without_selected_service(): void
    {
        $zone = ServiceZone::factory()->create();

        Livewire::actingAs($this->admin)
            ->test(CatalogueServices::class)
            ->call('editZoneRule', $zone->id)
            ->assertSet('selectedZoneId', null);
    }

    public function test_save_zone_rule_persists_rule(): void
    {
        $service = ServiceCatalog::factory()->create();
        $zone = ServiceZone::factory()->create();

        Livewire::actingAs($this->admin)
            ->test(CatalogueServices::class)
            ->call('selectService', $service->id)
            ->set('selectedZoneId', $zone->id)
            ->set('rule_enabled', true)
            ->set('rule_price_multiplier', 1.25)
            ->set('rule_minimum_notice_hours', 24)
            ->set('rule_maximum_daily_capacity', 8)
            ->call('saveZoneRule')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('zone_service_rules', [
            'service_catalog_id' => $service->id,
            'service_zone_id' => $zone->id,
            'minimum_notice_hours' => 24,
            'maximum_daily_capacity' => 8,
        ]);
    }

    public function test_save_zone_rule_validates_multiplier_bounds(): void
    {
        $service = ServiceCatalog::factory()->create();
        $zone = ServiceZone::factory()->create();

        Livewire::actingAs($this->admin)
            ->test(CatalogueServices::class)
            ->call('selectService', $service->id)
            ->set('selectedZoneId', $zone->id)
            ->set('rule_price_multiplier', 99)
            ->call('saveZoneRule')
            ->assertHasErrors(['rule_price_multiplier']);
    }

    public function test_render_applies_search_status_market_type_and_trade_filters(): void
    {
        $trade = Trade::factory()->create();

        ServiceCatalog::factory()->create([
            'name' => 'Findable Cleaning',
            'code' => 'FINDME',
            'slug' => 'findable-cleaning',
            'is_active' => true,
            'is_entreprise' => true,
            'service_type' => 'cleaning',
            'trade_id' => $trade->id,
        ]);
        ServiceCatalog::factory()->create([
            'name' => 'Hidden Other',
            'code' => 'HIDE',
            'slug' => 'hidden-other',
            'is_active' => false,
            'is_entreprise' => false,
            'service_type' => 'office',
        ]);

        Livewire::actingAs($this->admin)
            ->test(CatalogueServices::class)
            ->set('search', 'Findable')
            ->set('status', 'active')
            ->set('market', 'entreprise')
            ->set('serviceType', 'cleaning')
            ->set('tradeFilter', (string) $trade->id)
            ->assertOk()
            ->assertSee('Findable Cleaning')
            ->assertDontSee('Hidden Other');
    }

    public function test_render_standard_market_filter(): void
    {
        ServiceCatalog::factory()->create([
            'name' => 'Standard Market Service',
            'is_entreprise' => false,
        ]);

        Livewire::actingAs($this->admin)
            ->test(CatalogueServices::class)
            ->set('market', 'standard')
            ->assertOk()
            ->assertSee('Standard Market Service');
    }

    public function test_render_group_by_trade_view(): void
    {
        $trade = Trade::factory()->create(['is_active' => true]);
        ServiceCatalog::factory()->create([
            'trade_id' => $trade->id,
            'name' => 'Grouped Service',
        ]);

        Livewire::actingAs($this->admin)
            ->test(CatalogueServices::class)
            ->set('groupByTrade', true)
            ->assertOk()
            ->assertViewHas('servicesGroupedByTrade');
    }

    public function test_updating_filters_trigger_lifecycle_hooks(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CatalogueServices::class)
            ->set('search', 'abc')
            ->set('status', 'active')
            ->set('market', 'entreprise')
            ->set('serviceType', 'cleaning')
            ->set('tradeFilter', '1')
            ->assertOk()
            ->assertSet('search', 'abc')
            ->assertSet('tradeFilter', '1');
    }
}
