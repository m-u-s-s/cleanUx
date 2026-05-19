<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ServiceCatalog;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\User;
use App\Services\Dispatch\AiDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DispatchTradeFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function makeEmployee(?int $zoneId = null): User
    {
        return User::factory()->create([
            'role'                    => User::ROLE_EMPLOYE,
            'is_active'               => true,
            'primary_service_zone_id' => $zoneId,
        ]);
    }

    protected function makeTrade(string $slug): Trade
    {
        return Trade::create([
            'slug' => $slug, 'code' => strtoupper($slug),
            'name' => ucfirst($slug),
            'is_active' => true, 'sort_order' => 10,
        ]);
    }

    public function test_employee_without_required_trade_is_excluded(): void
    {
        $zone = ServiceZone::factory()->create();
        $peinture = $this->makeTrade('peinture');
        $serrurerie = $this->makeTrade('serrurerie');

        $service = ServiceCatalog::factory()->create([
            'trade_id'  => $serrurerie->id,
            'is_active' => true,
        ]);

        $painter = $this->makeEmployee($zone->id);
        $painter->trades()->sync([$peinture->id]);

        $locksmith = $this->makeEmployee($zone->id);
        $locksmith->trades()->sync([$serrurerie->id]);

        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);

        $rdv = Booking::factory()->create([
            'client_id'           => $client->id,
            'service_zone_id'     => $zone->id,
            'service_catalog_id'  => $service->id,
            'date'                => now()->addDay()->toDateString(),
            'heure'               => '10:00',
            'duree_estimee'       => 90,
            'status'              => 'en_attente',
        ]);

        $ranking = app(AiDispatchService::class)->rankEmployees(
            $rdv->fresh(['client', 'serviceZone', 'serviceCatalog'])
        );

        $ids = $ranking->pluck('employee.id')->all();
        $this->assertContains($locksmith->id, $ids);
        $this->assertNotContains($painter->id, $ids,
            'Le peintre ne doit PAS être proposé pour une mission serrurier.');
    }

    public function test_fallback_returns_all_when_no_employee_has_required_trade(): void
    {
        $zone = ServiceZone::factory()->create();
        $serrurerie = $this->makeTrade('serrurerie');

        $service = ServiceCatalog::factory()->create([
            'trade_id'  => $serrurerie->id,
            'is_active' => true,
        ]);

        // Aucun employé tagué serrurerie — phase de transition
        $employee = $this->makeEmployee($zone->id);

        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);

        $rdv = Booking::factory()->create([
            'client_id'           => $client->id,
            'service_zone_id'     => $zone->id,
            'service_catalog_id'  => $service->id,
            'date'                => now()->addDay()->toDateString(),
            'heure'               => '10:00',
            'duree_estimee'       => 90,
            'status'              => 'en_attente',
        ]);

        $ranking = app(AiDispatchService::class)->rankEmployees(
            $rdv->fresh(['client', 'serviceZone', 'serviceCatalog'])
        );

        // Fallback ouvert : l'employé est tout de même proposé
        $this->assertNotEmpty($ranking);
        $this->assertSame($employee->id, $ranking->first()['employee']->id);
    }

    public function test_no_filtering_when_service_has_no_trade(): void
    {
        $zone = ServiceZone::factory()->create();
        $peinture = $this->makeTrade('peinture');

        $service = ServiceCatalog::factory()->create([
            'trade_id'  => null,           // service legacy sans trade
            'is_active' => true,
        ]);

        $painter = $this->makeEmployee($zone->id);
        $painter->trades()->sync([$peinture->id]);

        $generic = $this->makeEmployee($zone->id); // sans aucun trade

        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);

        $rdv = Booking::factory()->create([
            'client_id'           => $client->id,
            'service_zone_id'     => $zone->id,
            'service_catalog_id'  => $service->id,
            'date'                => now()->addDay()->toDateString(),
            'heure'               => '10:00',
            'duree_estimee'       => 90,
            'status'              => 'en_attente',
        ]);

        $ranking = app(AiDispatchService::class)->rankEmployees(
            $rdv->fresh(['client', 'serviceZone', 'serviceCatalog'])
        );

        $ids = $ranking->pluck('employee.id')->all();
        $this->assertContains($painter->id, $ids);
        $this->assertContains($generic->id, $ids,
            'Sans trade requis, tous les candidats restent éligibles.');
    }

    public function test_booking_trade_relation_resolves_via_service_catalog(): void
    {
        $trade = $this->makeTrade('peinture');
        $service = ServiceCatalog::factory()->create([
            'trade_id'  => $trade->id,
            'is_active' => true,
        ]);

        $rdv = Booking::factory()->create([
            'service_catalog_id' => $service->id,
        ]);

        $resolved = $rdv->fresh()->trade;
        $this->assertNotNull($resolved);
        $this->assertSame($trade->id, $resolved->id);
    }
}
