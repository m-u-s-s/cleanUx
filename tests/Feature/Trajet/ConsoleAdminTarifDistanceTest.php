<?php

namespace Tests\Feature\Trajet;

use App\Models\Country;
use App\Models\Question;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\TradeZonePricing;
use App\Models\User;
use App\Support\Domain\LocationRole;
use App\Support\Domain\QuestionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** LA CONSOLE MOBILE PEUT RÉGLER LE PRIX AU KILOMÈTRE — pas seulement le web. */
class ConsoleAdminTarifDistanceTest extends TestCase
{
    use RefreshDatabase;

    private ServiceZone $zone;

    protected function setUp(): void
    {
        parent::setUp();
        $this->zone = ServiceZone::factory()->create(['country_id' => Country::factory()->create()->id]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function metierDeCourse(): Trade
    {
        $trade = Trade::factory()->create(['taxi_rules' => true]);

        foreach ([LocationRole::PICKUP, LocationRole::DROPOFF] as $role) {
            Question::create([
                'trade_id' => $trade->id,
                'code' => $role,
                'label' => LocationRole::label($role),
                'type' => QuestionType::LOCATION,
                'location_role' => $role,
                'is_active' => true,
            ]);
        }

        return $trade->fresh();
    }

    private function ouvrir(Trade $trade): TradeZonePricing
    {
        return TradeZonePricing::create([
            'trade_id' => $trade->id,
            'service_zone_id' => $this->zone->id,
            'base_rate_cents' => 0,
            'surge_multiplier' => '1.00',
            'is_active' => true,
        ]);
    }

    public function test_la_liste_annonce_le_trajet_et_son_tarif(): void
    {
        $course = $this->metierDeCourse();
        $this->ouvrir($course)->update([
            'distance_pricing_enabled' => true,
            'pickup_fee_cents' => 250,
            'price_per_km_cents' => 140,
            'included_km' => 1,
        ]);

        $ligne = collect(
            $this->getJson("/api/admin/catalogue/zones/{$this->zone->id}/trades")->assertOk()->json('data')
        )->firstWhere('id', $course->id);

        $this->assertTrue($ligne['is_route_service']);
        $this->assertTrue($ligne['taxi_rules']);
        $this->assertTrue($ligne['distance_pricing_enabled']);
        $this->assertSame(140, $ligne['price_per_km_cents']);
        $this->assertSame(1, $ligne['included_km']);
    }

    /** LE TÉMOIN : un métier ordinaire n'est pas annoncé comme trajet. */
    public function test_un_metier_ordinaire_n_est_pas_un_trajet(): void
    {
        $peinture = Trade::factory()->create();
        $this->ouvrir($peinture);

        $ligne = collect(
            $this->getJson("/api/admin/catalogue/zones/{$this->zone->id}/trades")->assertOk()->json('data')
        )->firstWhere('id', $peinture->id);

        $this->assertFalse($ligne['is_route_service']);
        $this->assertNull($ligne['price_per_km_cents']);
    }

    public function test_l_admin_regle_le_tarif_depuis_la_console(): void
    {
        $course = $this->metierDeCourse();
        $this->ouvrir($course);

        $this->postJson("/api/admin/catalogue/zones/{$this->zone->id}/trades/{$course->id}/distance-pricing", [
            'distance_pricing_enabled' => true,
            'pickup_fee_cents' => 300,
            'price_per_km_cents' => 150,
            'price_per_minute_cents' => 35,
            'included_km' => 2,
        ])->assertOk()->assertJsonPath('data.price_per_km_cents', 150);

        $this->assertDatabaseHas('trade_zone_pricing', [
            'trade_id' => $course->id,
            'service_zone_id' => $this->zone->id,
            'distance_pricing_enabled' => true,
            'pickup_fee_cents' => 300,
            'included_km' => 2,
        ]);
    }

    /** « Aucun tarif au kilomètre » et « zéro centime le kilomètre » ne sont pas la même chose : le premier laisse le forfait décider, le second facturerait la distance gratuitement. */
    public function test_un_champ_vide_efface_le_tarif_sans_le_mettre_a_zero(): void
    {
        $course = $this->metierDeCourse();
        $this->ouvrir($course)->update(['price_per_km_cents' => 140]);

        $this->postJson("/api/admin/catalogue/zones/{$this->zone->id}/trades/{$course->id}/distance-pricing", [
            'distance_pricing_enabled' => false,
            'price_per_km_cents' => null,
        ])->assertOk()->assertJsonPath('data.price_per_km_cents', null);

        $this->assertNull(
            TradeZonePricing::where('trade_id', $course->id)->first()->getRawOriginal('price_per_km_cents')
        );
    }

    public function test_regler_un_tarif_sur_un_metier_ferme_est_refuse(): void
    {
        $course = $this->metierDeCourse();
        // Aucune ligne (métier, zone) : l'absence vaut FERMÉ.

        $this->postJson("/api/admin/catalogue/zones/{$this->zone->id}/trades/{$course->id}/distance-pricing", [
            'distance_pricing_enabled' => true,
            'price_per_km_cents' => 150,
        ])->assertStatus(422)->assertJsonPath('error_code', 'trade_closed_in_zone');
    }
}
