<?php

namespace Tests\Feature\OrderEngine;

use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\TradeZonePricing;
use App\Services\OrderEngine\PricingEngine;
use App\Services\OrderEngine\ZonePricingResolver;
use App\Support\Domain\OrderMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/** LE PRIX QUAND ON VEND DU TEMPS. */
class PrixALHeureTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_prix_est_le_tarif_multiplie_par_les_heures(): void
    {
        $devis = $this->devis(heures: 3, tarifCents: 4500);

        $this->assertSame(13500, $devis->minCents);
        $this->assertSame(13500, $devis->maxCents);
    }

    /** LA DURÉE DEVIENT UN ENGAGEMENT, PAS UNE ESTIMATION. */
    public function test_la_duree_rendue_est_celle_achetee_pas_celle_estimee(): void
    {
        $devis = $this->devis(heures: 4, tarifCents: 4500, estimationDuMetier: 120);

        $this->assertSame(240, $devis->durationMin);
    }

    /** Le forfait du métier ET celui de la zone sont ignorés : les additionner ferait payer deux fois la même prestation. */
    public function test_le_forfait_du_metier_est_ignore(): void
    {
        $devis = $this->devis(heures: 2, tarifCents: 5000, forfaitMetierCents: 9900);

        $this->assertSame(10000, $devis->minCents);
    }

    public function test_la_ligne_de_devis_nomme_le_calcul(): void
    {
        $devis = $this->devis(heures: 3, tarifCents: 4500);

        $ligne = collect($devis->lines)->firstWhere('code', '_hourly');

        $this->assertNotNull($ligne, 'Le client doit voir d’où viennent ses heures.');
        $this->assertSame('3 h × 45,00 €', $ligne['detail']);
    }

    public function test_une_demi_heure_est_lisible(): void
    {
        $devis = $this->devis(heures: 2.5, tarifCents: 4000);

        $ligne = collect($devis->lines)->firstWhere('code', '_hourly');

        $this->assertSame('2,5 h × 40,00 €', $ligne['detail']);
        $this->assertSame(10000, $devis->minCents);
        $this->assertSame(150, $devis->durationMin);
    }

    // ── Les bords ────────────────────────────────────────────────────────

    /** SANS HEURES CHOISIES, ON GARDE LE FORFAIT — surtout pas 0 €. */
    public function test_sans_heures_choisies_le_forfait_tient(): void
    {
        $devis = $this->devis(heures: null, tarifCents: 4500, forfaitMetierCents: 9900);

        $this->assertSame(9900, $devis->minCents);
    }

    public function test_sans_tarif_horaire_le_forfait_tient(): void
    {
        $devis = $this->devis(heures: 3, tarifCents: null, forfaitMetierCents: 9900);

        $this->assertSame(9900, $devis->minCents);
    }

    /** TÉMOIN : un métier NON horaire ignore complètement les heures reçues. */
    public function test_un_metier_forfaitaire_ignore_les_heures(): void
    {
        $devis = $this->devis(heures: 8, tarifCents: 4500, forfaitMetierCents: 9900, horaire: false);

        $this->assertSame(9900, $devis->minCents);
    }

    // ── L'empilement des multiplicateurs ─────────────────────────────────

    /** LE MODE IMMÉDIAT MULTIPLIE LE TOTAL HORAIRE, pas le tarif seul — et c'est ce qui permettra de retrouver le tarif effectif par simple division. */
    public function test_le_mode_immediat_majore_le_total_horaire(): void
    {
        $devis = $this->devis(heures: 3, tarifCents: 4500, mode: OrderMode::ASAP);

        // 3 × 4500 = 13500, puis ×1,30 = 17550
        $this->assertSame(17550, $devis->minCents);
    }

    /** LA DIVISION QUI PORTE TOUTE LA RÈGLE DU DÉPASSEMENT. */
    public function test_le_tarif_effectif_se_retrouve_par_division(): void
    {
        $devis = $this->devis(heures: 3, tarifCents: 4500, mode: OrderMode::ASAP);

        $tarifEffectif = (int) round($devis->minCents / ($devis->durationMin / 60));

        $this->assertSame(5850, $tarifEffectif, 'Soit 45 € × 1,30.');
    }

    public function test_la_majoration_de_zone_sempile_aussi(): void
    {
        $devis = $this->devis(heures: 2, tarifCents: 5000, majorationDeZone: 1.5);

        $this->assertSame(15000, $devis->minCents);
    }

    // ── La zone surcharge le tarif, de bout en bout ──────────────────────

    public function test_le_contexte_de_zone_porte_le_tarif_horaire(): void
    {
        $metier = Trade::factory()->create([
            'hourly_billing' => true,
            'default_hourly_rate' => 45.00,
            'base_price_cents' => 0,
        ]);
        $zone = ServiceZone::factory()->create();

        TradeZonePricing::updateOrCreate(
            ['trade_id' => $metier->id, 'service_zone_id' => $zone->id],
            ['base_rate_cents' => 0, 'is_active' => true, 'price_per_hour_cents' => 6000],
        );

        $contexte = app(ZonePricingResolver::class)->pricingContext($metier->id, $zone->id);

        $this->assertSame(6000, $contexte['hourly_rate_cents']);
    }

    public function test_une_zone_sans_grille_porte_quand_meme_le_tarif_du_metier(): void
    {
        $metier = Trade::factory()->create([
            'hourly_billing' => true,
            'default_hourly_rate' => 45.00,
        ]);

        $contexte = app(ZonePricingResolver::class)->pricingContext($metier->id, null);

        $this->assertSame(4500, $contexte['hourly_rate_cents']);
    }

    // ─────────────────────────────────────────────────────────────────────

    private function devis(
        ?float $heures,
        ?int $tarifCents,
        int $forfaitMetierCents = 0,
        int $estimationDuMetier = 0,
        bool $horaire = true,
        string $mode = OrderMode::SCHEDULED,
        float $majorationDeZone = 1.0,
    ) {
        $metier = Trade::factory()->create([
            'hourly_billing' => $horaire,
            'default_hourly_rate' => $tarifCents !== null ? $tarifCents / 100 : null,
            'base_price_cents' => $forfaitMetierCents,
            'estimated_duration_min' => $estimationDuMetier,
        ]);

        $contexte = [
            'mode' => $mode,
            'zone_multiplier' => $majorationDeZone,
            'hourly_rate_cents' => $tarifCents,
            'purchased_minutes' => $heures !== null ? (int) round($heures * 60) : null,
        ];

        return app(PricingEngine::class)->quoteItem(
            $metier,
            new Collection([]),
            [],
            $contexte,
        );
    }
}
