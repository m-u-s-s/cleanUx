<?php

namespace Tests\Feature;

use App\Livewire\Admin\TradeFormPreview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TradeFormPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_loads_a_valid_schema_array_at_mount(): void
    {
        $schema = [
            'version' => 1,
            'fields' => [
                ['key' => 'nb', 'label' => 'Nb', 'type' => 'number',
                 'pricing' => ['modifier' => 'per_unit', 'value' => 5]],
            ],
        ];

        Livewire::test(TradeFormPreview::class, ['schemaInput' => $schema])
            ->assertSet('schemaErrors', [])
            ->assertCount('tradeFormSchema.fields', 1);
    }

    public function test_preview_accepts_json_string_input(): void
    {
        $json = json_encode([
            'version' => 1,
            'fields' => [
                ['key' => 'urgent', 'label' => 'Urgent', 'type' => 'boolean',
                 'pricing' => ['modifier' => 'fixed', 'value' => 20]],
            ],
        ]);

        Livewire::test(TradeFormPreview::class, ['schemaInput' => $json])
            ->assertSet('schemaErrors', [])
            ->assertCount('tradeFormSchema.fields', 1);
    }

    public function test_preview_reports_invalid_json(): void
    {
        Livewire::test(TradeFormPreview::class, ['schemaInput' => '{ broken json'])
            ->assertNotSet('schemaErrors', [])
            ->assertSet('tradeFormSchema', null);
    }

    public function test_preview_reports_invalid_schema_structure(): void
    {
        Livewire::test(TradeFormPreview::class, ['schemaInput' => ['version' => 1]])
            ->assertNotSet('schemaErrors', [])
            ->assertSet('tradeFormSchema', null);
    }

    public function test_preview_updates_price_delta_when_answer_changes(): void
    {
        $schema = [
            'version' => 1,
            'fields' => [
                ['key' => 'nb', 'label' => 'Nb', 'type' => 'number',
                 'pricing' => ['modifier' => 'per_unit', 'value' => 5]],
                ['key' => 'urgent', 'label' => 'Urgent', 'type' => 'boolean',
                 'pricing' => ['modifier' => 'fixed', 'value' => 20]],
            ],
        ];

        $component = Livewire::test(TradeFormPreview::class, [
            'schemaInput' => $schema,
            'basePrice'   => 100.0,
        ]);

        // No answers yet
        $delta = $component->get('tradeFormPriceDelta');
        $this->assertSame(0.0, $delta['total']);

        // Fill in answers
        $component
            ->set('tradeFormAnswers.nb', 4)
            ->set('tradeFormAnswers.urgent', true);

        $delta = $component->get('tradeFormPriceDelta');
        // 4 × 5 = 20 + 20 = 40
        $this->assertSame(40.0, $delta['total']);
    }

    public function test_reloading_schema_resets_answers_to_defaults(): void
    {
        $schemaA = ['version' => 1, 'fields' => [
            ['key' => 'a', 'label' => 'A', 'type' => 'text', 'default' => 'hello'],
        ]];
        $schemaB = ['version' => 1, 'fields' => [
            ['key' => 'b', 'label' => 'B', 'type' => 'number', 'default' => 7],
        ]];

        Livewire::test(TradeFormPreview::class, ['schemaInput' => $schemaA])
            ->assertSet('tradeFormAnswers.a', 'hello')
            ->set('schemaInput', json_encode($schemaB))
            ->assertSet('tradeFormAnswers.b', 7)
            ->assertSet('tradeFormAnswers.a', null);
    }
}
