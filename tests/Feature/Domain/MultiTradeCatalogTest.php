<?php

namespace Tests\Feature\Domain;

use App\Models\ServiceCatalog;
use App\Models\ServiceOption;
use App\Models\Trade;
use Database\Seeders\ServiceCatalogTradeBackfillSeeder;
use Database\Seeders\TradeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 1 — Tests d'intégration domaine pour le multi-métiers.
 */
class MultiTradeCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_trade_seeder_creates_reference_trades(): void
    {
        $this->seed(TradeSeeder::class);

        $this->assertDatabaseHas('trades', ['slug' => 'nettoyage']);
        $this->assertDatabaseHas('trades', ['slug' => 'batiment']);
        $this->assertDatabaseHas('trades', ['slug' => 'peinture']);
        $this->assertDatabaseHas('trades', ['slug' => 'levage']);
        $this->assertDatabaseHas('trades', ['slug' => 'jardinage']);

        // Levage requires certification
        $this->assertTrue(Trade::where('slug', 'levage')->first()->requires_certification);
        // Levage not personal
        $this->assertFalse(Trade::where('slug', 'levage')->first()->is_personal_default);
    }

    public function test_backfill_seeder_attaches_legacy_services_to_cleaning_trade(): void
    {
        // Seed Trades only first
        $this->seed(TradeSeeder::class);

        // Create a "legacy" service WITHOUT trade_id (simulating pre-Phase-1 state)
        $legacy = ServiceCatalog::create([
            'name'      => 'Nettoyage bureaux legacy',
            'slug'      => 'cleaning-offices-legacy',
            'code'      => 'CLEAN_OFF_LEGACY',
            'is_active' => true,
            'base_price'=> 0,
            'currency'  => 'EUR',
            'default_duration_minutes' => 90,
        ]);

        // trade_id should be null at this stage
        $this->assertNull($legacy->fresh()->trade_id);

        // Run backfill
        $this->seed(ServiceCatalogTradeBackfillSeeder::class);

        $cleaning = Trade::where('slug', 'nettoyage')->first();
        $legacy->refresh();

        $this->assertSame($cleaning->id, $legacy->trade_id);
    }

    public function test_service_catalog_belongs_to_trade(): void
    {
        $trade = Trade::create([
            'slug' => 'painting-test', 'code' => 'PAINT_TEST', 'name' => 'Peinture test',
            'is_active' => true, 'sort_order' => 10,
        ]);

        $service = ServiceCatalog::create([
            'trade_id'  => $trade->id,
            'name'      => 'Peinture intérieure',
            'slug'      => 'peinture-interieure-test',
            'code'      => 'PAINT_INT_TEST',
            'is_active' => true,
            'base_price'=> 35,
            'currency'  => 'EUR',
            'default_duration_minutes' => 480,
            'billing_unit' => 'sqm',
        ]);

        $this->assertNotNull($service->trade);
        $this->assertSame($trade->id, $service->trade->id);
    }

    public function test_trade_has_many_active_services_and_count_is_accurate(): void
    {
        $trade = Trade::create([
            'slug' => 't-active-svc', 'code' => 'TASVC', 'name' => 'Trade test',
            'is_active' => true, 'sort_order' => 10,
        ]);

        ServiceCatalog::create([
            'trade_id'  => $trade->id, 'name' => 'Active 1', 'slug' => 'a1', 'code' => 'A1',
            'is_active' => true, 'base_price' => 0, 'currency' => 'EUR', 'default_duration_minutes' => 60,
        ]);
        ServiceCatalog::create([
            'trade_id'  => $trade->id, 'name' => 'Active 2', 'slug' => 'a2', 'code' => 'A2',
            'is_active' => true, 'base_price' => 0, 'currency' => 'EUR', 'default_duration_minutes' => 60,
        ]);
        ServiceCatalog::create([
            'trade_id'  => $trade->id, 'name' => 'Inactive 1', 'slug' => 'i1', 'code' => 'I1',
            'is_active' => false, 'base_price' => 0, 'currency' => 'EUR', 'default_duration_minutes' => 60,
        ]);

        $this->assertSame(3, $trade->services()->count());
        $this->assertSame(2, $trade->activeServices()->count());
    }

    public function test_service_option_belongs_to_service_and_cascades_on_delete(): void
    {
        $trade = Trade::create([
            'slug' => 't-opts', 'code' => 'TOPTS', 'name' => 'Trade with options',
            'is_active' => true, 'sort_order' => 10,
        ]);

        $service = ServiceCatalog::create([
            'trade_id'  => $trade->id,
            'name'      => 'Service avec options',
            'slug'      => 'svc-w-opts',
            'code'      => 'SVC_OPTS',
            'is_active' => true,
            'base_price'=> 80,
            'currency'  => 'EUR',
            'default_duration_minutes' => 120,
        ]);

        $opt = ServiceOption::create([
            'service_catalog_id' => $service->id,
            'slug'   => 'surface',
            'label'  => 'Surface (m²)',
            'type'   => 'number',
            'unit'   => 'm²',
            'is_required' => true,
            'price_modifier' => 'per_unit',
            'price_modifier_value' => 1.50,
            'min_value' => 10,
            'step'      => 5,
        ]);

        $this->assertSame($service->id, $opt->service->id);
        $this->assertSame(1, $service->options()->count());

        $service->delete();
        $this->assertSame(0, ServiceOption::where('service_catalog_id', $service->id)->count());
    }

    public function test_service_catalog_scope_for_trade_filters_correctly(): void
    {
        $a = Trade::create(['slug'=>'tA','code'=>'TA','name'=>'A','is_active'=>true,'sort_order'=>1]);
        $b = Trade::create(['slug'=>'tB','code'=>'TB','name'=>'B','is_active'=>true,'sort_order'=>2]);

        ServiceCatalog::create([
            'trade_id' => $a->id, 'name' => 'svcA', 'slug' => 'svca', 'code' => 'SVCA',
            'is_active' => true, 'base_price' => 0, 'currency' => 'EUR', 'default_duration_minutes' => 60,
        ]);
        ServiceCatalog::create([
            'trade_id' => $b->id, 'name' => 'svcB', 'slug' => 'svcb', 'code' => 'SVCB',
            'is_active' => true, 'base_price' => 0, 'currency' => 'EUR', 'default_duration_minutes' => 60,
        ]);

        $this->assertSame(1, ServiceCatalog::forTrade($a)->count());
        $this->assertSame(1, ServiceCatalog::forTrade($b->id)->count());
    }
}
