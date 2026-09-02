<?php

namespace Tests\Feature;

use App\Enums\ProviderType;
use App\Models\Booking;
use App\Models\ProviderProfile;
use App\Models\ServiceCatalog;
use App\Models\Trade;
use App\Models\User;
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
