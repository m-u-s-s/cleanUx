<?php

namespace Tests\Feature\OrderEngine;

use App\Livewire\OrderEngine\OrderJourney;
use App\Models\Question;
use App\Models\QuestionCondition;
use App\Models\QuestionOption;
use App\Models\QuestionStep;
use App\Models\Trade;
use App\Support\Domain\ConditionAction;
use App\Support\Domain\ConditionOperator;
use App\Support\Domain\QuestionType;
use Database\Seeders\OrderEngineCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * « Aucun métier n'affiche plus de sept questions en une seule étape. »
 *
 * C'était le seul critère d'acceptation que rien ne garantissait. Le validateur AVERTIT
 * l'administrateur au-delà de sept — mais ce n'est qu'une alerte, et surtout le parcours client
 * ignorait complètement les étapes : `visibleQuestions` rendait une liste plate, `step_id` n'était
 * lu nulle part. Un métier de douze questions affichait douze champs empilés, ce que la
 * spécification range parmi ses anti-patterns.
 *
 * La garantie ne peut pas reposer sur la discipline de qui remplit le back-office. Elle est donc
 * STRUCTURELLE : au-delà du seuil, le parcours se découpe tout seul.
 *
 * L'indicateur de progression est HONNÊTE, ce qui est la partie difficile : les questions
 * conditionnelles apparaissent et disparaissent pendant la saisie. Il compte donc les étapes
 * réellement VISIBLES à l'instant présent, jamais celles qui existent en base.
 */
class QuestionnaireStepsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrderEngineCatalogSeeder::class);
    }

    /**
     * Un questionnaire court SANS étapes déclarées reste d'un seul tenant.
     *
     * Pas de cérémonie là où elle n'apporte rien : six questions ne se traversent pas en deux
     * temps.
     *
     * (Le métier « Peinture » du catalogue seedé, lui, déclare DÉJÀ deux étapes — elles étaient
     * écrites dans les données et le parcours les ignorait. Il ne sert donc pas ce test-ci.)
     */
    public function test_a_short_questionnaire_stays_on_one_screen(): void
    {
        $component = Livewire::test(OrderJourney::class)
            ->call('selectTrade', $this->tradeWithQuestions(6)->id);

        $this->assertSame(1, $component->instance()->stepCount());
        $component->assertDontSee('Étape 1 sur');
    }

    /** Et le catalogue seedé en profite immédiatement : ses étapes sont enfin rendues. */
    public function test_the_seeded_catalogue_already_declared_steps(): void
    {
        $component = Livewire::test(OrderJourney::class)
            ->call('selectTrade', $this->peinture()->id);

        $this->assertSame(2, $component->instance()->stepCount());
        $component->assertSee('Étape 1 sur 2');
    }

    /**
     * LA GARANTIE : au-delà de sept, le parcours se découpe TOUT SEUL.
     *
     * Sans découpage automatique, la règle dépendrait de la discipline de l'administrateur — et le
     * validateur ne fait que l'avertir.
     */
    public function test_a_long_questionnaire_splits_itself(): void
    {
        $trade = $this->tradeWithQuestions(12);

        $component = Livewire::test(OrderJourney::class)->call('selectTrade', $trade->id);

        $this->assertGreaterThan(1, $component->instance()->stepCount());

        foreach (range(0, $component->instance()->stepCount() - 1) as $index) {
            $component->set('stepIndex', $index);

            $this->assertLessThanOrEqual(
                7,
                $component->instance()->visibleQuestions()->count(),
                'Une étape affiche plus de sept questions.',
            );
        }
    }

    /** Les étapes écrites par l'administrateur priment sur le découpage automatique. */
    public function test_admin_defined_steps_win(): void
    {
        $trade = $this->tradeWithQuestions(6);

        $step = QuestionStep::create(['trade_id' => $trade->id, 'title' => 'Vos locaux', 'sort_order' => 1]);
        $trade->questions()->limit(3)->update(['step_id' => $step->id]);

        Livewire::test(OrderJourney::class)
            ->call('selectTrade', $trade->id)
            ->assertSee('Étape 1 sur 2');
    }

    /** Avancer, revenir : rien n'est perdu — les réponses vivent dans le panier, pas à l'écran. */
    public function test_going_back_a_step_loses_nothing(): void
    {
        $trade = $this->tradeWithQuestions(12);
        $code = $trade->questions()->orderBy('sort_order')->first()->code;

        $component = Livewire::test(OrderJourney::class)
            ->call('selectTrade', $trade->id)
            ->dispatch('question-answered', code: $code, value: 'une réponse', valid: true)
            ->call('nextStep')
            ->call('previousStep');

        $this->assertSame('une réponse', $component->instance()->answers[$code] ?? null);
    }

    /**
     * L'indicateur compte les étapes RÉELLEMENT visibles.
     *
     * Une étape dont toutes les questions sont masquées par une condition ne compte pas, et ne se
     * traverse pas : annoncer « étape 2 sur 3 » puis sauter la troisième serait un compte
     * malhonnête, et le client saurait qu'on lui raconte quelque chose.
     */
    public function test_an_entirely_hidden_step_is_not_counted(): void
    {
        $trade = $this->tradeWithQuestions(6);

        $porte = Question::create([
            'trade_id' => $trade->id, 'code' => 'porte', 'label' => 'Une porte',
            'type' => QuestionType::BOOLEAN, 'sort_order' => 1,
        ]);
        QuestionOption::create(['question_id' => $porte->id, 'label' => 'Oui', 'value' => 'oui']);

        $step = QuestionStep::create(['trade_id' => $trade->id, 'title' => 'Cachée', 'sort_order' => 9]);
        $cachee = Question::create([
            'trade_id' => $trade->id, 'step_id' => $step->id, 'code' => 'cachee',
            'label' => 'Jamais visible', 'type' => QuestionType::TEXT, 'sort_order' => 90,
        ]);
        QuestionCondition::create([
            'question_id' => $cachee->id,
            'depends_on_question_id' => $porte->id,
            'operator' => ConditionOperator::EQUALS,
            'value' => ['oui'],
            'action' => ConditionAction::SHOW,
        ]);

        $component = Livewire::test(OrderJourney::class)->call('selectTrade', $trade->id);

        $this->assertSame(
            1,
            $component->instance()->stepCount(),
            'Une étape entièrement masquée est comptée dans la progression.',
        );
    }

    /** L'écran câble sa propre navigation — neuvième fois que ce module l'oublierait. */
    public function test_the_screen_wires_its_step_navigation(): void
    {
        $trade = $this->tradeWithQuestions(12);

        $html = Livewire::test(OrderJourney::class)->call('selectTrade', $trade->id)->html();

        $this->assertStringContainsString('nextStep', $html);
        $this->assertStringContainsString('Étape 1 sur', $html);
    }

    private function tradeWithQuestions(int $count): Trade
    {
        $trade = Trade::create([
            'slug' => 'metier-'.uniqid(),
            'code' => strtoupper(substr(uniqid(), -8)),
            'name' => 'Métier long',
            'sector_id' => $this->peinture()->sector_id,
            'is_active' => true,
        ]);

        foreach (range(1, $count) as $i) {
            Question::create([
                'trade_id' => $trade->id,
                'code' => 'q'.$i,
                'label' => 'Question '.$i,
                'type' => QuestionType::TEXT,
                'sort_order' => $i * 10,
            ]);
        }

        return $trade->fresh();
    }

    private function peinture(): Trade
    {
        return Trade::where('slug', 'peinture')->firstOrFail();
    }
}
