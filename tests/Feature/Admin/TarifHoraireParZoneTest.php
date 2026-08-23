<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\TradeZonePricingManager;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\TradeZonePricing;
use App\Models\User;
use App\Services\OrderEngine\HourlyRateResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** LA SURCHARGE HORAIRE PAR ZONE — une promesse que personne ne tenait. */
class TarifHoraireParZoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_ladministrateur_peut_poser_un_tarif_horaire_de_zone(): void
    {
        [$metier, $tarif] = $this->metierHoraireAvecZone();

        Livewire::actingAs($this->admin())
            ->test(TradeZonePricingManager::class, ['trade' => $metier])
            ->call('edit', $tarif->id)
            ->set('form_price_per_hour_cents', '6200')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(6200, (int) $tarif->refresh()->price_per_hour_cents);
    }

    /** Le moteur doit lire ce que l'écran a écrit — sinon l'un des deux parle dans le vide. */
    public function test_le_moteur_prefere_le_tarif_de_la_zone(): void
    {
        [$metier, $tarif] = $this->metierHoraireAvecZone();

        $resolveur = app(HourlyRateResolver::class);

        $this->assertSame(
            4500,
            $resolveur->tarifCatalogue($metier, $tarif->service_zone_id),
            'Sans surcharge, la zone suit le tarif du métier.',
        );

        $tarif->forceFill(['price_per_hour_cents' => 6200])->save();

        $this->assertSame(6200, $resolveur->tarifCatalogue($metier->fresh(), $tarif->service_zone_id));
    }

    /** VIDE ET ZÉRO NE DISENT PAS LA MÊME CHOSE. Vide = « suivre le métier ». */
    public function test_zero_est_une_gratuite_voulue_et_non_une_absence(): void
    {
        [$metier, $tarif] = $this->metierHoraireAvecZone();

        Livewire::actingAs($this->admin())
            ->test(TradeZonePricingManager::class, ['trade' => $metier])
            ->call('edit', $tarif->id)
            ->set('form_price_per_hour_cents', '0')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(0, (int) $tarif->refresh()->price_per_hour_cents);
        $this->assertSame(0, app(HourlyRateResolver::class)->tarifCatalogue($metier, $tarif->service_zone_id));
    }

    public function test_vider_le_champ_fait_retomber_sur_le_tarif_du_metier(): void
    {
        [$metier, $tarif] = $this->metierHoraireAvecZone();
        $tarif->forceFill(['price_per_hour_cents' => 6200])->save();

        Livewire::actingAs($this->admin())
            ->test(TradeZonePricingManager::class, ['trade' => $metier])
            ->call('edit', $tarif->id)
            ->set('form_price_per_hour_cents', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull($tarif->refresh()->price_per_hour_cents);
        $this->assertSame(4500, app(HourlyRateResolver::class)->tarifCatalogue($metier, $tarif->service_zone_id));
    }

    /** TÉMOIN — sur un métier au forfait, le champ n'a rien à faire là et le moteur refuse. */
    public function test_un_metier_au_forfait_na_ni_champ_ni_tarif(): void
    {
        [$metier, $tarif] = $this->metierHoraireAvecZone();
        $metier->forceFill(['hourly_billing' => false])->save();

        Livewire::actingAs($this->admin())
            ->test(TradeZonePricingManager::class, ['trade' => $metier->fresh()])
            ->assertViewHas('factureALHeure', false);

        $this->assertNull(app(HourlyRateResolver::class)->tarifCatalogue($metier->fresh(), $tarif->service_zone_id));
    }

    // ─────────────────────────────────────────────────────────────────────

    /** @return array{Trade, TradeZonePricing} */
    private function metierHoraireAvecZone(): array
    {
        $metier = Trade::factory()->create([
            'hourly_billing' => true,
            'default_hourly_rate' => 45,
        ]);

        $zone = ServiceZone::factory()->create();

        $tarif = TradeZonePricing::create([
            'trade_id' => $metier->id,
            'service_zone_id' => $zone->id,
            'base_rate_cents' => 4500,
            'surge_multiplier' => '1.00',
            'is_active' => true,
        ]);

        return [$metier, $tarif];
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }
}
