<?php

namespace Tests\Feature\Bundles;

use App\Enums\ProviderType;
use App\Models\MultiTradeBundleItemQuote;
use App\Models\ProviderProfile;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\User;
use App\Services\Bundles\MultiTradeBundleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Bundle Marketplace — raffinement : quand le chantier a une zone, seuls les
 * prestataires couvrant cette zone (et habilités au métier + KYC) sont sollicités.
 */
class BundleZoneFilteringTest extends TestCase
{
    use RefreshDatabase;

    private function provider(Trade $trade, ?int $zoneId): User
    {
        $user = User::factory()->employe()->create([
            'is_active' => true,
            'primary_service_zone_id' => $zoneId,
        ]);
        ProviderProfile::factory()->create([
            'user_id' => $user->id,
            'provider_type' => ProviderType::INDEPENDENT->value,
            'status' => 'active',
            'verification_status' => 'verified',
        ]);
        $user->trades()->attach($trade->id, ['proficiency' => 'expert', 'is_primary' => true]);

        return $user->fresh();
    }

    public function test_only_providers_covering_the_bundle_zone_are_solicited(): void
    {
        Notification::fake();
        config(['bundles.quote_request_top_n' => 5]);

        $trade = Trade::factory()->create();
        $zone = ServiceZone::factory()->create();
        $otherZone = ServiceZone::factory()->create();
        $client = User::factory()->client()->create();

        $inZone = $this->provider($trade, $zone->id);
        $outOfZone = $this->provider($trade, $otherZone->id);

        $bundle = app(MultiTradeBundleService::class)->createDraft(
            client: $client,
            name: 'Chantier zoné',
            items: [
                ['trade_id' => $trade->id, 'label' => 'A', 'estimated_price_cents' => 1000],
                ['trade_id' => $trade->id, 'label' => 'B', 'estimated_price_cents' => 2000],
            ],
            serviceZoneId: $zone->id,
        );

        app(MultiTradeBundleService::class)->startQuoting($bundle);

        $solicited = MultiTradeBundleItemQuote::pluck('provider_user_id')->unique()->all();
        $this->assertContains($inZone->id, $solicited, 'Le prestataire de la zone doit être sollicité.');
        $this->assertNotContains($outOfZone->id, $solicited, 'Un prestataire hors zone ne doit pas être sollicité.');
    }

    public function test_without_zone_eligibility_is_unchanged(): void
    {
        Notification::fake();
        config(['bundles.quote_request_top_n' => 5]);

        $trade = Trade::factory()->create();
        $client = User::factory()->client()->create();
        $anyProvider = $this->provider($trade, ServiceZone::factory()->create()->id);

        // Pas de zone sur le bundle → comportement inchangé (métier + KYC seulement).
        $bundle = app(MultiTradeBundleService::class)->createDraft($client, 'Chantier', [
            ['trade_id' => $trade->id, 'label' => 'A', 'estimated_price_cents' => 1000],
            ['trade_id' => $trade->id, 'label' => 'B', 'estimated_price_cents' => 2000],
        ]);

        app(MultiTradeBundleService::class)->startQuoting($bundle);

        $this->assertContains($anyProvider->id, MultiTradeBundleItemQuote::pluck('provider_user_id')->unique()->all());
    }
}
