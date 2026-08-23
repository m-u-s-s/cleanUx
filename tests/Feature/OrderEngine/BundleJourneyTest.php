<?php

namespace Tests\Feature\OrderEngine;

use App\Livewire\OrderEngine\OrderJourney;
use App\Models\OrderDraft;
use App\Models\Trade;
use App\Models\TradeBundleSuggestion;
use App\Models\User;
use App\Support\Domain\OrderMode;
use Database\Seeders\OrderEngineCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/** L'écran multi-métiers : ajouter, ordonner, chiffrer. */
class BundleJourneyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrderEngineCatalogSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Ajouter un service le place dans le chantier, sans redemander l'adresse. */
    public function test_adding_a_service_puts_it_on_the_site_plan(): void
    {
        $component = $this->bundleJourney();

        $component->call('addService', $this->carrelage()->id);

        $this->assertSame(2, OrderDraft::firstOrFail()->items()->count());
        $component->assertSee('Votre chantier');
    }

    /** Le chemin réel du bouton « Ajouter un autre service ». */
    public function test_picking_a_trade_from_the_dock_lands_it_on_the_site_plan(): void
    {
        $component = $this->bundleJourney();

        $component->call('backToTrades')
            ->call('selectTrade', $this->carrelage()->id);

        $this->assertSame(2, OrderDraft::firstOrFail()->items()->count());
        $this->assertTrue(
            OrderDraft::firstOrFail()->items()->where('trade_id', $this->carrelage()->id)->exists(),
        );
    }

    /** L'adresse n'est demandée QU'UNE FOIS. */
    public function test_the_address_is_never_asked_twice(): void
    {
        $component = $this->bundleJourney();
        $component->call('addService', $this->carrelage()->id);

        $draft = OrderDraft::firstOrFail();

        $this->assertSame('Rue de la Loi 1, 1000 Bruxelles', $draft->address);
        $this->assertSame(
            0,
            $draft->items()->whereNotNull('metadata->address')->count(),
            'Aucune ligne ne doit porter d’adresse propre.',
        );
    }

    /** Le séquencement est VISIBLE : le client lit après quoi chaque métier passe. */
    public function test_the_timeline_shows_what_waits_for_what(): void
    {
        TradeBundleSuggestion::create([
            'trade_id' => $this->peinture()->id,
            'suggested_trade_id' => $this->carrelage()->id,
            'default_sequence_gap_min' => 1440,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $component = $this->bundleJourney();
        $component->call('addService', $this->carrelage()->id);

        $component->assertSee('Après « '.$this->peinture()->name.' »')
            ->assertSee('j de séchage');
    }

    /** Le décalage se PROPAGE : le second métier ne démarre pas en même temps que le premier. */
    public function test_the_delay_pushes_the_next_trade_back(): void
    {
        TradeBundleSuggestion::create([
            'trade_id' => $this->peinture()->id,
            'suggested_trade_id' => $this->carrelage()->id,
            'default_sequence_gap_min' => 1440,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $component = $this->bundleJourney();
        $component->call('addService', $this->carrelage()->id);

        $timeline = $component->get('timeline');

        $this->assertGreaterThanOrEqual(
            1440,
            $timeline[0]['ends_at']->diffInMinutes($timeline[1]['starts_at']),
        );
    }

    /** « Souvent commandé avec » propose ce que l'administrateur a associé. */
    public function test_suggestions_offer_what_goes_together(): void
    {
        TradeBundleSuggestion::create([
            'trade_id' => $this->peinture()->id,
            'suggested_trade_id' => $this->carrelage()->id,
            'default_sequence_gap_min' => 0,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $this->bundleJourney()->assertSee('Souvent commandé avec')
            ->assertSee('+ '.$this->carrelage()->name);
    }

    /** Retirer un service le sort du chantier, et ne laisse pas de questionnaire orphelin. */
    public function test_removing_a_service_clears_it_from_the_screen(): void
    {
        $component = $this->bundleJourney();
        $component->call('addService', $this->carrelage()->id);

        $item = OrderDraft::firstOrFail()->items()->where('trade_id', $this->carrelage()->id)->firstOrFail();
        $component->call('removeService', $item->id);

        $this->assertSame(1, OrderDraft::firstOrFail()->items()->count());
        $component->assertSet('tradeId', null);
    }

    /** Réordonner l'impossible est REFUSÉ, et le refus est écrit. */
    public function test_an_impossible_order_is_refused_out_loud(): void
    {
        TradeBundleSuggestion::create([
            'trade_id' => $this->peinture()->id,
            'suggested_trade_id' => $this->carrelage()->id,
            'default_sequence_gap_min' => 1440,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $component = $this->bundleJourney();
        $component->call('addService', $this->carrelage()->id);

        $items = OrderDraft::firstOrFail()->items()->orderBy('sequence')->pluck('id')->all();

        // On tente de faire passer le dépendant AVANT ce dont il dépend.
        $component->call('reorderServices', array_reverse($items));

        $component->assertSee('ne peut pas passer avant');

        // Et l'ordre n'a PAS bougé : le refus n'est pas qu'un message.
        $this->assertSame(
            $items,
            OrderDraft::firstOrFail()->items()->orderBy('sequence')->pluck('id')->all(),
        );
    }

    /** Un réordonnancement licite passe, lui. */
    public function test_a_legitimate_reorder_goes_through(): void
    {
        $component = $this->bundleJourney();
        $component->call('addService', $this->carrelage()->id);

        $items = OrderDraft::firstOrFail()->items()->orderBy('sequence')->pluck('id')->all();
        $component->call('reorderServices', array_reverse($items));

        $this->assertSame(
            array_reverse($items),
            OrderDraft::firstOrFail()->items()->orderBy('sequence')->pluck('id')->all(),
        );
    }

    /** Le devis consolidé montre un total ET le détail par métier. */
    public function test_the_quote_shows_a_total_and_the_detail_per_trade(): void
    {
        $component = $this->bundleJourney();
        $component->call('addService', $this->carrelage()->id);

        $component->assertSee('Total du chantier')
            ->assertSee($this->peinture()->name)
            ->assertSee($this->carrelage()->name);

        $quote = $component->get('bundleQuote');
        $this->assertCount(2, $quote['items']);
    }

    /** En dehors du multi-services, rien de tout cela n'encombre l'écran. */
    public function test_none_of_this_shows_outside_bundle_mode(): void
    {
        Livewire::test(OrderJourney::class)
            ->call('selectTrade', $this->peinture()->id)
            ->assertDontSee('Votre chantier');
    }

    // ─── Fabriques ───────────────────────────────────────────────────────────────────────────

    private function peinture(): Trade
    {
        return Trade::where('slug', 'peinture')->firstOrFail();
    }

    /** Un second métier autorisé en multi-services. */
    private function carrelage(): Trade
    {
        return Trade::where('slug', 'plumbing')->firstOrFail();
    }

    private function bundleJourney()
    {
        foreach ([$this->peinture(), $this->carrelage()] as $trade) {
            $trade->update(['allows_bundle' => true]);
        }

        $component = Livewire::actingAs(User::factory()->client()->create())
            ->test(OrderJourney::class)
            // L'ordre réel du client : il configure un métier, PUIS découvre qu'il lui en faut
            // plusieurs. Le mode multi-services n'est d'ailleurs proposé qu'une fois un métier
            // choisi — avant, on ne sait pas s'il l'autorise.
            ->call('selectTrade', $this->peinture()->id)
            ->call('setMode', OrderMode::BUNDLE);

        OrderDraft::firstOrFail()->update([
            'mode' => OrderMode::BUNDLE,
            'address' => 'Rue de la Loi 1, 1000 Bruxelles',
            'lat' => 50.8467,
            'lng' => 4.3525,
        ]);

        return $component;
    }
}
