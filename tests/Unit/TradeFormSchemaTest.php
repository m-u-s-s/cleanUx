<?php

namespace Tests\Unit;

use App\Support\TradeFormSchema;
use Tests\TestCase;

class TradeFormSchemaTest extends TestCase
{
    public function test_validate_returns_empty_normalized_for_null(): void
    {
        $result = TradeFormSchema::validate(null);
        $this->assertTrue($result['ok']);
        $this->assertSame(['version' => 1, 'fields' => []], $result['normalized']);
    }

    public function test_validate_rejects_non_array(): void
    {
        $result = TradeFormSchema::validate('not_an_array');
        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_validate_rejects_missing_fields_array(): void
    {
        $result = TradeFormSchema::validate(['version' => 1]);
        $this->assertFalse($result['ok']);
    }

    public function test_validate_rejects_unsupported_type(): void
    {
        $result = TradeFormSchema::validate([
            'version' => 1,
            'fields' => [
                ['key' => 'x', 'label' => 'X', 'type' => 'unknown_type'],
            ],
        ]);
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('non supporté', $result['errors'][0]);
    }

    public function test_validate_rejects_duplicate_keys(): void
    {
        $result = TradeFormSchema::validate([
            'version' => 1,
            'fields' => [
                ['key' => 'foo', 'label' => 'A', 'type' => 'text'],
                ['key' => 'foo', 'label' => 'B', 'type' => 'text'],
            ],
        ]);
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('dupliqu', $result['errors'][0]);
    }

    public function test_validate_rejects_invalid_key_format(): void
    {
        $result = TradeFormSchema::validate([
            'version' => 1,
            'fields' => [
                ['key' => '1foo', 'label' => 'A', 'type' => 'text'],
            ],
        ]);
        $this->assertFalse($result['ok']);
    }

    public function test_validate_select_requires_options(): void
    {
        $result = TradeFormSchema::validate([
            'version' => 1,
            'fields' => [
                ['key' => 'col', 'label' => 'Col', 'type' => 'select'],
            ],
        ]);
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('options', $result['errors'][0]);
    }

    public function test_validate_select_rejects_duplicate_option_values(): void
    {
        $result = TradeFormSchema::validate([
            'version' => 1,
            'fields' => [
                ['key' => 'col', 'label' => 'Col', 'type' => 'select', 'options' => [
                    ['value' => 'a', 'label' => 'A'],
                    ['value' => 'a', 'label' => 'A2'],
                ]],
            ],
        ]);
        $this->assertFalse($result['ok']);
    }

    public function test_validate_pricing_modifier_rejected_for_select(): void
    {
        $result = TradeFormSchema::validate([
            'version' => 1,
            'fields' => [
                ['key' => 'col', 'label' => 'Col', 'type' => 'select',
                 'options' => [['value' => 'a', 'label' => 'A']],
                 'pricing' => ['modifier' => 'fixed', 'value' => 10]],
            ],
        ]);
        $this->assertFalse($result['ok']);
    }

    public function test_validate_normalizes_a_full_schema(): void
    {
        $result = TradeFormSchema::validate([
            'version' => 1,
            'fields' => [
                ['key' => 'nb_enfants', 'label' => '  Nombre  ', 'type' => 'number',
                 'required' => true, 'min' => 1, 'max' => 5, 'default' => 1,
                 'pricing' => ['modifier' => 'per_unit', 'value' => 5]],
                ['key' => 'urgence', 'label' => 'Urgence', 'type' => 'boolean',
                 'pricing' => ['modifier' => 'percent', 'value' => 100]],
                ['key' => 'type_serrure', 'label' => 'Serrure', 'type' => 'select',
                 'options' => [
                    ['value' => 'simple', 'label' => 'Simple', 'price_delta' => 0],
                    ['value' => 'blindee', 'label' => 'Blindée', 'price_delta' => 50],
                 ]],
                ['key' => 'extras', 'label' => 'Extras', 'type' => 'multiselect',
                 'options' => [
                    ['value' => 'fournitures', 'label' => 'Fournitures', 'price_delta' => 30],
                 ]],
                ['key' => 'commentaire', 'label' => 'Commentaire', 'type' => 'textarea',
                 'max_length' => 500],
            ],
        ]);

        $this->assertTrue($result['ok']);
        $fields = $result['normalized']['fields'];
        $this->assertCount(5, $fields);
        $this->assertSame('Nombre', $fields[0]['label']);
        $this->assertSame(1.0, $fields[0]['min']);
        $this->assertTrue($fields[0]['required']);
        $this->assertSame(100.0, $fields[1]['pricing']['value']);
        $this->assertCount(2, $fields[2]['options']);
    }

    public function test_default_answers_uses_defaults_or_type_defaults(): void
    {
        $schema = TradeFormSchema::validate([
            'version' => 1,
            'fields' => [
                ['key' => 'nb', 'label' => 'Nb', 'type' => 'number', 'default' => 3],
                ['key' => 'flag', 'label' => 'Flag', 'type' => 'boolean'],
                ['key' => 'ms', 'label' => 'Ms', 'type' => 'multiselect',
                 'options' => [['value' => 'a', 'label' => 'A']]],
                ['key' => 'txt', 'label' => 'Txt', 'type' => 'text'],
            ],
        ])['normalized'];

        $defaults = TradeFormSchema::defaultAnswers($schema);
        $this->assertSame(3, $defaults['nb']);
        $this->assertFalse($defaults['flag']);
        $this->assertSame([], $defaults['ms']);
        $this->assertSame('', $defaults['txt']);
    }

    public function test_compute_price_delta_handles_all_modifiers(): void
    {
        $schema = TradeFormSchema::validate([
            'version' => 1,
            'fields' => [
                ['key' => 'nb', 'label' => 'Nb', 'type' => 'number',
                 'pricing' => ['modifier' => 'per_unit', 'value' => 5]],
                ['key' => 'urgent', 'label' => 'Urgent', 'type' => 'boolean',
                 'pricing' => ['modifier' => 'percent', 'value' => 50]],
                ['key' => 'fixe', 'label' => 'Fixe', 'type' => 'boolean',
                 'pricing' => ['modifier' => 'fixed', 'value' => 20]],
                ['key' => 'col', 'label' => 'Col', 'type' => 'select',
                 'options' => [
                    ['value' => 'a', 'label' => 'A', 'price_delta' => 0],
                    ['value' => 'b', 'label' => 'B', 'price_delta' => 80],
                 ]],
                ['key' => 'extras', 'label' => 'Extras', 'type' => 'multiselect',
                 'options' => [
                    ['value' => 'x', 'label' => 'X', 'price_delta' => 10],
                    ['value' => 'y', 'label' => 'Y', 'price_delta' => 15],
                 ]],
            ],
        ])['normalized'];

        $delta = TradeFormSchema::computePriceDelta($schema, [
            'nb'     => 4,
            'urgent' => true,
            'fixe'   => true,
            'col'    => 'b',
            'extras' => ['x', 'y'],
        ], 200.0);

        // 4 × 5 = 20 (per_unit)
        // 200 × 50% = 100 (percent)
        // 20 (fixed)
        // 80 (select B)
        // 10 + 15 = 25 (multiselect)
        // Total = 245
        $this->assertSame(245.0, $delta['total']);
        $this->assertCount(5, $delta['breakdown']);
    }

    public function test_compute_price_delta_ignores_unset_or_zero(): void
    {
        $schema = TradeFormSchema::validate([
            'version' => 1,
            'fields' => [
                ['key' => 'nb', 'label' => 'Nb', 'type' => 'number',
                 'pricing' => ['modifier' => 'per_unit', 'value' => 5]],
                ['key' => 'flag', 'label' => 'Flag', 'type' => 'boolean',
                 'pricing' => ['modifier' => 'fixed', 'value' => 10]],
            ],
        ])['normalized'];

        $delta = TradeFormSchema::computePriceDelta($schema, [
            'nb' => 0,
            'flag' => false,
        ]);

        $this->assertSame(0.0, $delta['total']);
        $this->assertEmpty($delta['breakdown']);
    }

    public function test_answer_validation_rules_for_each_type(): void
    {
        $schema = TradeFormSchema::validate([
            'version' => 1,
            'fields' => [
                ['key' => 'nb', 'label' => 'Nb', 'type' => 'number', 'required' => true,
                 'min' => 1, 'max' => 10],
                ['key' => 'flag', 'label' => 'Flag', 'type' => 'boolean'],
                ['key' => 'col', 'label' => 'Col', 'type' => 'select', 'required' => true,
                 'options' => [['value' => 'a', 'label' => 'A'], ['value' => 'b', 'label' => 'B']]],
                ['key' => 'ms', 'label' => 'Ms', 'type' => 'multiselect',
                 'options' => [['value' => 'x', 'label' => 'X']]],
                ['key' => 'txt', 'label' => 'Txt', 'type' => 'text', 'max_length' => 50],
            ],
        ])['normalized'];

        $rules = TradeFormSchema::answerValidationRules($schema, 'answers');

        $this->assertContains('required', $rules['answers.nb']);
        $this->assertContains('numeric', $rules['answers.nb']);
        $this->assertContains('min:1', $rules['answers.nb']);
        $this->assertContains('max:10', $rules['answers.nb']);

        $this->assertContains('nullable', $rules['answers.flag']);
        $this->assertContains('boolean', $rules['answers.flag']);

        $this->assertContains('in:a,b', $rules['answers.col']);

        $this->assertContains('array', $rules['answers.ms']);
        $this->assertContains('in:x', $rules['answers.ms.*']);

        $this->assertContains('max:50', $rules['answers.txt']);
    }
}
