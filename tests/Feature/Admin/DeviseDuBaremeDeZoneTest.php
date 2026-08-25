<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\TradeZonePricingManager;
use App\Models\Country;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\TradeZonePricing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * LA DEVISE D'UN BAREME SUIT LA ZONE, PAS CELUI QUI LA REGARDE.
 *
 * L'ecran rendait tous les tarifs avec `deviseDuContexte()` — la devise du LECTEUR. Juste
 * pour une facture qu'on lui adresse ; faux pour un bareme qu'il configure ailleurs. Un
 * administrateur belge tarifant Casablanca lisait « 45,00 EUR » la ou le client paiera des
 * dirhams : ce n'est pas un defaut d'affichage, c'est un prix qu'on croit avoir pose.
 */
class DeviseDuBaremeDeZoneTest extends TestCase
{
    use RefreshDatabase;

    private function zoneAu(string $iso, ?string $devise): ServiceZone
    {
        $pays = Country::factory()->create(['iso_code' => $iso, 'currency_code' => $devise]);

        return ServiceZone::factory()->create(['country_id' => $pays->id]);
    }

    private function admin(): User
    {
        return User::factory()->admin()->create([
            'permissions' => ['manage-services', 'perform-critical-admin-actions'],
            'access_scope' => User::ACCESS_SCOPE_ALL,
            'is_active' => true,
        ]);
    }

    public function test_le_modele_tient_la_devise_de_sa_zone(): void
    {
        $this->assertSame('MAD', $this->zoneAu('MA', 'MAD')->deviseDeLaZone());
    }

    /**
     * TEMOIN — une zone SANS pays retombe sur la devise du marche, sans lever.
     *
     * `countries.currency_code` est NOT NULL, mais `service_zones.country_id` est nullable :
     * c'est le seul chemin ou la question « quelle devise ? » n'a pas de reponse posee.
     */
    public function test_une_zone_sans_pays_retombe_sur_la_devise_du_marche(): void
    {
        $orpheline = ServiceZone::factory()->create(['country_id' => null]);

        $this->assertSame(
            strtoupper((string) config('fx.base_currency', 'EUR')),
            $orpheline->deviseDeLaZone(),
        );
    }

    public function test_l_ecran_web_affiche_le_bareme_dans_la_devise_de_la_zone(): void
    {
        $trade = Trade::factory()->create();
        $marocaine = $this->zoneAu('MA', 'MAD');

        TradeZonePricing::factory()->create([
            'trade_id' => $trade->id,
            'service_zone_id' => $marocaine->id,
            'base_rate_cents' => 4500,
        ]);

        $this->actingAs($this->admin());

        $rendu = Livewire::test(TradeZonePricingManager::class, ['trade' => $trade])->html();

        $this->assertStringNotContainsString('€', $rendu);
        $this->assertMatchesRegularExpression('/MAD|DH/u', $rendu);
    }

    /**
     * TEMOIN POSITIF — une zone belge continue de s'afficher en euros.
     *
     * Sans lui, une methode qui rendrait « MAD » pour tout le monde passerait le test
     * precedent en mesurant une panne.
     */
    public function test_temoin_une_zone_belge_reste_en_euros(): void
    {
        $trade = Trade::factory()->create();
        $belge = $this->zoneAu('BE', 'EUR');

        TradeZonePricing::factory()->create([
            'trade_id' => $trade->id,
            'service_zone_id' => $belge->id,
            'base_rate_cents' => 4500,
        ]);

        $this->actingAs($this->admin());

        $rendu = Livewire::test(TradeZonePricingManager::class, ['trade' => $trade])->html();

        $this->assertStringNotContainsString('MAD', $rendu);
    }

    public function test_l_api_du_catalogue_remonte_la_devise_de_la_zone(): void
    {
        $marocaine = $this->zoneAu('MA', 'MAD');

        Sanctum::actingAs($this->admin(), ['*']);

        $this->getJson("/api/admin/catalogue/zones/{$marocaine->id}/trades")
            ->assertOk()
            ->assertJsonPath('zone.currency', 'MAD');
    }

    /** TEMOIN POSITIF — la meme route rend « EUR » pour une zone belge. */
    public function test_temoin_l_api_rend_l_euro_pour_une_zone_belge(): void
    {
        $belge = $this->zoneAu('BE', 'EUR');

        Sanctum::actingAs($this->admin(), ['*']);

        $this->getJson("/api/admin/catalogue/zones/{$belge->id}/trades")
            ->assertOk()
            ->assertJsonPath('zone.currency', 'EUR');
    }
}
