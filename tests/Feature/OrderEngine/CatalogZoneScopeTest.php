<?php

namespace Tests\Feature\OrderEngine;

use App\Livewire\Admin\OrderEngine\CatalogCenter;
use App\Models\Country;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\TradeZonePricing;
use App\Models\User;
use Database\Seeders\OrderEngineCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/** Le catalogue vu depuis UNE zone. */
class CatalogZoneScopeTest extends TestCase
{
    use RefreshDatabase;

    private Country $pays;

    private ServiceZone $zone;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrderEngineCatalogSeeder::class);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->pays = Country::factory()->create();
        $this->zone = ServiceZone::factory()->create([
            'country_id' => $this->pays->id,
            'name' => 'Bruxelles',
        ]);
    }

    /** @return array{country: Country, zone: ServiceZone} */
    private function contexte(): array
    {
        return ['country' => $this->pays, 'zone' => $this->zone];
    }

    public function test_il_annonce_la_zone_qu_il_montre(): void
    {
        Livewire::test(CatalogCenter::class, $this->contexte())
            ->assertOk()
            ->assertSee('Bruxelles');
    }

    public function test_il_refuse_une_zone_qui_n_est_pas_dans_ce_pays(): void
    {
        $autrePays = Country::factory()->create();

        // L'URL porte les deux identifiants : rien n'empêche d'écrire un couple incohérent à la
        // main. Le refus est explicite plutôt que d'afficher le catalogue d'un marché voisin sous
        // le nom d'un autre.
        Livewire::test(CatalogCenter::class, ['country' => $autrePays, 'zone' => $this->zone])
            ->assertStatus(404);
    }

    public function test_activer_un_metier_cree_la_ligne_du_couple(): void
    {
        $metier = Trade::query()->firstOrFail();

        Livewire::test(CatalogCenter::class, $this->contexte())
            ->call('basculerMetierDansLaZone', $metier->id);

        $this->assertDatabaseHas('trade_zone_pricing', [
            'trade_id' => $metier->id,
            'service_zone_id' => $this->zone->id,
            'is_active' => true,
        ]);
    }

    public function test_desactiver_conserve_la_grille(): void
    {
        $metier = Trade::query()->firstOrFail();
        TradeZonePricing::create([
            'trade_id' => $metier->id,
            'service_zone_id' => $this->zone->id,
            'base_rate_cents' => 4500,
            'is_active' => true,
        ]);

        Livewire::test(CatalogCenter::class, $this->contexte())
            ->call('basculerMetierDansLaZone', $metier->id);

        $ligne = TradeZonePricing::where('trade_id', $metier->id)
            ->where('service_zone_id', $this->zone->id)
            ->firstOrFail();

        // Éteindre n'efface pas : rallumer doit retrouver le tarif saisi, pas repartir de zéro.
        // Supprimer la ligne ferait perdre un réglage qui a pu demander une négociation.
        $this->assertFalse((bool) $ligne->is_active);
        $this->assertSame(4500, (int) $ligne->base_rate_cents);
    }

    public function test_rallumer_retrouve_le_tarif(): void
    {
        $metier = Trade::query()->firstOrFail();
        TradeZonePricing::create([
            'trade_id' => $metier->id,
            'service_zone_id' => $this->zone->id,
            'base_rate_cents' => 4500,
            'is_active' => false,
        ]);

        Livewire::test(CatalogCenter::class, $this->contexte())
            ->call('basculerMetierDansLaZone', $metier->id);

        $ligne = TradeZonePricing::where('trade_id', $metier->id)
            ->where('service_zone_id', $this->zone->id)
            ->firstOrFail();

        $this->assertTrue((bool) $ligne->is_active);
        $this->assertSame(4500, (int) $ligne->base_rate_cents);
    }

    public function test_l_activation_est_propre_a_la_zone(): void
    {
        $metier = Trade::query()->firstOrFail();
        $autre = ServiceZone::factory()->create(['country_id' => $this->pays->id]);

        Livewire::test(CatalogCenter::class, $this->contexte())
            ->call('basculerMetierDansLaZone', $metier->id);

        // Toute la raison d'être du chantier : Bruxelles et Liège ne partagent rien.
        $this->assertDatabaseMissing('trade_zone_pricing', [
            'trade_id' => $metier->id,
            'service_zone_id' => $autre->id,
        ]);
    }

    public function test_il_ecrit_dans_la_table_qui_fait_foi(): void
    {
        $metier = Trade::query()->firstOrFail();

        Livewire::test(CatalogCenter::class, $this->contexte())
            ->call('basculerMetierDansLaZone', $metier->id);

        // `trade_zone_settings` portait elle aussi un `is_active` et un multiplicateur pour le même couple.
        $this->assertFalse(Schema::hasTable('trade_zone_settings'));
    }

    public function test_l_ecran_annonce_que_le_reglage_atteint_le_client(): void
    {
        // L'AVERTISSEMENT INVERSE.
        Livewire::test(CatalogCenter::class, $this->contexte())
            ->assertSee('Ce que vous réglez ici est ce que voit le client', false)
            ->assertDontSee('n’a pas encore d’effet sur ce que voit un client', false);
    }

    public function test_la_premiere_ouverture_part_du_prix_du_metier(): void
    {
        $metier = Trade::query()->firstOrFail();
        $metier->update(['base_price_cents' => 3900]);

        Livewire::test(CatalogCenter::class, $this->contexte())
            ->call('basculerMetierDansLaZone', $metier->id);

        // Ouvrir un métier dans une zone ne doit pas le mettre à zéro euro en attendant qu'on
        // saisisse une grille : on part du prix du métier, faute de mieux.
        $ligne = TradeZonePricing::where('trade_id', $metier->id)
            ->where('service_zone_id', $this->zone->id)
            ->firstOrFail();

        $this->assertSame(3900, (int) $ligne->base_rate_cents);
    }
}
