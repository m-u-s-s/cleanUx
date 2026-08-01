<?php

namespace Tests\Feature\OrderEngine;

use App\Models\OrderDraft;
use App\Models\OrderDraftAnswer;
use App\Models\OrderDraftItem;
use App\Models\Question;
use App\Models\QuestionCondition;
use App\Models\QuestionOption;
use App\Models\Sector;
use App\Models\Trade;
use App\Services\OrderEngine\CatalogArchiver;
use App\Services\OrderEngine\QuestionnaireValidator;
use App\Support\Domain\ConditionAction;
use App\Support\Domain\ConditionOperator;
use App\Support\Domain\QuestionType;
use Database\Seeders\OrderEngineCatalogSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gouvernance du catalogue : archiver sans détruire, et avertir avant de laisser nuire.
 *
 * Ces deux services sont ce qui rend le constructeur de parcours confiable entre les mains d'un
 * responsable non technique. L'un garantit qu'aucun geste d'administration ne peut rendre un devis
 * illisible ; l'autre rend visibles les défauts qui ne lèvent aucune erreur mais coûtent des
 * clients.
 */
class CatalogGovernanceTest extends TestCase
{
    use RefreshDatabase;

    private CatalogArchiver $archiver;

    private QuestionnaireValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->archiver = app(CatalogArchiver::class);
        $this->validator = app(QuestionnaireValidator::class);
    }

    // ─── Archivage ───────────────────────────────────────────────────────────────────────────

    /**
     * LE critère d'acceptation : archiver une question ne casse aucune commande passée.
     *
     * Le devis reste lisible intégralement — libellé de la question, libellé de la réponse, et
     * l'euro exact qu'elle a coûté.
     */
    public function test_archiving_a_question_leaves_past_quotes_fully_readable(): void
    {
        [$trade, $question] = $this->tradeWithQuestion();
        $answer = $this->answerFor($trade, $question, '42 m²', 8400);

        $this->archiver->archive($question);

        $answer->refresh();
        $this->assertSame('Quelle surface ?', $answer->question_label_snapshot);
        $this->assertSame('42 m²', $answer->answer_label_snapshot);
        $this->assertSame(8400, $answer->price_impact_cents);
        $this->assertSame('surface_m2', $answer->question_code);
    }

    /** Archiver n'est jamais supprimer : la ligne existe encore, rangée. */
    public function test_archiving_never_destroys_the_row(): void
    {
        [, $question] = $this->tradeWithQuestion();

        $this->archiver->archive($question);

        $this->assertNull(Question::find($question->id), 'La question doit disparaître des écrans courants.');
        $this->assertNotNull(
            Question::withTrashed()->find($question->id),
            'La question a été détruite : les devis qui la citent deviennent inexplicables.',
        );
    }

    /** Retirer du parcours et ranger des écrans sont deux gestes : l'archivage fait les deux. */
    public function test_archiving_also_takes_the_entry_out_of_the_client_journey(): void
    {
        [, $question] = $this->tradeWithQuestion();

        $this->archiver->archive($question);

        $this->assertFalse((bool) Question::withTrashed()->find($question->id)->is_active);
    }

    /** Rien n'est perdu : tout se rouvre. */
    public function test_an_archived_question_can_come_back(): void
    {
        [, $question] = $this->tradeWithQuestion();
        $this->archiver->archive($question);

        $this->archiver->restore(Question::withTrashed()->findOrFail($question->id));

        $restored = Question::find($question->id);
        $this->assertNotNull($restored);
        $this->assertTrue((bool) $restored->is_active);
    }

    public function test_the_impact_reports_how_many_orders_used_the_question(): void
    {
        [$trade, $question] = $this->tradeWithQuestion();
        $this->answerFor($trade, $question, '42 m²', 8400);
        $this->answerFor($trade, $question, '80 m²', 16800);

        $impact = $this->archiver->impactOf($question);

        $this->assertSame(2, $impact['used_count']);
        $this->assertStringContainsString('2 fois', $impact['summary']);
        $this->assertStringContainsString('restent lisibles', $impact['summary']);
    }

    /**
     * Un code archivé reste réservé à vie, et c'est une protection, pas une limitation.
     *
     * Les réponses sont indexées par CODE. Autoriser un administrateur à réécrire « surface_m2 »
     * six mois plus tard, avec un autre sens, rendrait les instantanés historiques ambigus : la
     * même clé désignerait deux questions différentes selon la date, et plus aucun devis ancien ne
     * serait interprétable avec certitude.
     *
     * Ce test est né d'une erreur de ma part : j'avais écrit l'inverse — un test qui supposait la
     * recréation possible — et la base l'a démenti. La contrainte protégeait déjà ce que je
     * croyais devoir construire.
     */
    public function test_an_archived_code_stays_reserved_forever(): void
    {
        [$trade, $question] = $this->tradeWithQuestion();
        $this->answerFor($trade, $question, '42 m²', 8400);

        $this->archiver->archive($question);

        $this->expectException(UniqueConstraintViolationException::class);

        Question::create([
            'trade_id' => $trade->id,
            'code' => 'surface_m2',
            'label' => 'Quelle surface à traiter ?',
            'type' => QuestionType::SURFACE,
        ]);
    }

    /** Le même code reste libre sur un AUTRE métier : la réservation est propre à chacun. */
    public function test_the_same_code_lives_freely_on_another_trade(): void
    {
        [, $question] = $this->tradeWithQuestion();
        $this->archiver->archive($question);

        $other = $this->trade();
        $twin = Question::create([
            'trade_id' => $other->id,
            'code' => 'surface_m2',
            'label' => 'Quelle surface ?',
            'type' => QuestionType::SURFACE,
        ]);

        $this->assertNotNull($twin->id);
        // Et son historique lui est propre : il ne récupère pas les réponses du métier voisin.
        $this->assertSame(0, $this->archiver->impactOf($twin)['used_count']);
    }

    /** Une question dont d'autres dépendent : l'avertissement doit le dire avant, pas après. */
    public function test_the_impact_warns_when_other_questions_depend_on_it(): void
    {
        [$trade, $question] = $this->tradeWithQuestion();
        $dependent = Question::create([
            'trade_id' => $trade->id, 'code' => 'detail', 'label' => 'Détail',
            'type' => QuestionType::SINGLE_CHOICE,
        ]);
        QuestionCondition::create([
            'question_id' => $dependent->id,
            'depends_on_question_id' => $question->id,
            'operator' => ConditionOperator::IS_ANSWERED,
            'action' => ConditionAction::SHOW,
        ]);

        $this->assertStringContainsString('dépend', $this->archiver->impactOf($question)['summary']);
    }

    /** Archiver un secteur laisse ses métiers intacts — et l'annonce. */
    public function test_archiving_a_sector_spares_its_trades(): void
    {
        $this->seed(OrderEngineCatalogSeeder::class);
        $sector = Sector::where('slug', 'nettoyage')->firstOrFail();
        $tradeIds = $sector->trades()->pluck('id');

        $impact = $this->archiver->impactOf($sector);
        $this->archiver->archive($sector);

        $this->assertSame(3, $impact['children_count']);
        $this->assertSame(3, Trade::whereIn('id', $tradeIds)->count(), 'Les métiers ont été emportés avec leur secteur.');
    }

    /** Une option se désactive : elle n'est plus proposée, les commandes gardent son libellé. */
    public function test_archiving_an_option_only_deactivates_it(): void
    {
        [, $question] = $this->tradeWithQuestion();
        $option = QuestionOption::create([
            'question_id' => $question->id, 'label' => 'Grande surface', 'value' => 'grande',
        ]);

        $this->archiver->archive($option);

        $this->assertFalse((bool) $option->fresh()->is_active);
        $this->assertNotNull(QuestionOption::find($option->id));
    }

    // ─── Garde-fous du constructeur ──────────────────────────────────────────────────────────

    /** Le catalogue livré ne doit déclencher aucun avertissement : il sert de référence. */
    public function test_the_seeded_catalog_passes_its_own_guardrails(): void
    {
        $this->seed(OrderEngineCatalogSeeder::class);

        foreach (Trade::whereNotNull('sector_id')->get() as $trade) {
            $issues = $this->validator->inspect($trade);

            $this->assertEmpty(
                $issues,
                "Le métier « {$trade->name} » déclenche : ".collect($issues)->pluck('message')->implode(' | '),
            );
        }
    }

    /** Loi 3 — au-delà de dix questions, l'administrateur doit être averti. */
    public function test_a_long_questionnaire_raises_a_warning(): void
    {
        $trade = $this->trade();

        foreach (range(1, 12) as $i) {
            Question::create([
                'trade_id' => $trade->id, 'code' => 'q'.$i, 'label' => 'Question '.$i,
                'type' => QuestionType::TEXT, 'sort_order' => $i,
            ]);
        }

        $codes = collect($this->validator->inspect($trade))->pluck('code');

        $this->assertTrue($codes->contains('trade_too_long'));
        $this->assertTrue($codes->contains('step_too_long'));
    }

    /** Loi 6 — une question sans échappatoire est signalée. */
    public function test_a_question_without_a_way_out_is_flagged(): void
    {
        $trade = $this->trade();
        Question::create([
            'trade_id' => $trade->id, 'code' => 'mur', 'label' => 'Question bloquante',
            'type' => QuestionType::TEXT, 'allows_unknown' => false,
        ]);

        $this->assertTrue(collect($this->validator->inspect($trade))->pluck('code')->contains('no_way_out'));
    }

    /** Photo et adresse sont exemptées : l'une est facultative, l'autre indispensable. */
    public function test_photo_and_address_need_no_way_out(): void
    {
        $trade = $this->trade();
        foreach ([QuestionType::PHOTO, QuestionType::ADDRESS] as $i => $type) {
            Question::create([
                'trade_id' => $trade->id, 'code' => 'q'.$i, 'label' => 'Q'.$i,
                'type' => $type, 'allows_unknown' => false,
            ]);
        }

        $this->assertEmpty(collect($this->validator->inspect($trade))->where('code', 'no_way_out'));
    }

    /**
     * Deux défauts sur la même question BLOQUENT la publication.
     *
     * Le défaut ne se voit pas en base : l'écran dépendrait de l'ordre de tri, et le client
     * validerait une réponse qu'il n'a pas choisie.
     */
    public function test_two_defaults_on_one_question_block_publication(): void
    {
        $trade = $this->trade();
        $question = Question::create([
            'trade_id' => $trade->id, 'code' => 'choix', 'label' => 'Un choix',
            'type' => QuestionType::SINGLE_CHOICE,
        ]);
        QuestionOption::create(['question_id' => $question->id, 'label' => 'A', 'value' => 'a', 'is_default' => true]);
        QuestionOption::create(['question_id' => $question->id, 'label' => 'B', 'value' => 'b', 'is_default' => true]);

        $issue = collect($this->validator->inspect($trade))->firstWhere('code', 'multiple_defaults');

        $this->assertNotNull($issue);
        $this->assertSame(QuestionnaireValidator::SEVERITY_ERROR, $issue['severity']);
        $this->assertFalse($this->validator->canPublish($trade));
    }

    /**
     * LA détection qui bloque : deux questions qui s'attendent l'une l'autre.
     *
     * Ni l'une ni l'autre ne s'affichera jamais. C'est le seul défaut qui ne dégrade pas le
     * parcours mais en supprime silencieusement une partie.
     */
    public function test_a_circular_dependency_blocks_publication(): void
    {
        $trade = $this->trade();
        $a = Question::create(['trade_id' => $trade->id, 'code' => 'a', 'label' => 'A', 'type' => QuestionType::TEXT]);
        $b = Question::create(['trade_id' => $trade->id, 'code' => 'b', 'label' => 'B', 'type' => QuestionType::TEXT]);

        QuestionCondition::create([
            'question_id' => $a->id, 'depends_on_question_id' => $b->id,
            'operator' => ConditionOperator::IS_ANSWERED, 'action' => ConditionAction::SHOW,
        ]);
        QuestionCondition::create([
            'question_id' => $b->id, 'depends_on_question_id' => $a->id,
            'operator' => ConditionOperator::IS_ANSWERED, 'action' => ConditionAction::SHOW,
        ]);

        $this->assertTrue(collect($this->validator->inspect($trade))->pluck('code')->contains('circular_dependency'));
        $this->assertFalse($this->validator->canPublish($trade));
    }

    /**
     * Un LOSANGE n'est pas un cycle.
     *
     * C et D dépendent toutes deux de A : deux chemins mènent à la même question, c'est
     * parfaitement légal. Une détection naïve par « déjà visité » les confondrait avec un cycle et
     * bloquerait un questionnaire valide.
     */
    public function test_a_diamond_dependency_is_not_a_cycle(): void
    {
        $trade = $this->trade();
        $a = Question::create(['trade_id' => $trade->id, 'code' => 'a', 'label' => 'A', 'type' => QuestionType::TEXT]);
        $c = Question::create(['trade_id' => $trade->id, 'code' => 'c', 'label' => 'C', 'type' => QuestionType::TEXT]);
        $d = Question::create(['trade_id' => $trade->id, 'code' => 'd', 'label' => 'D', 'type' => QuestionType::TEXT]);

        foreach ([$c, $d] as $dependent) {
            QuestionCondition::create([
                'question_id' => $dependent->id, 'depends_on_question_id' => $a->id,
                'operator' => ConditionOperator::IS_ANSWERED, 'action' => ConditionAction::SHOW,
            ]);
        }

        $this->assertEmpty(collect($this->validator->inspect($trade))->where('code', 'circular_dependency'));
        $this->assertTrue($this->validator->canPublish($trade));
    }

    /** Une condition orpheline rend sa question invisible pour toujours : c'est bloquant. */
    public function test_a_condition_pointing_nowhere_blocks_publication(): void
    {
        $trade = $this->trade();
        $other = Question::create(['trade_id' => $this->trade()->id, 'code' => 'x', 'label' => 'X', 'type' => QuestionType::TEXT]);
        $question = Question::create(['trade_id' => $trade->id, 'code' => 'a', 'label' => 'A', 'type' => QuestionType::TEXT]);

        QuestionCondition::create([
            'question_id' => $question->id, 'depends_on_question_id' => $other->id,
            'operator' => ConditionOperator::IS_ANSWERED, 'action' => ConditionAction::SHOW,
        ]);

        $this->assertTrue(collect($this->validator->inspect($trade))->pluck('code')->contains('dangling_condition'));
    }

    /** La condition se relit en français : c'est ce que l'administrateur voit dans l'éditeur. */
    public function test_a_condition_reads_as_plain_french(): void
    {
        $trade = $this->trade();
        $trigger = Question::create([
            'trade_id' => $trade->id, 'code' => 'application', 'label' => 'Application au pistolet',
            'type' => QuestionType::SINGLE_CHOICE,
        ]);
        $dependent = Question::create([
            'trade_id' => $trade->id, 'code' => 'type', 'label' => 'Type de pistolet',
            'type' => QuestionType::SINGLE_CHOICE,
        ]);
        $condition = QuestionCondition::create([
            'question_id' => $dependent->id, 'depends_on_question_id' => $trigger->id,
            'operator' => ConditionOperator::EQUALS, 'value' => ['value' => 'Oui'],
            'action' => ConditionAction::SHOW,
        ]);

        $this->assertSame(
            'Afficher cette question SI « Application au pistolet » est Oui',
            $this->validator->describeCondition($condition, $trigger),
        );
    }

    // ─── Fabriques ───────────────────────────────────────────────────────────────────────────

    private function trade(): Trade
    {
        return Trade::create([
            'slug' => 'trade-'.uniqid(),
            'code' => strtoupper(substr(uniqid(), -8)),
            'name' => 'Métier de test',
        ]);
    }

    /** @return array{0: Trade, 1: Question} */
    private function tradeWithQuestion(): array
    {
        $trade = $this->trade();

        $question = Question::create([
            'trade_id' => $trade->id,
            'code' => 'surface_m2',
            'label' => 'Quelle surface ?',
            'type' => QuestionType::SURFACE,
        ]);

        return [$trade, $question];
    }

    private function answerFor(Trade $trade, Question $question, string $label, int $cents): OrderDraftAnswer
    {
        $draft = OrderDraft::create([
            'reference' => OrderDraft::generateReference(),
            'mode' => 'scheduled',
            'status' => 'draft',
        ]);

        $item = OrderDraftItem::create(['order_draft_id' => $draft->id, 'trade_id' => $trade->id]);

        return OrderDraftAnswer::create([
            'order_draft_item_id' => $item->id,
            'question_id' => $question->id,
            'question_code' => $question->code,
            'question_label_snapshot' => $question->label,
            'answer_label_snapshot' => $label,
            'price_impact_cents' => $cents,
        ]);
    }
}
