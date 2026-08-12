<?php

namespace Tests\Feature\Pricing;

use App\Livewire\Admin\OrderEngine\CatalogCenter;
use App\Models\Country;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\TradeZonePricing;
use App\Models\User;
use App\Services\Pricing\SurgePricingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * LA MAJORATION PAR MÉTIER ET PAR ZONE : RÉGLABLE, APPLIQUÉE, ET COUPABLE.
 *
 * TROIS DÉFAUTS SE COMPLÉTAIENT.
 *
 * Le multiplicateur vivait sur `trade_zone_pricing`, le moteur de surge le lisait — et AUCUN écran
 * ne permettait de l'écrire. Un réglage qu'on consulte sans pouvoir le changer n'est pas un
 * réglage : pour majorer la plomberie à Bruxelles un soir de tempête, il fallait une requête SQL.
 *
 * Et `surge_pricing` figurait dans `config/features.php`, à `true`, sans qu'aucun code ne
 * l'interroge. Le seul interrupteur censé couper la majoration sans déploiement ne coupait rien.
 * Sur un mécanisme qui change ce que les gens paient, c'est le pire endroit où laisser un
 * interrupteur factice : le jour où une majoration s'emballe, on croit l'éteindre et elle continue.
 *
 * ENFIN, DEUX TABLES DÉCRIVAIENT LE MÊME FAIT — `trade_zone_settings` servait l'écran
 * d'administration, `trade_zone_pricing` servait le parcours de commande. L'administrateur réglait
 * l'une, le client payait selon l'autre. La première a été supprimée ; ce fichier vérifie qu'une
 * seule vérité subsiste, en mesurant l'effet du réglage sur le PRIX.
 */
class MajorationParMetierEtZoneTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: ServiceZone, 2: Trade, 3: Country} */
    private function contexte(): array
    {
        $admin = User::factory()->admin()->create();
        $pays = Country::factory()->create();
        $zone = ServiceZone::factory()->create(['country_id' => $pays->id]);
        $trade = Trade::factory()->create(['allows_asap' => true]);

        TradeZonePricing::query()->create([
            'trade_id' => $trade->id,
            'service_zone_id' => $zone->id,
            'base_rate_cents' => 10000,
            'surge_multiplier' => '1.00',
            'is_active' => true,
            'asap_enabled' => false,
        ]);

        return [$admin, $zone, $trade, $pays];
    }

    #[Test]
    public function l_administration_peut_regler_la_majoration(): void
    {
        [$admin, $zone, $trade, $pays] = $this->contexte();

        Livewire::actingAs($admin)
            ->test(CatalogCenter::class, ['country' => $pays, 'zone' => $zone])
            ->call('reglerMajorationDansLaZone', $trade->id, '1.50');

        $this->assertSame(
            '1.50',
            (string) TradeZonePricing::query()
                ->where('trade_id', $trade->id)
                ->where('service_zone_id', $zone->id)
                ->value('surge_multiplier'),
        );
    }

    #[Test]
    public function la_majoration_reglee_change_le_prix(): void
    {
        [, $zone, $trade] = $this->contexte();

        TradeZonePricing::query()
            ->where('trade_id', $trade->id)
            ->where('service_zone_id', $zone->id)
            ->update(['surge_multiplier' => '1.50']);

        $resultat = app(SurgePricingEngine::class)->calculate(100.0, $zone, ['trade_id' => $trade->id]);

        /*
         * C'EST L'ASSERTION QUI FERME LA BOUCLE. Vérifier que la colonne contient 1,50 ne prouverait
         * que l'écriture ; ce qui comptait, c'est que le moteur de prix lise CETTE table-là. Deux
         * grilles concurrentes passaient ce genre de test des deux côtés tout en facturant un
         * montant qui ne correspondait à aucun des deux écrans.
         */
        $this->assertSame(1.5, $resultat['factors']['trade_zone']);
        $this->assertGreaterThan(100.0, $resultat['final_price']);
    }

    #[Test]
    public function le_drapeau_baisse_coupe_toute_majoration(): void
    {
        [, $zone, $trade] = $this->contexte();

        TradeZonePricing::query()
            ->where('trade_id', $trade->id)
            ->update(['surge_multiplier' => '2.00']);

        config()->set('features.surge_pricing', false);

        $resultat = app(SurgePricingEngine::class)->calculate(100.0, $zone, ['trade_id' => $trade->id]);

        // Le prix de base rendu tel quel, et une source qui le DIT : les appelants affichent parfois
        // « prix majoré », ils doivent pouvoir distinguer « pas de majoration » de « majoration
        // désactivée ».
        $this->assertSame(100.0, $resultat['final_price']);
        $this->assertSame(1.0, $resultat['multiplier']);
        $this->assertSame('disabled', $resultat['source']);
        $this->assertFalse($resultat['is_visible']);
    }

    #[Test]
    public function une_majoration_sous_un_est_refusee(): void
    {
        [$admin, $zone, $trade, $pays] = $this->contexte();

        Livewire::actingAs($admin)
            ->test(CatalogCenter::class, ['country' => $pays, 'zone' => $zone])
            ->call('reglerMajorationDansLaZone', $trade->id, '0.80');

        // Une « majoration » inférieure à 1 serait une remise appliquée à tous sans que personne ne
        // l'ait décidée — et elle passerait par un champ intitulé « majoration ».
        $this->assertSame(
            '1.00',
            (string) TradeZonePricing::query()->where('trade_id', $trade->id)->value('surge_multiplier'),
        );
    }

    #[Test]
    public function un_metier_ferme_dans_la_zone_ne_se_majore_pas(): void
    {
        [$admin, $zone, $trade, $pays] = $this->contexte();

        TradeZonePricing::query()->where('trade_id', $trade->id)->update(['is_active' => false]);

        Livewire::actingAs($admin)
            ->test(CatalogCenter::class, ['country' => $pays, 'zone' => $zone])
            ->call('reglerMajorationDansLaZone', $trade->id, '1.80');

        // Majorer un service qu'on ne vend pas ici ne s'applique à rien, mais laisse une valeur
        // trompeuse que quelqu'un lira le jour où le métier rouvrira.
        $this->assertSame(
            '1.00',
            (string) TradeZonePricing::query()->where('trade_id', $trade->id)->value('surge_multiplier'),
        );
    }

    #[Test]
    public function la_table_concurrente_a_bien_disparu(): void
    {
        // `trade_zone_settings` décrivait le même fait que `trade_zone_pricing`. Deux grilles pour
        // un seul prix, c'est un prix facturé qui ne correspond à aucun écran.
        $this->assertFalse(Schema::hasTable('trade_zone_settings'));
        $this->assertTrue(Schema::hasColumn('trade_zone_pricing', 'surge_multiplier'));
    }
}
