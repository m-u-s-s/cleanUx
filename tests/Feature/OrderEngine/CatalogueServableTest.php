<?php

namespace Tests\Feature\OrderEngine;

use App\Models\ClientPlace;
use App\Models\Country;
use App\Models\Sector;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\TradeZonePricing;
use App\Models\User;
use App\Services\OrderEngine\CatalogueServable;
use App\Services\OrderEngine\PricingEngine;
use App\Services\OrderEngine\ZonePricingResolver;
use App\Support\Domain\OrderMode;
use App\Support\Domain\PricingUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** CE QUE LE MOTEUR ACCEPTE, DIT UNE SEULE FOIS — pour le web ET pour l'application. */
class CatalogueServableTest extends TestCase
{
    use RefreshDatabase;

    private ServiceZone $zone;

    private Sector $urgent;

    private Sector $lent;

    private Trade $plomberie;

    private Trade $sanitaire;

    private Trade $ravalement;

    protected function setUp(): void
    {
        parent::setUp();

        $this->zone = ServiceZone::create([
            'name' => 'Zone sonde', 'slug' => 'zone-sonde', 'code' => 'ZSD',
            'status' => 'active', 'is_bookable' => true, 'is_visible' => true,
            'priority' => 10, 'coverage_type' => 'city_cluster',
        ]);

        // Ordre inverse de sort_order : les assertions sur les clauses de tri (pas juste l'ordre des resultats)
        // detectent un orderBy manquant. SQLite retourne les lignes triées via l'index (is_active, sort_order),
        // donc les résultats sont corrects même sans la clause; on teste la clause elle-même.
        $this->lent = Sector::create(['name' => 'Gros œuvre', 'slug' => 'gros-oeuvre-sonde', 'is_active' => true, 'sort_order' => 2]);
        $this->urgent = Sector::create(['name' => 'Dépannage', 'slug' => 'depannage-sonde', 'is_active' => true, 'sort_order' => 1]);

        // Les métiers aussi, en ordre inverse : sanitaire (2) avant plomberie (1).
        $this->sanitaire = Trade::create([
            'sector_id' => $this->urgent->id, 'slug' => 'sanitaire-sonde', 'code' => 'SAN-SD', 'name' => 'Sanitaire',
            'is_active' => true, 'sort_order' => 2, 'allows_scheduled' => true, 'allows_asap' => false, 'allows_bundle' => true,
        ]);
        $this->plomberie = Trade::create([
            'sector_id' => $this->urgent->id, 'slug' => 'plomberie-sonde', 'code' => 'PLB-SD', 'name' => 'Plomberie',
            'is_active' => true, 'sort_order' => 1, 'allows_scheduled' => true, 'allows_asap' => true, 'allows_bundle' => true,
            'base_price_cents' => 8500,
        ]);
        $this->ravalement = Trade::create([
            'sector_id' => $this->lent->id, 'slug' => 'ravalement-sonde', 'code' => 'RAV-SD', 'name' => 'Ravalement',
            'is_active' => true, 'sort_order' => 1, 'allows_scheduled' => true, 'allows_asap' => false, 'allows_bundle' => true,
        ]);
    }

    private function catalogue(): CatalogueServable
    {
        return app(CatalogueServable::class);
    }

    #[Test]
    public function en_rendez_vous_tous_les_secteurs_actifs_sont_servables(): void
    {
        $query = $this->catalogue()->secteurs(OrderMode::SCHEDULED, null);
        $this->assertSame(
            [$this->urgent->id, $this->lent->id],
            $query->pluck('id')->all(),
        );
        // L'index (is_active, sort_order) retourne les lignes triées même sans la clause;
        // on teste que la clause est explicitement présente (Sector::ordered() = orderBy sort_order, name).
        $this->assertSame(['sort_order', 'name'], array_column($query->getQuery()->orders ?? [], 'column'));
    }

    #[Test]
    public function en_immediat_un_secteur_sans_metier_immediat_disparait(): void
    {
        // Témoin : le même appel en rendez-vous garde les deux secteurs (test précédent).
        $this->assertSame([$this->urgent->id], $this->catalogue()->secteurs(OrderMode::ASAP, null)->pluck('id')->all());
    }

    #[Test]
    public function les_metiers_suivent_le_mode_et_leur_ordre(): void
    {
        $query = $this->catalogue()->metiers($this->urgent->id, OrderMode::SCHEDULED, null);
        $this->assertSame(
            [$this->plomberie->id, $this->sanitaire->id],
            $query->pluck('id')->all(),
        );
        // L'index (is_active, sort_order) retourne les lignes triées même sans la clause;
        // on teste que la clause est explicitement présente.
        $this->assertSame(['sort_order'], array_column($query->getQuery()->orders ?? [], 'column'));

        $this->assertSame(
            [$this->plomberie->id],
            $this->catalogue()->metiers($this->urgent->id, OrderMode::ASAP, null)->pluck('id')->all(),
        );
    }

    #[Test]
    public function une_zone_fermee_a_l_immediat_retire_le_metier(): void
    {
        TradeZonePricing::create([
            'trade_id' => $this->plomberie->id, 'service_zone_id' => $this->zone->id,
            'base_rate_cents' => 5000, 'surge_multiplier' => '1.00', 'is_active' => true, 'asap_enabled' => false,
        ]);

        $this->assertSame([], $this->catalogue()->secteurs(OrderMode::ASAP, $this->zone->id)->pluck('id')->all());
    }

    #[Test]
    public function temoin_la_meme_zone_ouverte_garde_le_metier(): void
    {
        TradeZonePricing::create([
            'trade_id' => $this->plomberie->id, 'service_zone_id' => $this->zone->id,
            'base_rate_cents' => 5000, 'surge_multiplier' => '1.00', 'is_active' => true, 'asap_enabled' => true,
        ]);

        $this->assertSame([$this->urgent->id], $this->catalogue()->secteurs(OrderMode::ASAP, $this->zone->id)->pluck('id')->all());
    }

    #[Test]
    public function la_zone_du_client_vient_de_son_lieu_par_defaut(): void
    {
        $client = User::factory()->client()->create();
        ClientPlace::factory()->create(['user_id' => $client->id, 'service_zone_id' => $this->zone->id, 'is_default' => false]);

        // Refus : un lieu qui n'est pas celui par défaut ne compte pas.
        $this->assertNull($this->catalogue()->zoneDuClient($client));

        // Témoin : le lieu par défaut donne la zone.
        ClientPlace::factory()->parDefaut()->create(['user_id' => $client->id, 'service_zone_id' => $this->zone->id]);
        $this->assertSame($this->zone->id, $this->catalogue()->zoneDuClient($client->fresh()));
    }

    /*
     * LE PLANCHER ANNONCÉ EST LE MINIMUM DU MOTEUR — mesuré ici contre le moteur lui-même.
     *
     * La référence est calculée comme le parcours web la calcule avant toute réponse : le contexte
     * de `ZonePricingResolver::pricingContext` (celui que lit `OrderJourney::quote`), le mode, et une
     * heure achetée pour un métier horaire. Chaque cas porte AUSSI son montant écrit en clair : si le
     * moteur changeait, la parité seule ne dirait pas que le chiffre annoncé a bougé.
     */

    #[Test]
    public function parite_a_en_rendez_vous_sans_zone_le_plancher_est_le_minimum_du_moteur(): void
    {
        $plancher = $this->catalogue()->plancher($this->plomberie, null, OrderMode::SCHEDULED);

        $this->assertSame($this->minimumDuMoteur($this->plomberie, null, OrderMode::SCHEDULED), $plancher['cents']);
        $this->assertSame(8500, $plancher['cents']);
        $this->assertFalse($plancher['horaire']);
    }

    #[Test]
    public function parite_b_en_immediat_la_ligne_de_zone_active_entre_dans_le_calcul(): void
    {
        $ligne = TradeZonePricing::create([
            'trade_id' => $this->plomberie->id, 'service_zone_id' => $this->zone->id,
            'base_rate_cents' => 9900, 'surge_multiplier' => '1.20', 'min_price_cents' => 15000,
            'is_active' => true, 'asap_enabled' => true,
        ]);

        $plancher = $this->catalogue()->plancher($this->plomberie, $ligne->fresh(), OrderMode::ASAP);

        $this->assertSame($this->minimumDuMoteur($this->plomberie, $this->zone->id, OrderMode::ASAP), $plancher['cents']);
        // 9900 × 1,30 (immédiat) × 1,20 (zone) = 15444, au-dessus du plancher de zone de 15000.
        $this->assertSame(15444, $plancher['cents']);
        $this->assertFalse($plancher['horaire']);

        // En rendez-vous, 9900 × 1,20 = 11880 : c'est le plancher de ZONE qui tient, comme au devis.
        $enRendezVous = $this->catalogue()->plancher($this->plomberie, $ligne->fresh(), OrderMode::SCHEDULED);
        $this->assertSame($this->minimumDuMoteur($this->plomberie, $this->zone->id, OrderMode::SCHEDULED), $enRendezVous['cents']);
        $this->assertSame(15000, $enRendezVous['cents']);
    }

    #[Test]
    public function parite_c_un_metier_au_devis_obligatoire_n_annonce_aucun_prix(): void
    {
        $surDevis = Trade::create([
            'sector_id' => $this->urgent->id, 'slug' => 'toiture-sonde', 'code' => 'TOI-SD', 'name' => 'Toiture',
            'is_active' => true, 'sort_order' => 3, 'allows_scheduled' => true, 'allows_asap' => false, 'allows_bundle' => true,
            // Un prix de base existe, et il ne doit PAS être annoncé : le moteur ne l'annoncerait pas.
            'base_price_cents' => 12000, 'pricing_unit' => PricingUnit::QUOTE_ONLY,
        ]);

        $plancher = $this->catalogue()->plancher($surDevis, null, OrderMode::SCHEDULED);

        $this->assertNull($this->minimumDuMoteur($surDevis, null, OrderMode::SCHEDULED));
        $this->assertNull($plancher['cents']);
        $this->assertFalse($plancher['horaire']);
    }

    #[Test]
    public function parite_d_un_metier_horaire_annonce_une_heure_au_tarif_de_sa_zone(): void
    {
        $menage = $this->metierHoraire('menage-sonde', 'MEN-SD', 45.00);
        $ligne = TradeZonePricing::create([
            'trade_id' => $menage->id, 'service_zone_id' => $this->zone->id,
            'base_rate_cents' => 0, 'surge_multiplier' => '1.00', 'price_per_hour_cents' => 6000,
            'is_active' => true, 'asap_enabled' => true,
        ]);

        $plancher = $this->catalogue()->plancher($menage, $ligne->fresh(), OrderMode::SCHEDULED);

        $this->assertSame($this->minimumDuMoteur($menage, $this->zone->id, OrderMode::SCHEDULED), $plancher['cents']);
        // Une heure à 60 € : ni le tarif de référence du métier (45 €), ni son forfait (85 €).
        $this->assertSame(6000, $plancher['cents']);
        $this->assertTrue($plancher['horaire']);
    }

    #[Test]
    public function parite_e_un_metier_horaire_sans_zone_annonce_une_heure_a_son_tarif_de_reference(): void
    {
        $menage = $this->metierHoraire('menage-sonde', 'MEN-SD', 45.00);

        $enRendezVous = $this->catalogue()->plancher($menage, null, OrderMode::SCHEDULED);
        $this->assertSame($this->minimumDuMoteur($menage, null, OrderMode::SCHEDULED), $enRendezVous['cents']);
        $this->assertSame(4500, $enRendezVous['cents']);
        $this->assertTrue($enRendezVous['horaire']);

        // L'immédiat majore l'heure aussi : 4500 × 1,30.
        $enImmediat = $this->catalogue()->plancher($menage, null, OrderMode::ASAP);
        $this->assertSame($this->minimumDuMoteur($menage, null, OrderMode::ASAP), $enImmediat['cents']);
        $this->assertSame(5850, $enImmediat['cents']);
        $this->assertTrue($enImmediat['horaire']);

        // Témoin : sans aucun tarif horaire, le moteur retombe sur le forfait — et le prix ne se lit plus par heure.
        $sansTarif = $this->metierHoraire('repassage-sonde', 'REP-SD', null);
        $forfait = $this->catalogue()->plancher($sansTarif, null, OrderMode::SCHEDULED);
        $this->assertSame($this->minimumDuMoteur($sansTarif, null, OrderMode::SCHEDULED), $forfait['cents']);
        $this->assertSame(8500, $forfait['cents']);
        $this->assertFalse($forfait['horaire']);
    }

    #[Test]
    public function parite_f_une_ligne_inactive_vaut_une_absence_de_ligne(): void
    {
        $ligne = TradeZonePricing::create([
            'trade_id' => $this->plomberie->id, 'service_zone_id' => $this->zone->id,
            'base_rate_cents' => 9900, 'surge_multiplier' => '1.50', 'min_price_cents' => 20000,
            'is_active' => false, 'asap_enabled' => true,
        ]);

        $plancher = $this->catalogue()->plancher($this->plomberie, $ligne->fresh(), OrderMode::ASAP);

        $this->assertSame($this->minimumDuMoteur($this->plomberie, $this->zone->id, OrderMode::ASAP), $plancher['cents']);
        $this->assertSame($this->catalogue()->plancher($this->plomberie, null, OrderMode::ASAP), $plancher);
        // 8500 × 1,30 : ni la grille, ni la majoration, ni le plancher de la ligne inactive.
        $this->assertSame(11050, $plancher['cents']);

        // Même règle pour le tarif horaire posé sur une ligne inactive.
        $menage = $this->metierHoraire('menage-sonde', 'MEN-SD', 45.00);
        $ligneHoraire = TradeZonePricing::create([
            'trade_id' => $menage->id, 'service_zone_id' => $this->zone->id,
            'base_rate_cents' => 0, 'surge_multiplier' => '1.00', 'price_per_hour_cents' => 9000,
            'is_active' => false, 'asap_enabled' => true,
        ]);

        $horaire = $this->catalogue()->plancher($menage, $ligneHoraire->fresh(), OrderMode::SCHEDULED);
        $this->assertSame($this->minimumDuMoteur($menage, $this->zone->id, OrderMode::SCHEDULED), $horaire['cents']);
        $this->assertSame(4500, $horaire['cents']);
        $this->assertTrue($horaire['horaire']);
    }

    #[Test]
    public function temoin_le_mode_atteint_le_moteur(): void
    {
        $enImmediat = $this->catalogue()->plancher($this->plomberie, null, OrderMode::ASAP);
        $enRendezVous = $this->catalogue()->plancher($this->plomberie, null, OrderMode::SCHEDULED);

        // Le même métier, sans zone : seul le mode change, et le plancher avec lui.
        $this->assertNotSame($enRendezVous['cents'], $enImmediat['cents']);
        $this->assertSame($this->minimumDuMoteur($this->plomberie, null, OrderMode::ASAP), $enImmediat['cents']);
        $this->assertSame($this->minimumDuMoteur($this->plomberie, null, OrderMode::SCHEDULED), $enRendezVous['cents']);
    }

    #[Test]
    public function sans_aucun_prix_positif_le_plancher_est_inconnu(): void
    {
        $this->assertNull($this->catalogue()->plancher($this->sanitaire, null, OrderMode::SCHEDULED)['cents']);

        $this->sanitaire->update(['base_price_cents' => 0]);
        $this->assertNull($this->catalogue()->plancher($this->sanitaire->fresh(), null, OrderMode::SCHEDULED)['cents']);
    }

    /** Le minimum que le moteur rend au parcours web avant toute réponse, `null` quand il n'annonce rien. */
    private function minimumDuMoteur(Trade $trade, ?int $zoneId, string $mode): ?int
    {
        $contexte = ['mode' => $mode] + app(ZonePricingResolver::class)->pricingContext((int) $trade->id, $zoneId);

        if ($trade->hourly_billing && $contexte['hourly_rate_cents'] !== null) {
            $contexte += ['purchased_minutes' => 60];
        }

        $devis = app(PricingEngine::class)->quoteItem($trade, collect(), [], $contexte);

        return $devis->quoteOnly || $devis->minCents <= 0 ? null : $devis->minCents;
    }

    /** Un métier facturé à l'heure, avec un forfait de 85 € que l'heure doit remplacer. */
    private function metierHoraire(string $slug, string $code, ?float $tarifDeReference): Trade
    {
        return Trade::create([
            'sector_id' => $this->urgent->id, 'slug' => $slug, 'code' => $code, 'name' => ucfirst(explode('-', $slug)[0]),
            'is_active' => true, 'sort_order' => 5, 'allows_scheduled' => true, 'allows_asap' => true, 'allows_bundle' => true,
            'base_price_cents' => 8500, 'hourly_billing' => true, 'default_hourly_rate' => $tarifDeReference,
        ]);
    }

    #[Test]
    public function la_devise_suit_le_pays_de_la_zone(): void
    {
        $maroc = Country::factory()->create(['currency_code' => 'MAD']);
        $this->zone->update(['country_id' => $maroc->id]);

        $this->assertSame('MAD', $this->catalogue()->devise($this->zone->id));

        // Témoin : sans zone, la devise de base.
        config(['fx.base_currency' => 'EUR']);
        $this->assertSame('EUR', $this->catalogue()->devise(null));
    }
}
