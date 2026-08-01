<?php

namespace Tests\Feature\OrderEngine;

use App\Livewire\Admin\OrderEngine\QuestionnaireBuilder;
use App\Models\OrderDraft;
use App\Models\OrderDraftAnswer;
use App\Models\OrderDraftItem;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Trade;
use App\Models\User;
use App\Support\Domain\OrderMode;
use Database\Seeders\OrderEngineCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Le constructeur de parcours, vu du poste de l'administrateur.
 *
 * Ce que ces tests verrouillent, c'est ce qui rend l'écran DIGNE DE CONFIANCE entre les mains d'un
 * responsable non technique : le simulateur donne le vrai prix, le code d'une question se
 * verrouille dès qu'une commande le cite, l'archivage annonce son impact, et les avertissements du
 * validateur remontent jusqu'à l'écran.
 */
class QuestionnaireBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrderEngineCatalogSeeder::class);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    /**
     * Un non-admin est refusé par le COMPOSANT, pas seulement par la route.
     *
     * Cet écran écrit le catalogue, verrouille des codes et archive des questions. La middleware
     * `role:admin` le protège aujourd'hui ; la garantie doit survivre à un remaniement de routes,
     * et à tout montage du composant hors de `/admin/*`.
     *
     * Ce test existe parce que la suite du projet m'a pris en défaut : j'avais écrit le composant
     * sans le trait, et `AdminComponentGuardTest` l'a signalé.
     */
    public function test_a_non_admin_is_refused_by_the_component_itself(): void
    {
        $this->actingAs(User::factory()->client()->create());

        Livewire::test(QuestionnaireBuilder::class, ['trade' => $this->peinture()])
            ->assertForbidden();
    }

    public function test_it_opens_on_a_seeded_trade(): void
    {
        Livewire::test(QuestionnaireBuilder::class, ['trade' => $this->peinture()])
            ->assertOk()
            ->assertSee('Peinture')
            ->assertSee('Quelle surface à peindre ?');
    }

    /**
     * LE test qui justifie l'écran : le simulateur donne le prix RÉEL.
     *
     * L'administrateur répond dans l'aperçu, et voit le même montant que le client verra. Un
     * simulateur qui recalculerait de son côté finirait par diverger du moteur, et validerait une
     * grille tarifaire qui n'est pas celle qui sera appliquée.
     */
    public function test_the_simulator_builds_the_same_price_the_client_will_pay(): void
    {
        $component = Livewire::test(QuestionnaireBuilder::class, ['trade' => $this->peinture()]);

        foreach ([
            ['code' => 'surface_m2', 'value' => 40],
            ['code' => 'etendue', 'value' => 'murs_plafonds'],
            ['code' => 'etat_support', 'value' => 'bon'],
        ] as $answer) {
            $component->dispatch('question-answered', code: $answer['code'], value: $answer['value'], valid: true);
        }

        // 12 000 (base) + 40 × 250 (surface) + 4 500 (plafonds) = 26 500
        $this->assertSame(26500, $component->instance()->quote()->minCents);
    }

    /** Chaque euro rattaché à une ligne : c'est ce qui permet de repérer le zéro de trop. */
    public function test_the_simulator_explains_the_price_line_by_line(): void
    {
        $component = Livewire::test(QuestionnaireBuilder::class, ['trade' => $this->peinture()])
            ->dispatch('question-answered', code: 'etendue', value: 'murs_plafonds', valid: true);

        $lines = collect($component->instance()->quote()->lines);

        $this->assertTrue($lines->contains('code', 'etendue'));
        $this->assertSame(
            $component->instance()->quote()->minCents,
            $lines->sum('min_cents'),
        );
    }

    /** La majoration d'urgence doit se voir AVANT la mise en ligne, pas se découvrir en production. */
    public function test_switching_the_preview_to_the_immediate_mode_shows_the_surcharge(): void
    {
        $trade = Trade::where('slug', 'plumbing')->firstOrFail();

        $component = Livewire::test(QuestionnaireBuilder::class, ['trade' => $trade]);
        $scheduled = $component->instance()->quote()->minCents;

        $component->set('previewMode', OrderMode::ASAP);

        $this->assertGreaterThan($scheduled, $component->instance()->quote()->minCents);
    }

    /**
     * Le code se VERROUILLE dès qu'une commande le cite.
     *
     * C'est la clé sous laquelle les réponses sont enregistrées : le renommer rendrait
     * inexplicables tous les devis qui le citent. L'interface l'empêche, plutôt que de compter sur
     * la prudence de qui l'emploie.
     */
    public function test_a_used_question_code_can_no_longer_be_renamed(): void
    {
        $trade = $this->peinture();
        $question = $trade->questions()->where('code', 'surface_m2')->firstOrFail();
        $this->answerFor($trade, $question);

        $component = Livewire::test(QuestionnaireBuilder::class, ['trade' => $trade])
            ->call('edit', $question->id);

        $this->assertTrue($component->instance()->codeIsLocked());

        $component->set('form.code', 'autre_code')->call('save');

        $this->assertSame('surface_m2', $question->fresh()->code);
    }

    /** Tant qu'aucune commande ne le cite, le code reste modifiable. */
    public function test_an_unused_question_code_is_still_editable(): void
    {
        $trade = $this->peinture();
        $question = $trade->questions()->where('code', 'etat_support')->firstOrFail();

        Livewire::test(QuestionnaireBuilder::class, ['trade' => $trade])
            ->call('edit', $question->id)
            ->set('form.code', 'etat_des_murs')
            ->call('save');

        $this->assertSame('etat_des_murs', $question->fresh()->code);
    }

    /** Le libellé propose un code, mais ne le devine jamais deux fois. */
    public function test_the_label_suggests_a_code_for_a_new_question(): void
    {
        Livewire::test(QuestionnaireBuilder::class, ['trade' => $this->peinture()])
            ->call('startNew')
            ->set('form.label', 'Combien de pièces ?')
            ->assertSet('form.code', 'combien_de_pieces');
    }

    /** L'ordre s'enregistre immédiatement : un ordre à penser à sauver finit perdu. */
    public function test_reordering_is_persisted_at_once(): void
    {
        $trade = $this->peinture();
        $first = $trade->questions()->orderBy('sort_order')->first();

        Livewire::test(QuestionnaireBuilder::class, ['trade' => $trade])
            ->call('move', $first->id, 1);

        $this->assertNotSame(
            $first->id,
            $trade->questions()->orderBy('sort_order')->first()->id,
            'La question n’a pas bougé, ou l’ordre n’a pas été enregistré.',
        );
    }

    /** Désactiver n'est pas archiver : la question quitte le parcours, elle reste sous la main. */
    public function test_deactivating_keeps_the_question_available(): void
    {
        $trade = $this->peinture();
        $question = $trade->questions()->first();

        Livewire::test(QuestionnaireBuilder::class, ['trade' => $trade])
            ->call('toggleActive', $question->id);

        $this->assertFalse((bool) $question->fresh()->is_active);
        $this->assertNotNull(Question::find($question->id));
    }

    /** L'impact est annoncé AVANT. Qui le découvre après n'a plus de recours. */
    public function test_archiving_announces_its_impact_before_doing_anything(): void
    {
        $trade = $this->peinture();
        $question = $trade->questions()->where('code', 'surface_m2')->firstOrFail();
        $this->answerFor($trade, $question);

        $component = Livewire::test(QuestionnaireBuilder::class, ['trade' => $trade])
            ->call('confirmArchive', $question->id);

        $this->assertSame(1, $component->instance()->archiveImpact['used_count']);
        $component->assertSee('restent lisibles');

        // Rien n'a encore été archivé : l'annonce précède l'acte.
        $this->assertNotNull(Question::find($question->id));

        $component->call('archive');
        $this->assertNull(Question::find($question->id));
        $this->assertNotNull(Question::withTrashed()->find($question->id));
    }

    /** Les avertissements du validateur remontent jusqu'à l'écran, sinon ils ne servent à rien. */
    public function test_the_guardrails_surface_on_the_screen(): void
    {
        $trade = $this->peinture();
        $trade->questions()->first()->update(['allows_unknown' => false]);

        Livewire::test(QuestionnaireBuilder::class, ['trade' => $trade])
            ->assertSee('porte de sortie');
    }

    /** Deux défauts bloquent la publication, et l'écran le dit. */
    public function test_a_blocking_issue_prevents_publication(): void
    {
        $trade = $this->peinture();
        $question = $trade->questions()->where('code', 'etendue')->firstOrFail();
        $question->options()->update(['is_default' => true]);

        $component = Livewire::test(QuestionnaireBuilder::class, ['trade' => $trade]);

        $this->assertFalse($component->instance()->canPublish());
        $component->assertSee('publication bloquée');
    }

    /** Ajouter une réponse ne pose jamais deux défauts : le validateur refuserait la publication. */
    public function test_adding_an_option_never_creates_a_second_default(): void
    {
        $trade = $this->peinture();
        $question = $trade->questions()->where('code', 'etendue')->firstOrFail();

        Livewire::test(QuestionnaireBuilder::class, ['trade' => $trade])
            ->call('addOption', $question->id);

        $this->assertSame(1, $question->fresh()->options()->where('is_default', true)->count());
    }

    /** Poser un défaut retire l'autre — plutôt que de laisser le validateur refuser plus tard. */
    public function test_setting_a_default_clears_the_previous_one(): void
    {
        $trade = $this->peinture();
        $question = $trade->questions()->where('code', 'etendue')->firstOrFail();
        $other = $question->options()->where('is_default', false)->firstOrFail();

        Livewire::test(QuestionnaireBuilder::class, ['trade' => $trade])
            ->call('updateOption', $other->id, ['is_default' => true]);

        $this->assertSame(1, $question->fresh()->options()->where('is_default', true)->count());
        $this->assertTrue((bool) QuestionOption::find($other->id)->is_default);
    }

    /** Une nouvelle question naît avec sa porte de sortie ouverte : poser un mur doit être délibéré. */
    public function test_a_new_question_offers_a_way_out_by_default(): void
    {
        Livewire::test(QuestionnaireBuilder::class, ['trade' => $this->peinture()])
            ->call('startNew')
            ->assertSet('form.allows_unknown', true);
    }

    public function test_it_refuses_a_code_that_is_not_a_stable_key(): void
    {
        Livewire::test(QuestionnaireBuilder::class, ['trade' => $this->peinture()])
            ->call('startNew')
            ->set('form.label', 'Une question')
            ->set('form.code', 'Code Avec Espaces')
            ->call('save')
            ->assertHasErrors('form.code');
    }

    /** Un métier au devis obligatoire n'affiche pas de prix, même dans le simulateur. */
    public function test_a_quote_only_trade_shows_no_price_in_the_simulator(): void
    {
        Livewire::test(QuestionnaireBuilder::class, ['trade' => Trade::where('slug', 'roofing')->firstOrFail()])
            ->assertSee('devis obligatoire');
    }

    // ─── Fabriques ───────────────────────────────────────────────────────────────────────────

    private function peinture(): Trade
    {
        return Trade::where('slug', 'peinture')->firstOrFail();
    }

    private function answerFor(Trade $trade, Question $question): void
    {
        $draft = OrderDraft::create([
            'reference' => OrderDraft::generateReference(),
            'mode' => OrderMode::SCHEDULED,
            'status' => 'draft',
        ]);
        $item = OrderDraftItem::create(['order_draft_id' => $draft->id, 'trade_id' => $trade->id]);

        OrderDraftAnswer::create([
            'order_draft_item_id' => $item->id,
            'question_id' => $question->id,
            'question_code' => $question->code,
            'question_label_snapshot' => $question->label,
            'answer_label_snapshot' => '40 m²',
            'price_impact_cents' => 10000,
        ]);
    }
}
