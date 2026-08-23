<?php

namespace Tests\Feature\OrderEngine;

use App\Models\OrderDraft;
use App\Models\OrderDraftItem;
use App\Models\Trade;
use App\Services\OrderEngine\BundleComposer;
use App\Services\OrderEngine\OrderDraftManager;
use App\Support\Domain\OrderMode;
use Database\Seeders\OrderEngineCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Le panier multi-métiers : une commande, plusieurs professionnels, un seul suivi. */
class BundleComposerTest extends TestCase
{
    use RefreshDatabase;

    private BundleComposer $composer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrderEngineCatalogSeeder::class);
        $this->composer = app(BundleComposer::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ─── Composition ─────────────────────────────────────────────────────────────────────────

    public function test_a_bundle_holds_several_trades_in_one_order(): void
    {
        $draft = $this->bundle();

        $this->composer->addTrade($draft, $this->trade('plumbing'));
        $this->composer->addTrade($draft, $this->trade('electrical'));

        $this->assertSame(2, $draft->fresh()->items()->count());
    }

    /** L'adresse n'est demandée QU'UNE FOIS. */
    public function test_the_address_is_never_asked_twice(): void
    {
        $draft = $this->bundle();
        $draft->update(['address' => 'Rue de la Loi 1, 1000 Bruxelles', 'lat' => 50.8467, 'lng' => 4.3525]);

        $this->composer->addTrade($draft, $this->trade('plumbing'));
        $this->composer->addTrade($draft, $this->trade('peinture'));

        $fresh = $draft->fresh();
        $this->assertSame('Rue de la Loi 1, 1000 Bruxelles', $fresh->address);
        $this->assertSame(2, $fresh->items()->count());

        // Aucune ligne ne porte d'adresse propre : il n'y a qu'un seul endroit où la lire.
        // Tous les articles fautifs d'un coup : un panier de cinq lignes demanderait sinon cinq
        // executions pour etre nettoye.
        $porteurs = [];

        foreach ($fresh->items as $item) {
            if (array_key_exists('address', $item->getAttributes())) {
                $porteurs[] = 'article #'.$item->id;
            }
        }

        $this->assertSame([], $porteurs, 'Ces articles portent une adresse : elle appartient au panier, pas a la ligne.');
    }

    /** Les suggestions viennent de l'administrateur, et écartent ce qui est déjà au panier. */
    public function test_suggestions_exclude_what_is_already_there(): void
    {
        $draft = $this->bundle();
        $this->composer->addTrade($draft, $this->trade('plumbing'));

        $slugs = $this->composer->suggestionsFor($draft->fresh())->pluck('trade.slug');
        $this->assertTrue($slugs->contains('electrical'));

        $this->composer->addTrade($draft->fresh(), $this->trade('electrical'));

        $this->assertFalse(
            $this->composer->suggestionsFor($draft->fresh())->pluck('trade.slug')->contains('electrical'),
            'Un métier déjà au panier est encore proposé : toutes les suggestions deviennent suspectes.',
        );
    }

    /** Deux métiers peuvent suggérer le même troisième : il n'est proposé qu'une fois. */
    public function test_a_trade_suggested_twice_appears_once(): void
    {
        $draft = $this->bundle();
        $this->composer->addTrade($draft, $this->trade('plumbing'));
        $this->composer->addTrade($draft->fresh(), $this->trade('peinture'));

        $suggestions = $this->composer->suggestionsFor($draft->fresh())->pluck('trade.slug');

        $this->assertSame($suggestions->count(), $suggestions->unique()->count());
    }

    // ─── Ordonnancement ──────────────────────────────────────────────────────────────────────

    /** LA règle du chantier : le nettoyage de fin de chantier attend la plomberie, et il attend le délai configuré par l'administrateur. */
    public function test_a_dependent_trade_waits_for_the_one_before_it(): void
    {
        $draft = $this->bundle();
        $plumbing = $this->composer->addTrade($draft, $this->trade('plumbing'));
        $cleaning = $this->composer->addTrade($draft->fresh(), $this->trade('nettoyage-fin-chantier'));

        $this->assertSame($plumbing->id, $cleaning->depends_on_item_id);
        $this->assertSame(1440, $cleaning->sequence_gap_min);
    }

    /** Une association SANS délai n'est pas une dépendance. */
    public function test_a_suggestion_without_a_delay_is_not_a_dependency(): void
    {
        $draft = $this->bundle();
        $this->composer->addTrade($draft, $this->trade('plumbing'));
        $electrical = $this->composer->addTrade($draft->fresh(), $this->trade('electrical'));

        $this->assertNull($electrical->depends_on_item_id);
    }

    /** LE décalage se PROPAGE. */
    public function test_the_delay_propagates_down_the_timeline(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-01 08:00:00'));

        $draft = $this->bundle();
        $draft->update(['scheduled_at' => Carbon::parse('2026-09-02 08:00:00')]);

        $this->composer->addTrade($draft, $this->trade('plumbing'));               // 90 min
        $this->composer->addTrade($draft->fresh(), $this->trade('nettoyage-fin-chantier')); // +1440 min

        $timeline = $this->composer->timeline($draft->fresh());

        $plumbing = $timeline->firstWhere('trade.slug', 'plumbing');
        $cleaning = $timeline->firstWhere('trade.slug', 'nettoyage-fin-chantier');

        $this->assertSame('2026-09-02 08:00', $plumbing['starts_at']->format('Y-m-d H:i'));
        $this->assertSame('2026-09-02 09:30', $plumbing['ends_at']->format('Y-m-d H:i'));
        // Fin de la plomberie + 24 h de séchage.
        $this->assertSame('2026-09-03 09:30', $cleaning['starts_at']->format('Y-m-d H:i'));
        $this->assertSame('Plomberie', $cleaning['waits_for']);
    }

    /** Sans dépendance, les interventions s'enchaînent simplement. */
    public function test_independent_trades_follow_one_another(): void
    {
        $draft = $this->bundle();
        $draft->update(['scheduled_at' => Carbon::parse('2026-09-02 08:00:00')]);

        $this->composer->addTrade($draft, $this->trade('plumbing'));    // 90 min
        $this->composer->addTrade($draft->fresh(), $this->trade('electrical')); // 90 min

        $timeline = $this->composer->timeline($draft->fresh());

        $this->assertSame('2026-09-02 09:30', $timeline->last()['starts_at']->format('Y-m-d H:i'));
    }

    /** Le client réordonne ce qui peut l'être, et se voit REFUSER ce qui casserait le chantier. */
    public function test_reordering_is_refused_when_it_breaks_the_site(): void
    {
        $draft = $this->bundle();
        $plumbing = $this->composer->addTrade($draft, $this->trade('plumbing'));
        $cleaning = $this->composer->addTrade($draft->fresh(), $this->trade('nettoyage-fin-chantier'));

        $this->expectException(ValidationException::class);
        $this->composer->reorder($draft->fresh(), [$cleaning->id, $plumbing->id]);
    }

    public function test_reordering_is_allowed_when_nothing_depends_on_anything(): void
    {
        $draft = $this->bundle();
        $plumbing = $this->composer->addTrade($draft, $this->trade('plumbing'));
        $electrical = $this->composer->addTrade($draft->fresh(), $this->trade('electrical'));

        $this->composer->reorder($draft->fresh(), [$electrical->id, $plumbing->id]);

        $this->assertSame($electrical->id, $draft->fresh()->items()->orderBy('sequence')->first()->id);
    }

    /** Retirer un métier ne laisse pas de dépendance orpheline. */
    public function test_removing_a_trade_reattaches_what_depended_on_it(): void
    {
        $draft = $this->bundle();
        $painting = $this->composer->addTrade($draft, $this->trade('peinture'));
        $cleaning = $this->composer->addTrade($draft->fresh(), $this->trade('nettoyage-fin-chantier'));

        $this->assertSame($painting->id, $cleaning->depends_on_item_id);

        $this->composer->removeTrade($draft->fresh(), $painting);

        $this->assertNull(OrderDraftItem::find($cleaning->id)->depends_on_item_id);
        $this->assertSame(0, OrderDraftItem::find($cleaning->id)->sequence_gap_min);
    }

    // ─── Devis consolidé ─────────────────────────────────────────────────────────────────────

    /** Un seul total, le détail par métier, et la remise VISIBLE. */
    public function test_the_quote_is_consolidated_with_a_visible_discount(): void
    {
        $draft = $this->bundle();

        foreach (['plumbing', 'electrical', 'peinture'] as $slug) {
            $this->composer->addTrade($draft->fresh(), $this->trade($slug));
        }

        $result = $this->composer->consolidatedQuote($draft->fresh());

        $this->assertSame(3, $result['items']->count());

        $discount = collect($result['order']->lines)->firstWhere('code', '_bundle_discount');
        $this->assertNotNull($discount, 'La remise groupée n’apparaît pas : invisible, elle ne décide de rien.');
        $this->assertStringContainsString('3 services', $discount['detail']);

        // Le total consolidé vaut bien la somme des lignes, remise comprise.
        $this->assertSame(
            $result['order']->minCents,
            collect($result['order']->lines)->sum('min_cents'),
        );
    }

    /** Chaque métier garde son propre détail : le devis se déplie ligne par ligne. */
    public function test_each_trade_keeps_its_own_detail(): void
    {
        $draft = $this->bundle();
        $this->composer->addTrade($draft, $this->trade('plumbing'));
        $this->composer->addTrade($draft->fresh(), $this->trade('peinture'));

        $result = $this->composer->consolidatedQuote($draft->fresh());

        // Tous les métiers sans détail d'un coup : un devis groupé en compte plusieurs, et savoir
        // que le premier est muet ne dit rien des suivants.
        $sansDetail = [];

        foreach ($result['items'] as $line) {
            if (empty($line['quote']->lines)) {
                $sansDetail[] = $line['trade']->name;
            }
        }

        $this->assertSame([], $sansDetail, 'Ces métiers ne détaillent pas leur devis.');
    }

    // ─── Fabriques ───────────────────────────────────────────────────────────────────────────

    private function bundle(): OrderDraft
    {
        return app(OrderDraftManager::class)->resumeOrCreate('jeton-'.uniqid(), null, OrderMode::BUNDLE);
    }

    private function trade(string $slug): Trade
    {
        return Trade::where('slug', $slug)->firstOrFail();
    }
}
