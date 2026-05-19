<?php

namespace Tests\Feature\PricingV2;

use App\Services\PricingV2\PricingDsl;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class PricingDslTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('pricing_v2.variable_keys', [
            'surface_m2', 'urgency', 'is_recurrent', 'day_of_week', 'rooms_count',
        ]);
        Config::set('pricing_v2.condition_operators', [
            'eq', 'neq', 'in', 'not_in', 'gt', 'gte', 'lt', 'lte', 'between',
            'is_true', 'is_false', 'is_null', 'is_not_null', 'contains',
        ]);
    }

    public function test_empty_conditions_match(): void
    {
        $this->assertTrue(app(PricingDsl::class)->evaluate([], ['surface_m2' => 50]));
    }

    public function test_eq_leaf_matches(): void
    {
        $tree = ['field' => 'urgency', 'op' => 'eq', 'value' => 'urgent'];
        $this->assertTrue(app(PricingDsl::class)->evaluate($tree, ['urgency' => 'urgent']));
        $this->assertFalse(app(PricingDsl::class)->evaluate($tree, ['urgency' => 'normal']));
    }

    public function test_gte_operator(): void
    {
        $tree = ['field' => 'surface_m2', 'op' => 'gte', 'value' => 50];
        $this->assertTrue(app(PricingDsl::class)->evaluate($tree, ['surface_m2' => 75]));
        $this->assertTrue(app(PricingDsl::class)->evaluate($tree, ['surface_m2' => 50]));
        $this->assertFalse(app(PricingDsl::class)->evaluate($tree, ['surface_m2' => 30]));
    }

    public function test_in_operator(): void
    {
        $tree = ['field' => 'day_of_week', 'op' => 'in', 'value' => ['saturday', 'sunday']];
        $this->assertTrue(app(PricingDsl::class)->evaluate($tree, ['day_of_week' => 'saturday']));
        $this->assertFalse(app(PricingDsl::class)->evaluate($tree, ['day_of_week' => 'monday']));
    }

    public function test_between_operator(): void
    {
        $tree = ['field' => 'rooms_count', 'op' => 'between', 'value' => [2, 4]];
        $this->assertTrue(app(PricingDsl::class)->evaluate($tree, ['rooms_count' => 3]));
        $this->assertFalse(app(PricingDsl::class)->evaluate($tree, ['rooms_count' => 5]));
    }

    public function test_is_true_operator(): void
    {
        $tree = ['field' => 'is_recurrent', 'op' => 'is_true', 'value' => null];
        $this->assertTrue(app(PricingDsl::class)->evaluate($tree, ['is_recurrent' => true]));
        $this->assertFalse(app(PricingDsl::class)->evaluate($tree, ['is_recurrent' => false]));
    }

    public function test_and_combinator(): void
    {
        $tree = [
            'and' => [
                ['field' => 'surface_m2', 'op' => 'gte', 'value' => 50],
                ['field' => 'urgency', 'op' => 'eq', 'value' => 'urgent'],
            ],
        ];
        $this->assertTrue(app(PricingDsl::class)->evaluate($tree, ['surface_m2' => 75, 'urgency' => 'urgent']));
        $this->assertFalse(app(PricingDsl::class)->evaluate($tree, ['surface_m2' => 30, 'urgency' => 'urgent']));
        $this->assertFalse(app(PricingDsl::class)->evaluate($tree, ['surface_m2' => 75, 'urgency' => 'normal']));
    }

    public function test_or_combinator(): void
    {
        $tree = [
            'or' => [
                ['field' => 'urgency', 'op' => 'eq', 'value' => 'urgent'],
                ['field' => 'is_recurrent', 'op' => 'is_true', 'value' => null],
            ],
        ];
        $this->assertTrue(app(PricingDsl::class)->evaluate($tree, ['urgency' => 'urgent', 'is_recurrent' => false]));
        $this->assertTrue(app(PricingDsl::class)->evaluate($tree, ['urgency' => 'normal', 'is_recurrent' => true]));
        $this->assertFalse(app(PricingDsl::class)->evaluate($tree, ['urgency' => 'normal', 'is_recurrent' => false]));
    }

    public function test_not_combinator(): void
    {
        $tree = ['not' => ['field' => 'urgency', 'op' => 'eq', 'value' => 'urgent']];
        $this->assertTrue(app(PricingDsl::class)->evaluate($tree, ['urgency' => 'normal']));
        $this->assertFalse(app(PricingDsl::class)->evaluate($tree, ['urgency' => 'urgent']));
    }

    public function test_invalid_field_fails_closed(): void
    {
        $tree = ['field' => 'arbitrary_xyz', 'op' => 'eq', 'value' => 'anything'];
        $this->assertFalse(app(PricingDsl::class)->evaluate($tree, ['arbitrary_xyz' => 'anything']));
    }

    public function test_invalid_operator_fails_closed(): void
    {
        $tree = ['field' => 'urgency', 'op' => 'regex_match', 'value' => 'urgent'];
        $this->assertFalse(app(PricingDsl::class)->evaluate($tree, ['urgency' => 'urgent']));
    }
}
