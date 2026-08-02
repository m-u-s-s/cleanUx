<?php

namespace Tests\Feature\OrderEngine;

use App\Livewire\Admin\OrderEngine\QuestionnaireBuilder;
use App\Models\Question;
use App\Models\QuestionCondition;
use App\Models\QuestionOption;
use App\Models\Trade;
use App\Models\User;
use App\Support\Domain\ConditionAction;
use App\Support\Domain\ConditionOperator;
use App\Support\Domain\QuestionType;
use Database\Seeders\OrderEngineCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * « Afficher [Type de pistolet] SI [Application au pistolet] EST [Oui]. »
 *
 * Le moteur de conditions était complet et testé — évaluation, détection de cycles, export,
 * import, refus de publication sur une dépendance circulaire — et il n'avait AUCUNE interface.
 * `QuestionCondition` n'apparaissait nulle part dans les composants ni dans les gabarits : un
 * administrateur ne pouvait ni créer, ni modifier, ni supprimer une seule condition.
 *
 * C'est la promesse centrale du module qui tombait : « ajouter un métier et ses questions sans une
 * ligne de code » devient faux dès qu'un parcours a besoin d'une question conditionnelle — c'est-à-
 * dire dès le premier exemple de la spécification.
 */
class ConditionEditorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrderEngineCatalogSeeder::class);
        $this->actingAs(User::factory()->create(['role' => 'admin', 'platform_role' => 'admin']));
    }

    public function test_an_admin_adds_a_condition_from_the_screen(): void
    {
        [$trade, $pistolet, $type] = $this->twoQuestions();

        Livewire::test(QuestionnaireBuilder::class, ['trade' => $trade])
            ->call('startCondition', $type->id)
            ->set('conditionForm.depends_on_question_id', $pistolet->id)
            ->set('conditionForm.operator', ConditionOperator::EQUALS)
            ->set('conditionForm.value', 'oui')
            ->set('conditionForm.action', ConditionAction::SHOW)
            ->call('saveCondition')
            ->assertHasNoErrors();

        $condition = QuestionCondition::where('question_id', $type->id)->first();

        $this->assertNotNull($condition, 'Aucune condition n’a été créée depuis l’écran.');
        $this->assertSame($pistolet->id, $condition->depends_on_question_id);
        $this->assertSame(ConditionOperator::EQUALS, $condition->operator);
        $this->assertSame(ConditionAction::SHOW, $condition->action);
    }

    /** La règle se relit en toutes lettres, pas en identifiants. */
    public function test_the_rule_reads_back_in_plain_words(): void
    {
        [$trade, $pistolet, $type] = $this->twoQuestions();
        $this->condition($type, $pistolet, 'oui');

        Livewire::test(QuestionnaireBuilder::class, ['trade' => $trade])
            ->assertSee('Peinture au pistolet ?')
            ->assertSee('est égal à');
    }

    /**
     * Une dépendance circulaire est refusée À LA SAISIE.
     *
     * Le validateur la bloquait déjà à la publication. Mais un administrateur qui a écrit trois
     * conditions et découvre au moment de publier que l'une d'elles est fautive doit refaire le
     * chemin à l'envers pour trouver laquelle. Refuser au moment du geste dit QUELLE règle pose
     * problème, pendant qu'il l'a sous les yeux.
     */
    public function test_a_circular_dependency_is_refused_when_typed(): void
    {
        [$trade, $a, $b] = $this->twoQuestions();
        $this->condition($b, $a, 'oui');

        Livewire::test(QuestionnaireBuilder::class, ['trade' => $trade])
            ->call('startCondition', $a->id)
            ->set('conditionForm.depends_on_question_id', $b->id)
            ->set('conditionForm.operator', ConditionOperator::IS_ANSWERED)
            ->set('conditionForm.action', ConditionAction::SHOW)
            ->call('saveCondition')
            ->assertHasErrors('conditionForm.depends_on_question_id');

        // Le catalogue seedé porte DÉJÀ des conditions : on ne compte que les nôtres.
        $this->assertSame(
            1,
            QuestionCondition::whereIn('question_id', [$a->id, $b->id])->count(),
            'La condition circulaire a été enregistrée.',
        );
    }

    /** Une question ne dépend pas d'elle-même. */
    public function test_a_question_cannot_depend_on_itself(): void
    {
        [$trade, , $type] = $this->twoQuestions();

        Livewire::test(QuestionnaireBuilder::class, ['trade' => $trade])
            ->call('startCondition', $type->id)
            ->set('conditionForm.depends_on_question_id', $type->id)
            ->set('conditionForm.operator', ConditionOperator::IS_ANSWERED)
            ->set('conditionForm.action', ConditionAction::SHOW)
            ->call('saveCondition')
            ->assertHasErrors('conditionForm.depends_on_question_id');

        $this->assertSame(0, QuestionCondition::where('question_id', $type->id)->count());
    }

    public function test_a_condition_can_be_removed(): void
    {
        [$trade, $pistolet, $type] = $this->twoQuestions();
        $condition = $this->condition($type, $pistolet, 'oui');

        Livewire::test(QuestionnaireBuilder::class, ['trade' => $trade])
            ->call('removeCondition', $condition->id);

        $this->assertNull(QuestionCondition::find($condition->id));
    }

    /** Le lecteur seul ne réécrit pas la logique du parcours. */
    public function test_a_read_only_admin_cannot_write_a_condition(): void
    {
        $this->actingAs(User::factory()->create([
            'role' => 'admin', 'platform_role' => 'admin', 'access_scope' => 'readonly',
        ]));

        [$trade, $pistolet, $type] = $this->twoQuestions();

        Livewire::test(QuestionnaireBuilder::class, ['trade' => $trade])
            ->call('startCondition', $type->id)
            ->set('conditionForm.depends_on_question_id', $pistolet->id)
            ->set('conditionForm.operator', ConditionOperator::IS_ANSWERED)
            ->set('conditionForm.action', ConditionAction::SHOW)
            ->call('saveCondition');

        $this->assertSame(0, QuestionCondition::where('question_id', $type->id)->count());
    }

    /**
     * L'écran câble ses propres actions.
     *
     * Ce module a produit cinq fois un service écrit, testé, vert — et sans porte d'entrée. Un
     * test qui appelle `saveCondition()` directement ne prouve pas qu'un bouton l'appelle.
     */
    public function test_the_screen_wires_the_condition_editor(): void
    {
        [$trade, $pistolet, $type] = $this->twoQuestions();
        $condition = $this->condition($type, $pistolet, 'oui');

        $component = Livewire::test(QuestionnaireBuilder::class, ['trade' => $trade]);

        // Au repos : le bouton qui OUVRE l'éditeur, et celui qui retire une règle existante.
        $atRest = $component->html();
        $this->assertStringContainsString('startCondition('.$type->id.')', $atRest);
        $this->assertStringContainsString('removeCondition('.$condition->id.')', $atRest);

        // Éditeur ouvert : le bouton qui enregistre.
        $open = $component->call('startCondition', $type->id)->html();
        $this->assertStringContainsString(
            'saveCondition',
            $open,
            'L’éditeur s’ouvre sans bouton pour enregistrer la règle.',
        );
    }

    /** @return array{0: Trade, 1: Question, 2: Question} */
    private function twoQuestions(): array
    {
        $trade = Trade::where('slug', 'peinture')->firstOrFail();

        $pistolet = Question::create([
            'trade_id' => $trade->id, 'code' => 'cond_source', 'label' => 'Peinture au pistolet ?',
            'type' => QuestionType::BOOLEAN, 'sort_order' => 90,
        ]);
        QuestionOption::create(['question_id' => $pistolet->id, 'label' => 'Oui', 'value' => 'oui']);
        QuestionOption::create(['question_id' => $pistolet->id, 'label' => 'Non', 'value' => 'non']);

        $type = Question::create([
            'trade_id' => $trade->id, 'code' => 'cond_cible', 'label' => 'Modèle de pistolet',
            'type' => QuestionType::SINGLE_CHOICE, 'sort_order' => 91,
        ]);

        return [$trade, $pistolet, $type];
    }

    private function condition(Question $question, Question $dependsOn, string $value): QuestionCondition
    {
        return QuestionCondition::create([
            'question_id' => $question->id,
            'depends_on_question_id' => $dependsOn->id,
            'operator' => ConditionOperator::EQUALS,
            'value' => [$value],
            'action' => ConditionAction::SHOW,
        ]);
    }
}
