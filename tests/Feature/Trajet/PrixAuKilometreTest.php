<?php

namespace Tests\Feature\Trajet;

use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\TradeZonePricing;
use App\Services\OrderEngine\PricingEngine;
use App\Services\OrderEngine\ZonePricingResolver;
use App\Support\Domain\OrderMode;
use Database\Seeders\OrderEngineCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** LE PRIX AU KILOMÈTRE, ET LA PREUVE QU'IL NE CHANGE RIEN AILLEURS. */
class PrixAuKilometreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrderEngineCatalogSeeder::class);
    }

    private function peinture(): Trade
    {
        return Trade::where('slug', 'peinture')->firstOrFail();
    }

    private function devis(Trade $trade, array $contexte = [], array $reponses = ['surface_m2' => 40]): array
    {
        $quote = app(PricingEngine::class)->quoteItem(
            $trade,
            $trade->questions()->with(['options', 'conditions'])->get(),
            $reponses,
            $contexte,
        );

        return ['min' => $quote->minCents, 'max' => $quote->maxCents, 'lignes' => $quote->lines];
    }

    /** LE TÉMOIN. */
    public function test_un_metier_sans_prix_au_kilometre_ne_change_pas_d_un_centime(): void
    {
        $peinture = $this->peinture();

        $avant = $this->devis($peinture);

        // Même appel, mais avec une route de vingt kilomètres dans le contexte : le drapeau étant
        // éteint, elle ne doit RIEN peser. C'est ce qui prouve que la composante est bien gardée.
        $apres = $this->devis($peinture, [
            'route_distance_m' => 20_000,
            'route_duration_s' => 1_800,
            'distance_pricing_enabled' => false,
            'price_per_km_cents' => 150,
        ]);

        $this->assertSame($avant['min'], $apres['min']);
        $this->assertSame($avant['max'], $apres['max']);
        $this->assertSame(
            [],
            array_values(array_filter($apres['lignes'], fn (array $l) => $l['code'] === '_distance')),
            'Aucune ligne « Trajet » ne doit apparaître sur le devis d’un métier au forfait.'
        );
    }

    public function test_la_distance_est_facturee_quand_la_zone_l_ouvre(): void
    {
        $peinture = $this->peinture();

        $forfait = $this->devis($peinture);

        $auKm = $this->devis($peinture, [
            'distance_pricing_enabled' => true,
            'route_distance_m' => 10_000,
            'pickup_fee_cents' => 250,
            'price_per_km_cents' => 100,
            'included_km' => 0,
        ]);

        // 2,50 € de prise en charge + 10 km × 1,00 € = 12,50 €
        $this->assertSame($forfait['min'] + 1_250, $auKm['min']);

        $ligne = collect($auKm['lignes'])->firstWhere('code', '_distance');
        $this->assertNotNull($ligne, 'Le trajet est une LIGNE du devis : un montant que le client ne peut pas rattacher, il le conteste.');
        $this->assertStringContainsString('km', (string) $ligne['detail']);
    }

    public function test_les_kilometres_inclus_ne_se_refacturent_pas(): void
    {
        $peinture = $this->peinture();

        $courte = $this->devis($peinture, [
            'distance_pricing_enabled' => true,
            'route_distance_m' => 800,
            'pickup_fee_cents' => 500,
            'price_per_km_cents' => 200,
            'included_km' => 2,
        ]);

        $forfait = $this->devis($peinture);

        // 800 m sous les 2 km inclus : seule la prise en charge est due.
        $this->assertSame($forfait['min'] + 500, $courte['min']);
    }

    public function test_le_temps_se_facture_quand_un_tarif_a_la_minute_existe(): void
    {
        $peinture = $this->peinture();

        $sansTemps = $this->devis($peinture, [
            'distance_pricing_enabled' => true,
            'route_distance_m' => 5_000,
            'price_per_km_cents' => 100,
        ]);

        $avecTemps = $this->devis($peinture, [
            'distance_pricing_enabled' => true,
            'route_distance_m' => 5_000,
            'route_duration_s' => 600,
            'price_per_km_cents' => 100,
            'price_per_minute_cents' => 30,
        ]);

        // 10 minutes × 0,30 € = 3,00 €
        $this->assertSame($sansTemps['min'] + 300, $avecTemps['min']);
    }

    /** Sans route mesurée, on ne facture pas une distance qu'on ne connaît pas. */
    public function test_sans_route_mesuree_aucune_distance_n_est_facturee(): void
    {
        $peinture = $this->peinture();

        $sansRoute = $this->devis($peinture, [
            'distance_pricing_enabled' => true,
            'pickup_fee_cents' => 500,
            'price_per_km_cents' => 200,
        ]);

        $this->assertSame($this->devis($peinture)['min'], $sansRoute['min']);
    }

    /** LA MAJORATION DE L'IMMÉDIAT PORTE AUSSI SUR LES KILOMÈTRES — c'est voulu. */
    public function test_la_majoration_de_l_immediat_porte_sur_les_kilometres(): void
    {
        $peinture = $this->peinture();

        $distance = [
            'distance_pricing_enabled' => true,
            'route_distance_m' => 10_000,
            'pickup_fee_cents' => 250,
            'price_per_km_cents' => 100,
            'included_km' => 0,
        ];

        // 2,50 € + 10 km × 1,00 € = 12,50 € de trajet brut.
        $immediat = $this->devis($peinture, $distance + ['mode' => OrderMode::ASAP])['min']
            - $this->devis($peinture, ['mode' => OrderMode::ASAP])['min'];

        // LE TÉMOIN : le même trajet, en rendez-vous programmé, n'est pas majoré.
        $programme = $this->devis($peinture, $distance)['min']
            - $this->devis($peinture)['min'];

        $this->assertSame(1_250, $programme);
        $this->assertSame(1_625, $immediat, '12,50 € × 1,30 : la majoration de l’immédiat porte sur toute la course.');
    }

    /** L'ÉLARGISSEMENT D'INCERTITUDE NE TOUCHE PAS UNE DISTANCE MESURÉE. */
    public function test_l_elargissement_d_incertitude_ne_touche_pas_les_kilometres_mesures(): void
    {
        $peinture = $this->peinture();

        $sans = $this->devis($peinture, ['mode' => OrderMode::ASAP]);
        $avec = $this->devis($peinture, [
            'mode' => OrderMode::ASAP,
            'distance_pricing_enabled' => true,
            'route_distance_m' => 20_000,
            'pickup_fee_cents' => 250,
            'price_per_km_cents' => 120,
            'included_km' => 0,
        ]);

        // LE TÉMOIN, sans lequel ce test passerait au vert en mesurant un élargissement en panne :
        // la prestation, elle, est bien élargie.
        $this->assertGreaterThan(0, $sans['max'] - $sans['min'], 'Sans fourchette sur la prestation, ce test ne prouve rien.');

        $this->assertSame(
            $sans['max'] - $sans['min'],
            $avec['max'] - $avec['min'],
            'Ajouter une distance mesurée ne doit pas élargir la fourchette d’un seul centime.'
        );
        $this->assertSame($avec['max'] - $sans['max'], $avec['min'] - $sans['min']);
    }

    /** Un « je ne sais pas » élargit la prestation, jamais le trajet. */
    public function test_un_je_ne_sais_pas_n_elargit_pas_le_trajet(): void
    {
        $peinture = $this->peinture();
        $reponses = ['surface_m2' => '__unknown__'];

        $sans = $this->devis($peinture, [], $reponses);
        $avec = $this->devis($peinture, [
            'distance_pricing_enabled' => true,
            'route_distance_m' => 15_000,
            'price_per_km_cents' => 200,
        ], $reponses);

        $this->assertGreaterThan(0, $sans['max'] - $sans['min'], 'Sans porte de sortie effective, ce test ne prouve rien.');
        $this->assertSame($avec['max'] - $sans['max'], $avec['min'] - $sans['min']);
    }

    /** UN MULTIPLICATEUR DE PRIX, LUI, PORTE BIEN SUR LES KILOMÈTRES. */
    public function test_un_multiplicateur_de_reponse_porte_sur_les_kilometres(): void
    {
        $peinture = $this->peinture();
        $reponses = ['surface_m2' => 40, 'etat_support' => 'a_decaper']; // × 1,35

        $sans = $this->devis($peinture, [], $reponses);
        $avec = $this->devis($peinture, [
            'distance_pricing_enabled' => true,
            'route_distance_m' => 10_000,
            'price_per_km_cents' => 100,
            'included_km' => 0,
        ], $reponses);

        // 10,00 € de trajet × 1,35
        $this->assertSame(1_350, $avec['min'] - $sans['min']);
    }

    /** « Aucun tarif au kilomètre » et « zéro centime le kilomètre » ne sont pas la même chose. */
    public function test_le_contexte_distingue_l_absence_de_tarif_du_tarif_a_zero(): void
    {
        $zone = ServiceZone::factory()->create();
        $peinture = $this->peinture();

        $ligne = TradeZonePricing::create([
            'trade_id' => $peinture->id,
            'service_zone_id' => $zone->id,
            'base_rate_cents' => 5_000,
            'surge_multiplier' => '1.00',
            'is_active' => true,
            'distance_pricing_enabled' => true,
        ]);

        $contexte = app(ZonePricingResolver::class)->pricingContext($peinture->id, $zone->id);
        $this->assertNull($contexte['price_per_km_cents']);

        $ligne->update(['price_per_km_cents' => 0]);

        $contexte = app(ZonePricingResolver::class)->pricingContext($peinture->id, $zone->id);
        $this->assertSame(0, $contexte['price_per_km_cents']);
    }
}
