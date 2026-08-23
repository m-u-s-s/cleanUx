<?php

namespace Tests\Feature\OrderEngine;

use App\Livewire\OrderEngine\OrderJourney;
use App\Models\OrderDraftItem;
use App\Models\Trade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/** LE CLIENT CHOISIT SES HEURES. */
class ChoixDesHeuresTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_deux_colonnes_existent(): void
    {
        $this->assertTrue(Schema::hasColumn('order_draft_items', 'purchased_minutes'));
        $this->assertTrue(Schema::hasColumn('bookings', 'purchased_minutes'));
    }

    public function test_un_metier_horaire_ouvre_le_selecteur(): void
    {
        $metier = $this->metierHoraire();

        Livewire::test(OrderJourney::class, ['trade' => $metier->slug])
            ->assertOk();

        $this->assertTrue(
            (bool) Trade::query()->whereKey($metier->id)->value('hourly_billing'),
        );
    }

    // ── Les bornes ───────────────────────────────────────────────────────

    public function test_les_heures_sarrondissent_a_la_demi_heure(): void
    {
        $composant = $this->parcours()->call('choisirLesHeures', 2.3);

        // 2,3 → 2,5 : un client qui veut « environ deux heures et demie » ne doit pas
        // avoir à trancher entre deux et trois.
        $this->assertSame(2.5, $composant->get('heuresChoisies'));
    }

    public function test_le_minimum_est_tenu(): void
    {
        $composant = $this->parcours()->call('choisirLesHeures', 0.25);

        $this->assertSame(
            (float) config('order_engine.hourly_min_hours'),
            $composant->get('heuresChoisies'),
            'Sous une heure, on vend un déplacement à perte.',
        );
    }

    public function test_le_maximum_est_tenu(): void
    {
        $composant = $this->parcours()->call('choisirLesHeures', 999);

        $this->assertSame(
            (float) config('order_engine.hourly_max_hours'),
            $composant->get('heuresChoisies'),
        );
    }

    /** ZÉRO HEURE PRODUIRAIT UNE PRESTATION GRATUITE — c'est la borne qui compte le plus. */
    public function test_zero_heure_est_impossible(): void
    {
        $composant = $this->parcours()->call('choisirLesHeures', 0);

        $this->assertGreaterThan(0, $composant->get('heuresChoisies'));
    }

    public function test_les_boutons_avancent_et_reculent_par_demi_heure(): void
    {
        $composant = $this->parcours()
            ->call('choisirLesHeures', 3)
            ->call('ajouterUneDemiHeure');

        $this->assertSame(3.5, $composant->get('heuresChoisies'));

        $composant->call('retirerUneDemiHeure')->call('retirerUneDemiHeure');

        $this->assertSame(2.5, $composant->get('heuresChoisies'));
    }

    // ── La persistance ───────────────────────────────────────────────────

    public function test_les_heures_sont_enregistrees_sur_la_ligne_de_panier(): void
    {
        $metier = $this->metierHoraire();

        Livewire::test(OrderJourney::class, ['trade' => $metier->slug])
            ->call('choisirLesHeures', 3);

        $ligne = OrderDraftItem::query()->where('trade_id', $metier->id)->first();

        $this->assertNotNull($ligne, 'La ligne de panier doit exister.');
        $this->assertSame(180, $ligne->purchased_minutes);
    }

    /** LES HEURES VIVENT PAR MÉTIER, PAS PAR PANIER. */
    public function test_deux_metiers_du_meme_panier_gardent_chacun_leurs_heures(): void
    {
        $menage = $this->metierHoraire('menage-h', 'MEN_H');
        $repassage = $this->metierHoraire('repassage-h', 'REP_H');

        $composant = Livewire::test(OrderJourney::class, ['trade' => $menage->slug])
            ->call('choisirLesHeures', 2);

        $jeton = $composant->get('sessionToken');

        Livewire::test(OrderJourney::class, ['trade' => $repassage->slug])
            ->set('sessionToken', $jeton)
            ->call('choisirLesHeures', 3);

        $this->assertSame(
            120,
            OrderDraftItem::query()->where('trade_id', $menage->id)->value('purchased_minutes'),
        );
        $this->assertSame(
            180,
            OrderDraftItem::query()->where('trade_id', $repassage->id)->value('purchased_minutes'),
        );
    }

    // ── Le témoin : un métier forfaitaire ────────────────────────────────

    public function test_un_metier_forfaitaire_ne_porte_aucune_heure(): void
    {
        $metier = Trade::factory()->create([
            'slug' => 'forfait-x',
            'code' => 'FORF_X',
            'hourly_billing' => false,
            'base_price_cents' => 9900,
        ]);

        $composant = Livewire::test(OrderJourney::class, ['trade' => $metier->slug]);

        $this->assertFalse($composant->instance()->estFactureALHeure());
        $this->assertNull($composant->instance()->heuresEnMinutes());
    }

    public function test_le_tarif_horaire_est_expose_au_parcours(): void
    {
        $metier = $this->metierHoraire();

        $composant = Livewire::test(OrderJourney::class, ['trade' => $metier->slug]);

        $this->assertSame(4500, $composant->instance()->tarifHoraireCents());
    }

    // ─────────────────────────────────────────────────────────────────────

    private function parcours()
    {
        return Livewire::test(OrderJourney::class, ['trade' => $this->metierHoraire()->slug]);
    }

    private function metierHoraire(string $slug = 'menage-heure', string $code = 'MEN_HEURE'): Trade
    {
        return Trade::factory()->create([
            'slug' => $slug,
            'code' => $code,
            'hourly_billing' => true,
            'default_hourly_rate' => 45.00,
            'base_price_cents' => 0,
            'estimated_duration_min' => 120,
            'is_active' => true,
        ]);
    }
}
