<?php

namespace Tests\Feature;

use App\Models\ServiceCatalog;
use App\Models\Trade;
use App\Models\TradeZoneSetting;
use App\Models\ZoneServiceRule;
use App\Support\Livewire\Concerns\InteractsWithBookingFormState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesZoneAwareFixtures;
use Tests\TestCase;

/**
 * Stub minimal qui n'expose que ce dont la propriété getServicesGroupedByTradeProperty
 * a besoin : $resolvedServiceZoneId. Tout le reste du trait n'est pas sollicité.
 */
class FakeBookingFormHolder
{
    use InteractsWithBookingFormState;

    public ?int $resolvedServiceZoneId = null;

    public function __construct(?int $zoneId)
    {
        $this->resolvedServiceZoneId = $zoneId;
    }
}

class BookingServicesFilteredByTradeZoneTest extends TestCase
{
    use CreatesZoneAwareFixtures;
    use RefreshDatabase;

    public function test_service_of_disabled_trade_is_hidden_in_grouped_dropdown(): void
    {
        $context = $this->createCoverageContext();

        $peinture = Trade::create([
            'slug' => 'peinture', 'code' => 'PAINT', 'name' => 'Peinture',
            'is_active' => true, 'sort_order' => 20,
        ]);
        $jardinage = Trade::create([
            'slug' => 'jardinage', 'code' => 'GARDEN', 'name' => 'Jardinage',
            'is_active' => true, 'sort_order' => 30,
        ]);

        $paintService = ServiceCatalog::factory()->create([
            'trade_id'  => $peinture->id,
            'name'      => 'Peinture intérieure',
            'slug'      => 'peinture-interieure-test',
            'code'      => 'PAINT_INT',
            'is_active' => true,
        ]);
        $gardenService = ServiceCatalog::factory()->create([
            'trade_id'  => $jardinage->id,
            'name'      => 'Tonte pelouse',
            'slug'      => 'tonte-pelouse-test',
            'code'      => 'GARDEN_LAWN',
            'is_active' => true,
        ]);

        // Les deux services sont activés dans la zone par défaut
        foreach ([$paintService, $gardenService] as $service) {
            ZoneServiceRule::create([
                'service_zone_id'    => $context['zone']->id,
                'service_catalog_id' => $service->id,
                'is_enabled'         => true,
            ]);
        }

        // On désactive explicitement le métier Peinture dans la zone
        TradeZoneSetting::create([
            'trade_id'         => $peinture->id,
            'service_zone_id'  => $context['zone']->id,
            'is_active'        => false,
            'price_multiplier' => 1.00,
        ]);

        $holder = new FakeBookingFormHolder($context['zone']->id);
        $grouped = $holder->getServicesGroupedByTradeProperty();
        $flat = $holder->getServicesProperty();

        $allLabels = collect($grouped)->flatMap(fn ($g) => array_values($g));
        $this->assertFalse($allLabels->contains('Peinture intérieure'),
            'Le service Peinture doit être masqué quand son métier est désactivé dans la zone.');
        $this->assertTrue($allLabels->contains('Tonte pelouse'),
            'Le service Jardinage doit rester visible.');

        $this->assertArrayNotHasKey('PAINT_INT', $flat);
        $this->assertArrayHasKey('GARDEN_LAWN', $flat);
    }

    public function test_service_remains_visible_when_no_trade_zone_setting_exists(): void
    {
        $context = $this->createCoverageContext();

        $trade = Trade::create([
            'slug' => 'peinture', 'code' => 'PAINT', 'name' => 'Peinture',
            'is_active' => true, 'sort_order' => 20,
        ]);

        $service = ServiceCatalog::factory()->create([
            'trade_id'  => $trade->id,
            'name'      => 'Peinture facade',
            'slug'      => 'peinture-facade-test',
            'code'      => 'PAINT_FACADE',
            'is_active' => true,
        ]);

        ZoneServiceRule::create([
            'service_zone_id'    => $context['zone']->id,
            'service_catalog_id' => $service->id,
            'is_enabled'         => true,
        ]);

        $holder = new FakeBookingFormHolder($context['zone']->id);
        $flat = $holder->getServicesProperty();

        $this->assertArrayHasKey('PAINT_FACADE', $flat,
            'Sans TradeZoneSetting, le métier est implicitement actif (back-compat).');
    }
}
