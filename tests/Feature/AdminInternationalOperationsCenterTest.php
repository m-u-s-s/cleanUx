<?php

namespace Tests\Feature;

use App\Livewire\Admin\InternationalOperationsCenter;
use App\Models\Country;
use App\Models\ServiceCatalog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminInternationalOperationsCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function makeAdmin(): User
    {
        return User::factory()->admin()->create([
            'access_scope' => 'all',
            'permissions' => [],
            'is_active' => true,
        ]);
    }

    protected function makeCountry(): Country
    {
        return Country::create([
            'iso_code' => 'BE',
            'iso3_code' => 'BEL',
            'name' => 'Belgique',
            'official_name' => 'Royaume de Belgique',
            'default_locale' => 'fr_BE',
            'currency_code' => 'EUR',
            'phone_code' => '+32',
            'timezone' => 'Europe/Brussels',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_open_international_operations_center(): void
    {
        $admin = $this->makeAdmin();
        $this->makeCountry();

        $this->actingAs($admin)
            ->get(route('admin.international'))
            ->assertOk()
            ->assertSee('International exploitable')
            ->assertSee('Readiness');
    }

    public function test_admin_can_update_country_operational_billing_and_service_rules(): void
    {
        $admin = $this->makeAdmin();
        $country = $this->makeCountry();

        $service = ServiceCatalog::create([
            'code' => 'STANDARD',
            'name' => 'Nettoyage standard',
            'slug' => 'nettoyage-standard',
            'service_type' => 'nettoyage_standard',
            'is_active' => true,
            'requires_quote' => false,
            'requires_manual_validation' => false,
            'is_entreprise' => false,
            'default_duration_minutes' => 120,
            'base_price' => 100,
            'sort_order' => 1,
        ]);

        $this->actingAs($admin);

        Livewire::test(InternationalOperationsCenter::class)
            ->call('selectCountry', $country->id)
            ->set('booking_enabled', true)
            ->set('mission_enabled', true)
            ->set('billing_enabled', true)
            ->set('partner_network_enabled', false)
            ->set('readiness_stage', 'mission_enabled')
            ->set('currency_symbol', '€')
            ->set('default_tax_rate', 21)
            ->call('saveOperationalSetting')
            ->set('invoice_prefix', 'INVBE')
            ->set('quote_prefix', 'QBE')
            ->set('tax_label', 'TVA')
            ->set('payment_terms_days', 45)
            ->call('saveBillingProfile')
            ->set('catalog_ready', true)
            ->set('booking_ready', true)
            ->set('mission_ready', true)
            ->set('billing_ready', false)
            ->set('partner_network_ready', false)
            ->set('compliance_ready', true)
            ->set('support_ready', true)
            ->set('readiness_notes', 'Belgique prête pour le pilote.')
            ->call('saveReadiness')
            ->set('service_catalog_id', $service->id)
            ->set('service_is_enabled', true)
            ->set('service_requires_quote', false)
            ->set('service_requires_manual_validation', true)
            ->set('service_minimum_notice_hours', 36)
            ->set('service_sla_response_hours', 4)
            ->set('service_sla_resolution_hours', 24)
            ->set('service_pricing_multiplier', 1.15)
            ->call('saveServiceRule')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('country_operational_settings', [
            'country_id' => $country->id,
            'booking_enabled' => 1,
            'mission_enabled' => 1,
            'billing_enabled' => 1,
            'readiness_stage' => 'mission_enabled',
        ]);

        $this->assertDatabaseHas('country_billing_profiles', [
            'country_id' => $country->id,
            'invoice_prefix' => 'INVBE',
            'quote_prefix' => 'QBE',
            'payment_terms_days' => 45,
        ]);

        $this->assertDatabaseHas('market_launch_readiness', [
            'country_id' => $country->id,
            'catalog_ready' => 1,
            'mission_ready' => 1,
            'compliance_ready' => 1,
        ]);

        $this->assertDatabaseHas('country_service_catalog_rules', [
            'country_id' => $country->id,
            'service_catalog_id' => $service->id,
            'requires_manual_validation' => 1,
            'minimum_notice_hours' => 36,
        ]);
    }
}
