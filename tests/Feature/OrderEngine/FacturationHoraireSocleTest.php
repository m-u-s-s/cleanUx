<?php

namespace Tests\Feature\OrderEngine;

use App\Livewire\Admin\Trades;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\TradeZonePricing;
use App\Models\User;
use App\Services\OrderEngine\HourlyRateResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * LE SOCLE DE LA FACTURATION AU TEMPS PASSÉ.
 *
 * Ce que ces tests protègent, en une phrase : la plateforme annonçait publiquement « à partir de
 * 45 €/heure » et facturait 45 € forfaitaires. Le drapeau `hourly_billing` est ce qui rend la
 * promesse vraie — et il ne sert à rien s'il n'est pas éditable, pas persisté, ou si le tarif
 * qu'il pilote ne se résout pas.
 */
class FacturationHoraireSocleTest extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────────────────────────
    // Le schéma
    // ─────────────────────────────────────────────────────────────

    public function test_les_deux_colonnes_existent(): void
    {
        $this->assertTrue(Schema::hasColumn('trades', 'hourly_billing'));
        $this->assertTrue(Schema::hasColumn('trade_zone_pricing', 'price_per_hour_cents'));
    }

    public function test_aucun_metier_ne_change_de_comportement_par_defaut(): void
    {
        $metier = Trade::factory()->create();

        $this->assertFalse((bool) $metier->hourly_billing, 'Le défaut doit être neutre.');
        $this->assertNull(app(HourlyRateResolver::class)->tarifCatalogue($metier));
    }

    // ─────────────────────────────────────────────────────────────
    // Le formulaire admin
    // ─────────────────────────────────────────────────────────────

    public function test_ladmin_active_le_paiement_a_lheure_et_le_tarif_est_persiste(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Trades::class)
            ->call('openCreate')
            ->set('name', 'Ménage à l’heure')
            ->set('slug', 'menage-heure')
            ->set('code', 'MEN_H')
            ->set('hourly_billing', true)
            ->set('default_hourly_rate', '45.00')
            ->call('save')
            ->assertHasNoErrors();

        $metier = Trade::query()->where('slug', 'menage-heure')->firstOrFail();

        $this->assertTrue((bool) $metier->hourly_billing);
        $this->assertSame(4500, app(HourlyRateResolver::class)->tarifCatalogue($metier));
    }

    /**
     * Cocher la case sans tarif produirait un métier qui multiplie des heures par rien. Le refus
     * doit tomber à la saisie, pas à la première commande où il passerait pour une panne.
     */
    public function test_cocher_sans_tarif_est_refuse(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Trades::class)
            ->call('openCreate')
            ->set('name', 'Sans tarif')
            ->set('slug', 'sans-tarif')
            ->set('code', 'SANS_T')
            ->set('hourly_billing', true)
            ->set('default_hourly_rate', '')
            ->call('save')
            ->assertHasErrors('default_hourly_rate');

        $this->assertNull(Trade::query()->where('slug', 'sans-tarif')->first());
    }

    /** TÉMOIN : sans la case, l'absence de tarif ne gêne personne. */
    public function test_sans_la_case_le_tarif_reste_facultatif(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Trades::class)
            ->call('openCreate')
            ->set('name', 'Forfait')
            ->set('slug', 'forfait')
            ->set('code', 'FORF')
            ->set('hourly_billing', false)
            ->set('default_hourly_rate', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNotNull(Trade::query()->where('slug', 'forfait')->first());
    }

    public function test_lediteur_recharge_la_case_telle_quelle(): void
    {
        $metier = Trade::factory()->create([
            'hourly_billing' => true,
            'default_hourly_rate' => 38.50,
        ]);

        Livewire::actingAs($this->admin())
            ->test(Trades::class)
            ->call('edit', $metier->id)
            ->assertSet('hourly_billing', true)
            ->assertSet('default_hourly_rate', '38.50');
    }

    // ─────────────────────────────────────────────────────────────
    // La résolution du tarif
    // ─────────────────────────────────────────────────────────────

    public function test_la_zone_surcharge_le_tarif_du_metier(): void
    {
        [$metier, $zone] = $this->metierHoraireEtZone(4500);

        TradeZonePricing::updateOrCreate(
            ['trade_id' => $metier->id, 'service_zone_id' => $zone->id],
            ['base_rate_cents' => 0, 'is_active' => true, 'price_per_hour_cents' => 6000],
        );

        $resolveur = app(HourlyRateResolver::class);

        $this->assertSame(4500, $resolveur->tarifCatalogue($metier), 'Hors zone : le tarif du métier.');
        $this->assertSame(6000, $resolveur->tarifCatalogue($metier, $zone->id), 'Dans la zone : sa surcharge.');
    }

    /**
     * `null` veut dire « cette zone ne surcharge rien », `0` veut dire « une heure est offerte ici ».
     * Les confondre transformerait une gratuité voulue en facturation pleine — c'est exactement le
     * piège qui a valu à `price_per_km_cents` de ne PAS être casté en entier.
     */
    public function test_une_zone_a_zero_nest_pas_une_zone_sans_surcharge(): void
    {
        [$metier, $zone] = $this->metierHoraireEtZone(4500);

        TradeZonePricing::updateOrCreate(
            ['trade_id' => $metier->id, 'service_zone_id' => $zone->id],
            ['base_rate_cents' => 0, 'is_active' => true, 'price_per_hour_cents' => 0],
        );

        $this->assertSame(0, app(HourlyRateResolver::class)->tarifCatalogue($metier, $zone->id));
    }

    public function test_une_zone_sans_ligne_retombe_sur_le_metier(): void
    {
        [$metier, $zone] = $this->metierHoraireEtZone(4500);

        $this->assertSame(4500, app(HourlyRateResolver::class)->tarifCatalogue($metier, $zone->id));
    }

    public function test_un_metier_forfaitaire_na_pas_de_tarif_horaire(): void
    {
        $metier = Trade::factory()->create([
            'hourly_billing' => false,
            // Le tarif est renseigné et affiché sur la vitrine — mais sans la case, il ne facture rien.
            'default_hourly_rate' => 45.00,
        ]);

        $this->assertNull(app(HourlyRateResolver::class)->tarifCatalogue($metier));
    }

    // ─────────────────────────────────────────────────────────────

    private function metierHoraireEtZone(int $tarifCents): array
    {
        $metier = Trade::factory()->create([
            'hourly_billing' => true,
            'default_hourly_rate' => $tarifCents / 100,
        ]);

        return [$metier, ServiceZone::factory()->create()];
    }

    private function admin(): User
    {
        return User::factory()->create([
            'platform_role' => 'super_admin',
            'is_active' => true,
        ]);
    }
}
