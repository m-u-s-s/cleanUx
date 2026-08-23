<?php

namespace Tests\Unit\OrderEngine;

use App\Models\Question;
use App\Models\QuestionCondition;
use App\Models\QuestionOption;
use App\Models\Trade;
use App\Services\OrderEngine\ConditionEvaluator;
use App\Services\OrderEngine\PricingEngine;
use App\Support\Domain\ConditionAction;
use App\Support\Domain\ConditionOperator;
use App\Support\Domain\OrderMode;
use App\Support\Domain\PriceImpactMode;
use App\Support\Domain\PricingUnit;
use App\Support\Domain\QuestionType;
use Illuminate\Support\Collection;
use Tests\TestCase;

/** Le moteur tarifaire — sans base de données. */
class PricingEngineTest extends TestCase
{
    private PricingEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new PricingEngine(new ConditionEvaluator);
    }

    public function test_a_trade_without_answers_costs_its_base_price(): void
    {
        $quote = $this->engine->quoteItem($this->trade(15000), collect(), []);

        $this->assertSame(15000, $quote->minCents);
        $this->assertSame(15000, $quote->maxCents);
        $this->assertTrue($quote->isExact());
    }

    /** LA garantie arithmétique : on ADDITIONNE d'abord, on MULTIPLIE ensuite. */
    public function test_modifiers_are_summed_before_multipliers_are_applied(): void
    {
        $question = $this->choiceQuestion('finition', [
            ['value' => 'premium', 'price_modifier_cents' => 5000, 'price_multiplier' => 1.5],
        ]);

        $quote = $this->engine->quoteItem($this->trade(10000), collect([$question]), ['finition' => 'premium']);

        $this->assertSame(22500, $quote->minCents);
        $this->assertNotSame(20000, $quote->minCents, 'Le multiplicateur a été appliqué avant la somme.');
    }

    /** L'arrondi n'a lieu QU'UNE FOIS, à la fin. 101 × 1,5 × 1,5 = 227,25 → 227. */
    public function test_rounding_happens_once_at_the_end(): void
    {
        $q1 = $this->choiceQuestion('a', [['value' => 'oui', 'price_multiplier' => 1.5]]);
        $q2 = $this->choiceQuestion('b', [['value' => 'oui', 'price_multiplier' => 1.5]]);

        $quote = $this->engine->quoteItem($this->trade(101), collect([$q1, $q2]), ['a' => 'oui', 'b' => 'oui']);

        $this->assertSame(227, $quote->minCents);
        $this->assertNotSame(228, $quote->minCents, 'Un arrondi intermédiaire a été appliqué.');
    }

    public function test_a_per_unit_question_multiplies_the_value_by_its_coefficient(): void
    {
        $question = $this->numericQuestion('surface_m2', PriceImpactMode::PER_UNIT, 250, ['min' => 10, 'max' => 200]);

        $quote = $this->engine->quoteItem($this->trade(5000), collect([$question]), ['surface_m2' => 40]);

        // 5000 + (40 × 250) = 15000
        $this->assertSame(15000, $quote->minCents);
    }

    /** La porte de sortie borne le prix au lieu de le bloquer. */
    public function test_an_unknown_choice_widens_the_range_to_the_real_option_spread(): void
    {
        $question = $this->choiceQuestion('etendue', [
            ['value' => 'murs', 'price_modifier_cents' => 0],
            ['value' => 'murs_plafonds', 'price_modifier_cents' => 2500],
        ]);

        $quote = $this->engine->quoteItem(
            $this->trade(10000),
            collect([$question]),
            ['etendue' => ['unknown' => true]],
        );

        $this->assertSame(10000, $quote->minCents);
        $this->assertSame(12500, $quote->maxCents);
        $this->assertFalse($quote->isExact());
    }

    /** Sur une question numérique, ce sont les bornes déclarées par l'administrateur qui servent. */
    public function test_an_unknown_number_is_bounded_by_its_declared_validation(): void
    {
        $question = $this->numericQuestion('surface_m2', PriceImpactMode::PER_UNIT, 100, ['min' => 20, 'max' => 60]);

        $quote = $this->engine->quoteItem(
            $this->trade(0),
            collect([$question]),
            ['surface_m2' => ['unknown' => true]],
        );

        $this->assertSame(2000, $quote->minCents);
        $this->assertSame(6000, $quote->maxCents);
    }

    /** Une question CACHÉE ne pèse pas sur le prix. */
    public function test_a_hidden_question_never_reaches_the_price(): void
    {
        $trigger = $this->choiceQuestion('application', [
            ['value' => 'pistolet'],
            ['value' => 'rouleau'],
        ], id: 1);

        $dependent = $this->choiceQuestion('type_pistolet', [
            ['value' => 'airless', 'price_modifier_cents' => 4000],
        ], id: 2);

        $dependent->setRelation('conditions', collect([
            new QuestionCondition([
                'depends_on_question_id' => 1,
                'operator' => ConditionOperator::EQUALS,
                'value' => ['value' => 'pistolet'],
                'action' => ConditionAction::SHOW,
            ]),
        ]));

        $questions = collect([$trigger, $dependent]);

        // Le client a répondu « airless » puis est revenu sur « rouleau » : la question est cachée.
        $quote = $this->engine->quoteItem($this->trade(10000), $questions, [
            'application' => 'rouleau',
            'type_pistolet' => 'airless',
        ]);

        $this->assertSame(10000, $quote->minCents, 'Le supplément d’une question cachée a été facturé.');
    }

    public function test_the_same_answer_counts_once_the_question_becomes_visible(): void
    {
        $trigger = $this->choiceQuestion('application', [['value' => 'pistolet']], id: 1);
        $dependent = $this->choiceQuestion('type_pistolet', [
            ['value' => 'airless', 'price_modifier_cents' => 4000],
        ], id: 2);
        $dependent->setRelation('conditions', collect([
            new QuestionCondition([
                'depends_on_question_id' => 1,
                'operator' => ConditionOperator::EQUALS,
                'value' => ['value' => 'pistolet'],
                'action' => ConditionAction::SHOW,
            ]),
        ]));

        $quote = $this->engine->quoteItem($this->trade(10000), collect([$trigger, $dependent]), [
            'application' => 'pistolet',
            'type_pistolet' => 'airless',
        ]);

        $this->assertSame(14000, $quote->minCents);
    }

    /** Un métier au devis obligatoire n'annonce aucun prix, plutôt qu'un chiffre démenti sur place. */
    public function test_a_quote_only_trade_returns_no_estimate(): void
    {
        $trade = $this->trade(50000);
        $trade->pricing_unit = PricingUnit::QUOTE_ONLY;

        $quote = $this->engine->quoteItem($trade, collect(), []);

        $this->assertTrue($quote->quoteOnly);
        $this->assertSame(0, $quote->minCents);
    }

    /** La majoration d'urgence s'applique, et la fourchette s'élargit — le questionnaire est plus court. */
    public function test_the_asap_mode_applies_its_surcharge_and_widens_the_range(): void
    {
        $scheduled = $this->engine->quoteItem($this->trade(10000), collect(), []);
        $asap = $this->engine->quoteItem($this->trade(10000), collect(), [], ['mode' => OrderMode::ASAP]);

        $this->assertGreaterThan($scheduled->minCents, $asap->minCents);
        $this->assertGreaterThan($asap->minCents, $asap->maxCents, 'Le mode immédiat doit annoncer une fourchette, pas un prix ferme.');
    }

    public function test_a_zone_multiplier_applies_to_the_whole_item(): void
    {
        $quote = $this->engine->quoteItem($this->trade(10000), collect(), [], ['zone_multiplier' => 1.2]);

        $this->assertSame(12000, $quote->minCents);
    }

    /** Chaque euro doit être rattaché à une réponse : c'est ce qui désamorce les litiges. */
    public function test_every_cent_is_attached_to_a_line(): void
    {
        $question = $this->choiceQuestion('finition', [
            ['value' => 'premium', 'price_modifier_cents' => 5000],
        ]);

        $quote = $this->engine->quoteItem($this->trade(10000), collect([$question]), ['finition' => 'premium']);

        $this->assertSame(15000, $quote->minCents);
        $this->assertSame(15000, collect($quote->lines)->sum('min_cents'));
        $this->assertSame(['_base', 'finition'], collect($quote->lines)->pluck('code')->all());
    }

    // ─── Commande consolidée ─────────────────────────────────────────────────────────────────

    public function test_an_order_sums_its_items(): void
    {
        $a = $this->engine->quoteItem($this->trade(10000), collect(), []);
        $b = $this->engine->quoteItem($this->trade(5000), collect(), []);

        $order = $this->engine->quoteOrder([$a, $b]);

        $this->assertSame(15000, $order->minCents);
    }

    /** La remise multi-services est une LIGNE du devis, pas une soustraction silencieuse. */
    public function test_the_bundle_discount_applies_and_shows_itself(): void
    {
        $items = array_fill(0, 3, $this->engine->quoteItem($this->trade(10000), collect(), []));

        $order = $this->engine->quoteOrder($items, OrderMode::BUNDLE);

        // 30 000 − 8 % = 27 600
        $this->assertSame(27600, $order->minCents);

        $discountLine = collect($order->lines)->firstWhere('code', '_bundle_discount');
        $this->assertNotNull($discountLine, 'La remise doit apparaître sur le devis : invisible, elle ne décide de rien.');
        $this->assertSame(-2400, $discountLine['min_cents']);
    }

    public function test_no_discount_outside_the_bundle_mode(): void
    {
        $items = array_fill(0, 3, $this->engine->quoteItem($this->trade(10000), collect(), []));

        $this->assertSame(30000, $this->engine->quoteOrder($items, OrderMode::SCHEDULED)->minCents);
    }

    public function test_the_discount_tier_follows_the_number_of_trades(): void
    {
        $this->assertSame(0, $this->engine->bundleDiscountPercent(1));
        $this->assertSame(5, $this->engine->bundleDiscountPercent(2));
        $this->assertSame(8, $this->engine->bundleDiscountPercent(3));
        $this->assertSame(12, $this->engine->bundleDiscountPercent(9));
    }

    /** Un métier au devis n'entre pas dans le total : on ne somme pas ce qu'on n'a pas chiffré. */
    public function test_a_quote_only_item_is_excluded_from_the_order_total(): void
    {
        $priced = $this->engine->quoteItem($this->trade(10000), collect(), []);

        $quoteOnlyTrade = $this->trade(99999);
        $quoteOnlyTrade->pricing_unit = PricingUnit::QUOTE_ONLY;
        $unpriced = $this->engine->quoteItem($quoteOnlyTrade, collect(), []);

        $this->assertSame(10000, $this->engine->quoteOrder([$priced, $unpriced])->minCents);
    }

    // ─── Fabriques ───────────────────────────────────────────────────────────────────────────

    private function trade(int $baseCents): Trade
    {
        return new Trade([
            'name' => 'Peinture',
            'base_price_cents' => $baseCents,
            'pricing_unit' => PricingUnit::FIXED,
            'estimated_duration_min' => 120,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $options
     */
    private function choiceQuestion(string $code, array $options, int $id = 0): Question
    {
        $question = new Question([
            'code' => $code,
            'label' => ucfirst($code),
            'type' => QuestionType::SINGLE_CHOICE,
        ]);
        $question->id = $id ?: random_int(1000, 9999);

        $question->setRelation('options', new Collection(array_map(
            fn (array $o) => new QuestionOption(array_merge([
                'label' => $o['value'],
                'price_modifier_cents' => 0,
                'duration_modifier_min' => 0,
            ], $o)),
            $options,
        )));
        $question->setRelation('conditions', new Collection);

        return $question;
    }

    private function numericQuestion(string $code, string $mode, float $coefficient, array $validation): Question
    {
        $question = new Question([
            'code' => $code,
            'label' => ucfirst($code),
            'type' => QuestionType::SURFACE,
            'pricing' => ['mode' => $mode, 'coefficient' => $coefficient, 'unit' => 'm²'],
            'validation' => $validation,
            'duration_impact_min' => 0,
        ]);
        $question->id = random_int(1000, 9999);
        $question->setRelation('options', new Collection);
        $question->setRelation('conditions', new Collection);

        return $question;
    }
}
