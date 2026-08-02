<?php

namespace Tests\Feature\OrderEngine;

use App\Livewire\OrderEngine\OrderJourney;
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
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Une date par métier, quand une seule ne suffit pas.
 *
 * Le chantier proposait une séquence calculée : tout s'enchaînait depuis une date unique. C'est le
 * bon défaut — le client n'a pas à orchestrer ses artisans — mais pas toujours la réalité. Le
 * plombier passe mardi parce qu'il n'a que mardi, et le carreleur suit.
 *
 * CE QU'ON REFUSE. Épingler le carreleur AVANT la fin du plombier produit un chantier impossible.
 * Le composeur refuse déjà un réordonnancement qui violerait une dépendance plutôt que de le
 * corriger en silence — une date épinglée suit la même règle : on dit non, et on dit pourquoi.
 */
class BundlePerTradeDateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrderEngineCatalogSeeder::class);
    }

    public function test_a_pinned_date_drives_that_trade_start(): void
    {
        $draft = $this->bundle();
        $composer = app(BundleComposer::class);

        $plomberie = $composer->addTrade($draft, $this->trade('plumbing'));
        $composer->addTrade($draft, $this->trade('peinture'));

        $pinned = Carbon::now()->addDays(9)->setTime(14, 0);
        $composer->pinItemDate($draft, $plomberie, $pinned);

        $line = $composer->timeline($draft->fresh())->firstWhere('item.id', $plomberie->id);

        $this->assertSame(
            $pinned->format('Y-m-d H:i'),
            $line['starts_at']->format('Y-m-d H:i'),
            'La date épinglée n’a pas été retenue comme début.',
        );
    }

    /** Les métiers non épinglés continuent de s'enchaîner tout seuls. */
    public function test_unpinned_trades_still_follow_the_sequence(): void
    {
        $draft = $this->bundle();
        $composer = app(BundleComposer::class);

        $composer->addTrade($draft, $this->trade('plumbing'));
        $second = $composer->addTrade($draft, $this->trade('peinture'));

        $timeline = $composer->timeline($draft->fresh());
        $line = $timeline->firstWhere('item.id', $second->id);

        $this->assertNotNull($line['starts_at']);
    }

    /**
     * Un métier ne se pose pas AVANT ce dont il dépend.
     *
     * Le carreleur avant le plombier, c'est un chantier impossible. Corriger en silence serait pire
     * que refuser : le client croirait sa date prise en compte et découvrirait autre chose.
     */
    public function test_a_date_before_the_dependency_is_refused(): void
    {
        $draft = $this->bundle();
        $composer = app(BundleComposer::class);

        $plomberie = $composer->addTrade($draft, $this->trade('plumbing'));
        $carrelage = $composer->addTrade($draft, $this->trade('peinture'));

        $carrelage->update(['depends_on_item_id' => $plomberie->id]);
        $composer->pinItemDate($draft, $plomberie, Carbon::now()->addDays(9)->setTime(14, 0));

        $this->expectException(ValidationException::class);

        $composer->pinItemDate($draft->fresh(), $carrelage->fresh(), Carbon::now()->addDays(8)->setTime(9, 0));
    }

    /** Une date passée est refusée : on ne planifie pas hier. */
    public function test_a_past_date_is_refused(): void
    {
        $draft = $this->bundle();
        $composer = app(BundleComposer::class);
        $item = $composer->addTrade($draft, $this->trade('plumbing'));

        $this->expectException(ValidationException::class);

        $composer->pinItemDate($draft, $item, Carbon::now()->subDay());
    }

    /** On revient à la séquence automatique sans avoir à tout refaire. */
    public function test_the_pin_can_be_released(): void
    {
        $draft = $this->bundle();
        $composer = app(BundleComposer::class);
        $item = $composer->addTrade($draft, $this->trade('plumbing'));

        $composer->pinItemDate($draft, $item, Carbon::now()->addDays(9)->setTime(14, 0));
        $composer->releaseItemDate($draft->fresh(), $item->fresh());

        $this->assertNull($item->fresh()->scheduled_at);
    }

    /** L'écran câble ce qu'il propose — huitième fois que ce module l'oublie. */
    public function test_the_screen_wires_the_per_trade_date(): void
    {
        /*
         * Le chantier est composé PAR LE COMPOSANT, pas à côté.
         *
         * Un brouillon fabriqué sur un autre jeton de session n'est pas celui que l'écran ouvre :
         * la timeline resterait vide et le test passerait à côté du rendu qu'il prétend vérifier.
         */
        $component = Livewire::withQueryParams(['mode' => 'bundle'])
            ->test(OrderJourney::class)
            ->call('selectTrade', $this->trade('plumbing')->id);

        // Au repos : le champ qui POSE une date.
        $this->assertStringContainsString('pinItemDate', $component->html());

        // Une fois la date posée : le retour à la séquence automatique.
        $item = OrderDraftItem::query()->latest('id')->firstOrFail();
        $after = $component
            ->call('pinItemDate', $item->id, Carbon::now()->addDays(9)->format('Y-m-d'))
            ->html();

        $this->assertStringContainsString(
            'releaseItemDate',
            $after,
            'Une date posée sans moyen de revenir à la séquence enferme le client dans son choix.',
        );
    }

    private function bundle(): OrderDraft
    {
        return app(OrderDraftManager::class)->resumeOrCreate('jeton-'.uniqid(), null, OrderMode::BUNDLE);
    }

    private function trade(string $slug): Trade
    {
        return Trade::where('slug', $slug)->firstOrFail();
    }
}
