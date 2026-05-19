<?php

namespace Tests\Feature;

use App\Livewire\Admin\Trades as AdminTradesComponent;
use App\Models\ServiceCatalog;
use App\Models\Trade;
use App\Models\User;
use Database\Seeders\TradeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 1 — Tests Livewire admin pour la gestion des Trades.
 *
 * Calque sur le pattern de AdminGestionZonesTest:
 *   User::factory()->admin()->create([...permissions, access_scope, is_active])
 *   $this->actingAs($admin)
 *   Livewire::test(Component::class)->call(...)
 */
class AdminTradesCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function createAdmin(): User
    {
        return User::factory()->admin()->create([
            'permissions'  => ['manage-services', 'perform-critical-admin-actions'],
            'access_scope' => User::ACCESS_SCOPE_ALL,
            'is_active'    => true,
        ]);
    }

    public function test_admin_can_list_seeded_trades(): void
    {
        $this->seed(TradeSeeder::class);
        $admin = $this->createAdmin();

        $this->actingAs($admin);

        Livewire::test(AdminTradesComponent::class)
            ->assertSee('Nettoyage')
            ->assertSee('Bâtiment')
            ->assertSee('Peinture')
            ->assertSee('Levage');
    }

    public function test_admin_can_create_a_new_trade(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        Livewire::test(AdminTradesComponent::class)
            ->call('openCreate')
            ->set('name', 'Plomberie')
            ->set('slug', 'plomberie')
            ->set('code', 'PLUMBING')
            ->set('color', '#3B82F6')
            ->set('is_active', true)
            ->set('sort_order', 60)
            ->call('save');

        $this->assertDatabaseHas('trades', [
            'slug' => 'plomberie',
            'code' => 'PLUMBING',
            'name' => 'Plomberie',
        ]);
    }

    public function test_creating_a_trade_with_duplicate_slug_fails(): void
    {
        Trade::create([
            'slug' => 'jardinage', 'code' => 'GARDEN', 'name' => 'Jardinage existant',
            'is_active' => true, 'sort_order' => 10,
        ]);

        $admin = $this->createAdmin();
        $this->actingAs($admin);

        Livewire::test(AdminTradesComponent::class)
            ->call('openCreate')
            ->set('name', 'Jardinage 2')
            ->set('slug', 'jardinage')   // duplicate
            ->set('code', 'GARDEN_2')
            ->call('save')
            ->assertHasErrors(['slug']);

        $this->assertSame(1, Trade::where('slug', 'jardinage')->count());
    }

    public function test_admin_can_toggle_active_state(): void
    {
        $trade = Trade::create([
            'slug' => 'electricite', 'code' => 'ELEC', 'name' => 'Électricité',
            'is_active' => true, 'sort_order' => 100,
        ]);

        $admin = $this->createAdmin();
        $this->actingAs($admin);

        Livewire::test(AdminTradesComponent::class)
            ->call('toggleActive', $trade->id);

        $trade->refresh();
        $this->assertFalse($trade->is_active);

        Livewire::test(AdminTradesComponent::class)
            ->call('toggleActive', $trade->id);

        $trade->refresh();
        $this->assertTrue($trade->is_active);
    }

    public function test_admin_can_reorder_trades(): void
    {
        $a = Trade::create(['slug' => 'a', 'code' => 'A', 'name' => 'A', 'sort_order' => 10, 'is_active' => true]);
        $b = Trade::create(['slug' => 'b', 'code' => 'B', 'name' => 'B', 'sort_order' => 20, 'is_active' => true]);

        $admin = $this->createAdmin();
        $this->actingAs($admin);

        Livewire::test(AdminTradesComponent::class)
            ->call('moveUp', $b->id);

        $a->refresh();
        $b->refresh();

        $this->assertSame(20, $a->sort_order);
        $this->assertSame(10, $b->sort_order);
    }

    public function test_admin_can_save_business_properties_on_a_trade(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        Livewire::test(AdminTradesComponent::class)
            ->call('openCreate')
            ->set('name', 'Serrurerie')
            ->set('slug', 'serrurerie')
            ->set('code', 'LOCKSMITH')
            ->set('default_hourly_rate', '85.00')
            ->set('emergency_multiplier', '3.00')
            ->set('night_multiplier', '2.00')
            ->set('weekend_multiplier', '1.50')
            ->set('quote_validity_days', '30')
            ->set('requires_quote_by_default', false)
            ->set('sla_response_minutes', '60')
            ->call('save');

        $trade = Trade::where('slug', 'serrurerie')->first();
        $this->assertNotNull($trade);
        $this->assertSame('85.00', (string) $trade->default_hourly_rate);
        $this->assertSame('3.00', (string) $trade->emergency_multiplier);
        $this->assertSame('2.00', (string) $trade->night_multiplier);
        $this->assertSame('1.50', (string) $trade->weekend_multiplier);
        $this->assertSame(30, $trade->quote_validity_days);
        $this->assertSame(60, $trade->sla_response_minutes);
        $this->assertFalse($trade->requires_quote_by_default);
    }

    public function test_multiplier_out_of_range_is_rejected_on_trade(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        Livewire::test(AdminTradesComponent::class)
            ->call('openCreate')
            ->set('name', 'Test')
            ->set('slug', 'test-mult')
            ->set('code', 'TEST_MULT')
            ->set('emergency_multiplier', '0.5')  // < 1 → rejet
            ->set('night_multiplier', '15')        // > 10 → rejet
            ->call('save')
            ->assertHasErrors(['emergency_multiplier', 'night_multiplier']);

        $this->assertNull(Trade::where('slug', 'test-mult')->first());
    }

    public function test_edit_roundtrip_preserves_business_properties(): void
    {
        $trade = Trade::create([
            'slug'                  => 'electricite',
            'code'                  => 'ELEC',
            'name'                  => 'Électricité',
            'is_active'             => true,
            'sort_order'            => 60,
            'default_hourly_rate'   => 70.00,
            'emergency_multiplier'  => 2.50,
            'night_multiplier'      => 1.80,
            'weekend_multiplier'    => 1.20,
            'quote_validity_days'   => 45,
            'sla_response_minutes'  => 120,
            'requires_quote_by_default' => true,
        ]);

        $admin = $this->createAdmin();
        $this->actingAs($admin);

        Livewire::test(AdminTradesComponent::class)
            ->call('edit', $trade->id)
            ->assertSet('default_hourly_rate', '70.00')
            ->assertSet('emergency_multiplier', '2.50')
            ->assertSet('night_multiplier', '1.80')
            ->assertSet('weekend_multiplier', '1.20')
            ->assertSet('quote_validity_days', '45')
            ->assertSet('sla_response_minutes', '120')
            ->assertSet('requires_quote_by_default', true);
    }

    public function test_admin_can_save_a_booking_form_schema_on_a_trade(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        $schemaJson = json_encode([
            'version' => 1,
            'fields' => [
                ['key' => 'nb_enfants', 'label' => 'Nombre d\'enfants', 'type' => 'number',
                 'required' => true, 'min' => 1, 'max' => 10,
                 'pricing' => ['modifier' => 'per_unit', 'value' => 5]],
                ['key' => 'urgence_24_7', 'label' => 'Intervention 24/7', 'type' => 'boolean',
                 'pricing' => ['modifier' => 'percent', 'value' => 100]],
            ],
        ]);

        Livewire::test(AdminTradesComponent::class)
            ->call('openCreate')
            ->set('name', 'Babysitting')
            ->set('slug', 'babysitting')
            ->set('code', 'BABY')
            ->set('booking_form_schema_json', $schemaJson)
            ->call('save');

        $trade = Trade::where('slug', 'babysitting')->first();
        $this->assertNotNull($trade);
        $this->assertIsArray($trade->booking_form_schema);
        $this->assertSame(1, $trade->booking_form_schema['version']);
        $this->assertCount(2, $trade->booking_form_schema['fields']);
        $this->assertSame('nb_enfants', $trade->booking_form_schema['fields'][0]['key']);
    }

    public function test_admin_invalid_booking_form_schema_json_is_rejected(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        Livewire::test(AdminTradesComponent::class)
            ->call('openCreate')
            ->set('name', 'Test JSON')
            ->set('slug', 'test-json')
            ->set('code', 'TEST_JSON')
            ->set('booking_form_schema_json', '{ this is not json }')
            ->call('save')
            ->assertHasErrors(['booking_form_schema_json']);

        $this->assertNull(Trade::where('slug', 'test-json')->first());
    }

    public function test_admin_invalid_schema_structure_is_rejected(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin);

        // JSON valide mais schema invalide (champ sans label)
        $badJson = json_encode([
            'version' => 1,
            'fields' => [
                ['key' => 'x', 'type' => 'number'], // pas de label
            ],
        ]);

        Livewire::test(AdminTradesComponent::class)
            ->call('openCreate')
            ->set('name', 'Bad Schema')
            ->set('slug', 'bad-schema')
            ->set('code', 'BAD_SCHEMA')
            ->set('booking_form_schema_json', $badJson)
            ->call('save')
            ->assertHasErrors(['booking_form_schema_json']);

        $this->assertNull(Trade::where('slug', 'bad-schema')->first());
    }

    public function test_empty_schema_json_clears_field(): void
    {
        $trade = Trade::create([
            'slug' => 'with-schema', 'code' => 'WS', 'name' => 'With Schema',
            'is_active' => true, 'sort_order' => 5,
            'booking_form_schema' => ['version' => 1, 'fields' => [
                ['key' => 'a', 'label' => 'A', 'type' => 'text', 'required' => false],
            ]],
        ]);

        $admin = $this->createAdmin();
        $this->actingAs($admin);

        Livewire::test(AdminTradesComponent::class)
            ->call('edit', $trade->id)
            ->set('booking_form_schema_json', '')
            ->call('save');

        $this->assertNull($trade->fresh()->booking_form_schema);
    }

    public function test_cannot_delete_trade_with_attached_services(): void
    {
        $trade = Trade::create([
            'slug' => 'demolition', 'code' => 'DEMO', 'name' => 'Démolition',
            'is_active' => true, 'sort_order' => 70,
        ]);
        // attach a service to the trade
        ServiceCatalog::create([
            'trade_id'  => $trade->id,
            'name'      => 'Démolition cloison',
            'slug'      => 'demolition-cloison',
            'code'      => 'DEMO_CLOISON',
            'is_active' => true,
            'base_price'=> 250,
            'currency'  => 'EUR',
            'default_duration_minutes' => 240,
        ]);

        $admin = $this->createAdmin();
        $this->actingAs($admin);

        Livewire::test(AdminTradesComponent::class)
            ->call('delete', $trade->id);

        $this->assertNotNull(Trade::find($trade->id), 'Trade should not be soft-deleted while it still has services.');
    }
}
