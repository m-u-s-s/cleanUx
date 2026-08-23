<?php

namespace Tests\Feature;

use App\Enums\ProviderType;
use App\Models\Booking;
use App\Models\ProviderProfile;
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
        $user = User::factory()->employe()->create([
            'is_active' => true,
            'primary_service_zone_id' => $zoneId,
        ]);

        ProviderProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'provider_type' => ProviderType::INDEPENDENT->value,
                'status' => 'active',
                'verification_status' => 'verified',
            ],
        );

        return $user;
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
            'trade_id' => $serrurerie->id,
            'is_active' => true,
        ]);

        $painter = $this->makeEmployee($zone->id);
        $painter->trades()->sync([$peinture->id]);

        $locksmith = $this->makeEmployee($zone->id);
        $locksmith->trades()->sync([$serrurerie->id]);

        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);

        $rdv = Booking::factory()->create([
            'client_id' => $client->id,
            'service_zone_id' => $zone->id,
            'service_catalog_id' => $service->id,
            'date' => now()->addDay()->toDateString(),
            'heure' => '10:00',
            'duree_estimee' => 90,
            'status' => 'en_attente',
        ]);

        $ranking = app(AiDispatchService::class)->rankEmployees(
            $rdv->fresh(['client', 'serviceZone', 'serviceCatalog'])
        );

        $ids = $ranking->pluck('employee.id')->all();
        $this->assertContains($locksmith->id, $ids);
        $this->assertNotContains($painter->id, $ids,
            'Le peintre ne doit PAS être proposé pour une mission serrurier.');
    }

    /** LE REPLI OUVERT EST SUPPRIMÉ, et c'est le cœur de l'invariant. */
    public function test_aucun_candidat_quand_personne_n_exerce_le_metier(): void
    {
        $zone = ServiceZone::factory()->create();
        $serrurerie = $this->makeTrade('serrurerie');

        $service = ServiceCatalog::factory()->create([
            'trade_id' => $serrurerie->id,
            'is_active' => true,
        ]);

        // Aucun employé tagué serrurerie — phase de transition
        $employee = $this->makeEmployee($zone->id);

        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);

        $rdv = Booking::factory()->create([
            'client_id' => $client->id,
            'service_zone_id' => $zone->id,
            'service_catalog_id' => $service->id,
            'date' => now()->addDay()->toDateString(),
            'heure' => '10:00',
            'duree_estimee' => 90,
            'status' => 'en_attente',
        ]);

        $ranking = app(AiDispatchService::class)->rankEmployees(
            $rdv->fresh(['client', 'serviceZone', 'serviceCatalog'])
        );

        // PERSONNE. Pas « tout le monde », pas « au hasard ».
        $this->assertTrue($ranking->isEmpty());
        $this->assertNotContains($employee->id, $ranking->pluck('employee.id')->all());
    }

    /** SANS MÉTIER RÉSOLVABLE, ON NE REND PERSONNE. */
    public function test_aucun_candidat_quand_la_reservation_n_a_pas_de_metier(): void
    {
        $zone = ServiceZone::factory()->create();
        $peinture = $this->makeTrade('peinture');

        $service = ServiceCatalog::factory()->create([
            'trade_id' => null,           // service legacy sans trade
            'is_active' => true,
        ]);

        $painter = $this->makeEmployee($zone->id);
        $painter->trades()->sync([$peinture->id]);

        $generic = $this->makeEmployee($zone->id); // sans aucun trade

        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);

        $rdv = Booking::factory()->create([
            'client_id' => $client->id,
            'service_zone_id' => $zone->id,
            'service_catalog_id' => $service->id,
            'date' => now()->addDay()->toDateString(),
            'heure' => '10:00',
            'duree_estimee' => 90,
            'status' => 'en_attente',
        ]);

        $ranking = app(AiDispatchService::class)->rankEmployees(
            $rdv->fresh(['client', 'serviceZone', 'serviceCatalog'])
        );

        $this->assertTrue(
            $ranking->isEmpty(),
            'Sans métier résolvable, le dispatch ne cherche personne plutôt que n’importe qui.',
        );
        $this->assertNotContains($painter->id, $ranking->pluck('employee.id')->all());
        $this->assertNotContains($generic->id, $ranking->pluck('employee.id')->all());
    }

    /** LE MÉTIER SE LIT SUR SA COLONNE, avec le catalogue en REPLI. */
    public function test_le_metier_se_resout_par_la_colonne_puis_par_le_catalogue(): void
    {
        $trade = $this->makeTrade('peinture');
        $service = ServiceCatalog::factory()->create([
            'trade_id' => $trade->id,
            'is_active' => true,
        ]);

        // Archive : pas de colonne, un service au catalogue.
        $archive = Booking::factory()->create([
            'trade_id' => null,
            'service_catalog_id' => $service->id,
        ]);

        $this->assertSame($trade->id, $archive->fresh()->resolveTradeId());

        // Moteur de commande : la colonne, et elle prime.
        $autre = $this->makeTrade('serrurerie');
        $moderne = Booking::factory()->create([
            'trade_id' => $autre->id,
            'service_catalog_id' => $service->id,
        ]);

        $this->assertSame($autre->id, $moderne->fresh()->resolveTradeId());
        $this->assertSame($autre->id, $moderne->fresh()->trade->id);
    }
}
