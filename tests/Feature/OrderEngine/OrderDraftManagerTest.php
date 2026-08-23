<?php

namespace Tests\Feature\OrderEngine;

use App\Models\Trade;
use App\Models\User;
use App\Services\OrderEngine\OrderDraftManager;
use App\Support\Domain\OrderDraftStatus;
use App\Support\Domain\OrderMode;
use Database\Seeders\OrderEngineCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Le panier : deux lois du parcours, rendues vérifiables. */
class OrderDraftManagerTest extends TestCase
{
    use RefreshDatabase;

    private OrderDraftManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrderEngineCatalogSeeder::class);
        $this->manager = app(OrderDraftManager::class);
    }

    /** Loi 1 — un panier existe, et se chiffre, sans le moindre compte. */
    public function test_a_visitor_gets_a_priced_basket_without_an_account(): void
    {
        $draft = $this->manager->resumeOrCreate('jeton-visiteur');

        $this->assertNull($draft->client_id);
        $this->assertSame('jeton-visiteur', $draft->session_token);

        $item = $this->manager->itemFor($draft, $this->peinture());
        $this->manager->saveAnswers($item, $this->questions(), ['surface_m2' => 40, 'etendue' => 'murs_plafonds']);

        // 12 000 + 40 × 250 + 4 500 = 26 500
        $this->assertSame(26500, $this->manager->reprice($draft->fresh())->minCents);
    }

    /** Loi 10 — trois heures plus tard, dans un autre onglet, le panier est là. */
    public function test_the_same_visitor_finds_the_same_basket(): void
    {
        $first = $this->manager->resumeOrCreate('jeton-visiteur');
        $second = $this->manager->resumeOrCreate('jeton-visiteur');

        $this->assertSame($first->id, $second->id);
    }

    /** Le panier anonyme suit le client qui se connecte. */
    public function test_an_anonymous_basket_follows_the_client_who_signs_in(): void
    {
        $draft = $this->manager->resumeOrCreate('jeton-visiteur');
        $client = User::factory()->client()->create();

        $resumed = $this->manager->resumeOrCreate('jeton-visiteur', $client);

        $this->assertSame($draft->id, $resumed->id);
        $this->assertSame($client->id, $resumed->client_id);
    }

    /** Un compte prime sur un jeton : on retrouve son panier depuis un autre appareil. */
    public function test_a_signed_in_client_finds_their_basket_from_another_device(): void
    {
        $client = User::factory()->client()->create();
        $draft = $this->manager->resumeOrCreate('appareil-A', $client);

        $this->assertSame($draft->id, $this->manager->resumeOrCreate('appareil-B', $client)->id);
    }

    /** Rouvrir le même métier ne crée pas un doublon dans le panier. */
    public function test_adding_the_same_trade_twice_keeps_one_line(): void
    {
        $draft = $this->manager->resumeOrCreate('jeton');

        $this->manager->itemFor($draft, $this->peinture());
        $this->manager->itemFor($draft, $this->peinture());

        $this->assertSame(1, $draft->fresh()->items()->count());
    }

    /** L'INSTANTANÉ : on enregistre ce que le client a vu. */
    public function test_each_answer_keeps_a_human_readable_snapshot(): void
    {
        $draft = $this->manager->resumeOrCreate('jeton');
        $item = $this->manager->itemFor($draft, $this->peinture());

        $this->manager->saveAnswers($item, $this->questions(), ['etendue' => 'murs_plafonds']);

        $answer = $item->fresh()->answers()->where('question_code', 'etendue')->firstOrFail();

        $this->assertSame('Que faut-il peindre ?', $answer->question_label_snapshot);
        $this->assertSame('Murs et plafonds', $answer->answer_label_snapshot);
        $this->assertSame(4500, $answer->price_impact_cents);
    }

    /** Chaque euro rattaché à une réponse : la somme des impacts et le total doivent concorder. */
    public function test_the_stored_quote_is_explainable_line_by_line(): void
    {
        $draft = $this->manager->resumeOrCreate('jeton');
        $item = $this->manager->itemFor($draft, $this->peinture());

        $this->manager->saveAnswers($item, $this->questions(), [
            'surface_m2' => 40,
            'etendue' => 'murs_plafonds',
        ]);

        $fresh = $item->fresh(['answers']);
        $base = (int) $this->peinture()->base_price_cents;

        $this->assertSame(
            $fresh->estimate_min_cents,
            $base + $fresh->answers->sum('price_impact_cents'),
            'Le total stocké ne se reconstitue pas depuis les réponses : le devis n’est pas explicable.',
        );
    }

    /** LA garantie contre le devis fantôme. Une question devenue cachée voit sa réponse SUPPRIMÉE. */
    public function test_an_answer_to_a_hidden_question_is_removed_not_kept(): void
    {
        $draft = $this->manager->resumeOrCreate('jeton');
        $item = $this->manager->itemFor($draft, $this->peinture());

        // Au pistolet : la question du type de pistolet s'affiche et se répond.
        $this->manager->saveAnswers($item, $this->questions(), [
            'application' => 'pistolet',
            'type_pistolet' => 'airless',
        ]);
        $this->assertNotNull($item->fresh()->answers()->where('question_code', 'type_pistolet')->first());

        // Le client revient sur le rouleau : la question disparaît, et sa réponse avec.
        $this->manager->saveAnswers($item->fresh(), $this->questions(), [
            'application' => 'rouleau',
            'type_pistolet' => 'airless',
        ]);

        $this->assertNull(
            $item->fresh()->answers()->where('question_code', 'type_pistolet')->first(),
            'Le supplément d’une question cachée reste au devis : le client le contesterait à raison.',
        );
    }

    /** La porte de sortie s'enregistre comme telle, et se relit sur le devis. */
    public function test_a_way_out_answer_is_stored_as_such(): void
    {
        $draft = $this->manager->resumeOrCreate('jeton');
        $item = $this->manager->itemFor($draft, $this->peinture());

        $this->manager->saveAnswers($item, $this->questions(), ['etendue' => ['unknown' => true]]);

        $answer = $item->fresh()->answers()->where('question_code', 'etendue')->firstOrFail();

        $this->assertTrue((bool) $answer->is_unknown);
        $this->assertSame('À évaluer sur place', $answer->answer_label_snapshot);
    }

    /** Les réponses relues alimentent le moteur sans perte : aller-retour complet. */
    public function test_stored_answers_feed_the_engine_back_unchanged(): void
    {
        $draft = $this->manager->resumeOrCreate('jeton');
        $item = $this->manager->itemFor($draft, $this->peinture());

        $this->manager->saveAnswers($item, $this->questions(), [
            'surface_m2' => 40,
            'etendue' => 'murs_plafonds',
        ]);

        $reread = $this->manager->answersOf($item->fresh(['answers']));

        $this->assertSame('murs_plafonds', $reread['etendue']);
        $this->assertEquals(40, $reread['surface_m2']);
    }

    /** Le mode multi-services consolide, et la remise apparaît sur le devis. */
    public function test_a_multi_trade_basket_applies_its_discount(): void
    {
        $draft = $this->manager->resumeOrCreate('jeton', null, OrderMode::BUNDLE);

        foreach (['peinture', 'plumbing', 'electrical'] as $slug) {
            $trade = Trade::where('slug', $slug)->firstOrFail();
            $this->manager->itemFor($draft, $trade);
        }

        $order = $this->manager->reprice($draft->fresh());

        $this->assertTrue(
            collect($order->lines)->contains('code', '_bundle_discount'),
            'La remise multi-services n’apparaît pas : invisible, elle ne décide de rien.',
        );
    }

    /** Deux paniers ne partagent jamais une référence — elle se dicte au téléphone. */
    public function test_references_do_not_collide(): void
    {
        $references = collect(range(1, 25))
            ->map(fn ($i) => $this->manager->resumeOrCreate('jeton-'.$i)->reference);

        $this->assertSame($references->count(), $references->unique()->count());
    }

    /** Un panier converti n'est plus repris : le client suivant en ouvre un neuf. */
    public function test_a_converted_basket_is_not_resumed(): void
    {
        $draft = $this->manager->resumeOrCreate('jeton');
        $draft->update(['status' => OrderDraftStatus::CONVERTED]);

        $this->assertNotSame($draft->id, $this->manager->resumeOrCreate('jeton')->id);
    }

    // ─── Fabriques ───────────────────────────────────────────────────────────────────────────

    private function peinture(): Trade
    {
        return Trade::where('slug', 'peinture')->firstOrFail();
    }

    private function questions()
    {
        return $this->peinture()->questions()->with(['options', 'conditions'])->get();
    }
}
