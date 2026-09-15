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
use App\Support\Domain\OrderMode;
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

        $this->urgent = Sector::create(['name' => 'Dépannage', 'slug' => 'depannage-sonde', 'is_active' => true, 'sort_order' => 1]);
        $this->lent = Sector::create(['name' => 'Gros œuvre', 'slug' => 'gros-oeuvre-sonde', 'is_active' => true, 'sort_order' => 2]);

        $this->plomberie = Trade::create([
            'sector_id' => $this->urgent->id, 'slug' => 'plomberie-sonde', 'code' => 'PLB-SD', 'name' => 'Plomberie',
            'is_active' => true, 'sort_order' => 1, 'allows_scheduled' => true, 'allows_asap' => true, 'allows_bundle' => true,
            'base_price_cents' => 8500,
        ]);
        $this->sanitaire = Trade::create([
            'sector_id' => $this->urgent->id, 'slug' => 'sanitaire-sonde', 'code' => 'SAN-SD', 'name' => 'Sanitaire',
            'is_active' => true, 'sort_order' => 2, 'allows_scheduled' => true, 'allows_asap' => false, 'allows_bundle' => true,
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
        $this->assertSame(
            [$this->urgent->id, $this->lent->id],
            $this->catalogue()->secteurs(OrderMode::SCHEDULED, null)->pluck('id')->all(),
        );
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
        $this->assertSame(
            [$this->plomberie->id, $this->sanitaire->id],
            $this->catalogue()->metiers($this->urgent->id, OrderMode::SCHEDULED, null)->pluck('id')->all(),
        );
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

    #[Test]
    public function le_tarif_de_zone_passe_avant_le_prix_du_metier(): void
    {
        $ligne = TradeZonePricing::create([
            'trade_id' => $this->plomberie->id, 'service_zone_id' => $this->zone->id,
            'base_rate_cents' => 9900, 'surge_multiplier' => '1.00', 'is_active' => true, 'asap_enabled' => true,
        ]);

        $this->assertSame(9900, $this->catalogue()->prixPlancherCents($this->plomberie, $ligne));

        // Une ligne inactive ne compte pas : repli sur le prix du métier.
        $ligne->update(['is_active' => false]);
        $this->assertSame(8500, $this->catalogue()->prixPlancherCents($this->plomberie, $ligne->fresh()));

        // Sans ligne non plus.
        $this->assertSame(8500, $this->catalogue()->prixPlancherCents($this->plomberie, null));
    }

    #[Test]
    public function sans_aucun_prix_positif_le_plancher_est_inconnu(): void
    {
        $this->assertNull($this->catalogue()->prixPlancherCents($this->sanitaire, null));

        $this->sanitaire->update(['base_price_cents' => 0]);
        $this->assertNull($this->catalogue()->prixPlancherCents($this->sanitaire->fresh(), null));
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
