<?php

namespace Tests\Unit\Services;

use App\Models\Trade;
use App\Services\Booking\TradeFormRenderer;
use Tests\TestCase;

/**
 * Unit tests for TradeFormRenderer.
 *
 * These tests do NOT hit the database — Trade models are built
 * in-memory using make() and manually setting booking_form_schema.
 */
class TradeFormRendererTest extends TestCase
{
    private TradeFormRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new TradeFormRenderer();
    }

    // ─────────────────────────────────────────
    // 1. Empty / null schema returns []
    // ─────────────────────────────────────────

    public function test_returns_empty_array_when_schema_is_null(): void
    {
        $trade = new Trade(['booking_form_schema' => null]);

        $this->assertSame([], $this->renderer->fieldsForTrade($trade));
    }

    public function test_returns_empty_array_when_schema_is_empty_array(): void
    {
        $trade = new Trade(['booking_form_schema' => []]);

        $this->assertSame([], $this->renderer->fieldsForTrade($trade));
    }

    public function test_returns_empty_array_when_fields_array_is_empty(): void
    {
        $trade = new Trade(['booking_form_schema' => ['version' => 1, 'fields' => []]]);

        $this->assertSame([], $this->renderer->fieldsForTrade($trade));
    }

    // ─────────────────────────────────────────
    // 2. Normalized structure
    // ─────────────────────────────────────────

    public function test_normalizes_text_field(): void
    {
        $trade = new Trade([
            'booking_form_schema' => [
                'version' => 1,
                'fields'  => [
                    [
                        'key'         => 'description',
                        'label'       => 'Description',
                        'type'        => 'text',
                        'required'    => true,
                        'placeholder' => 'Enter description',
                    ],
                ],
            ],
        ]);

        $fields = $this->renderer->fieldsForTrade($trade);

        $this->assertCount(1, $fields);
        $this->assertSame('description', $fields[0]['name']);
        $this->assertSame('text', $fields[0]['type']);
        $this->assertSame('Description', $fields[0]['label']);
        $this->assertTrue($fields[0]['required']);
        $this->assertSame('Enter description', $fields[0]['placeholder']);
        $this->assertNull($fields[0]['options']);
    }

    public function test_normalizes_number_field_with_constraints(): void
    {
        $trade = new Trade([
            'booking_form_schema' => [
                'version' => 1,
                'fields'  => [
                    [
                        'key'      => 'surface_m2',
                        'label'    => 'Surface (m²)',
                        'type'     => 'number',
                        'required' => true,
                        'min'      => 1,
                        'max'      => 5000,
                        'unit'     => 'm²',
                    ],
                ],
            ],
        ]);

        $fields = $this->renderer->fieldsForTrade($trade);

        $this->assertCount(1, $fields);
        $this->assertSame('surface_m2', $fields[0]['name']);
        $this->assertSame('number', $fields[0]['type']);
        $this->assertSame('m²', $fields[0]['unit']);
        $this->assertSame(['min' => 1, 'max' => 5000], $fields[0]['validation']);
    }

    public function test_normalizes_select_field_with_options(): void
    {
        $trade = new Trade([
            'booking_form_schema' => [
                'version' => 1,
                'fields'  => [
                    [
                        'key'     => 'floor_type',
                        'label'   => 'Type de sol',
                        'type'    => 'select',
                        'options' => [
                            ['value' => 'parquet', 'label' => 'Parquet', 'price_delta' => 0],
                            ['value' => 'carrelage', 'label' => 'Carrelage', 'price_delta' => 10],
                        ],
                    ],
                ],
            ],
        ]);

        $fields = $this->renderer->fieldsForTrade($trade);

        $this->assertCount(1, $fields);
        $this->assertSame('select', $fields[0]['type']);
        $this->assertCount(2, $fields[0]['options']);
        // price_delta should NOT be leaked in the normalized output
        $this->assertArrayNotHasKey('price_delta', $fields[0]['options'][0]);
        $this->assertSame('parquet', $fields[0]['options'][0]['value']);
        $this->assertSame('Parquet', $fields[0]['options'][0]['label']);
    }

    public function test_normalizes_boolean_checkbox_field(): void
    {
        $trade = new Trade([
            'booking_form_schema' => [
                'version' => 1,
                'fields'  => [
                    [
                        'key'   => 'has_pets',
                        'label' => 'Présence animaux',
                        'type'  => 'boolean',
                    ],
                ],
            ],
        ]);

        $fields = $this->renderer->fieldsForTrade($trade);

        $this->assertCount(1, $fields);
        $this->assertSame('boolean', $fields[0]['type']);
        $this->assertFalse($fields[0]['required']);
        $this->assertNull($fields[0]['options']);
    }

    // ─────────────────────────────────────────
    // 3. Multiple fields are all returned
    // ─────────────────────────────────────────

    public function test_returns_all_fields(): void
    {
        $trade = new Trade([
            'booking_form_schema' => [
                'version' => 1,
                'fields'  => [
                    ['key' => 'field_a', 'label' => 'A', 'type' => 'text'],
                    ['key' => 'field_b', 'label' => 'B', 'type' => 'number'],
                    ['key' => 'field_c', 'label' => 'C', 'type' => 'textarea'],
                ],
            ],
        ]);

        $this->assertCount(3, $this->renderer->fieldsForTrade($trade));
    }

    // ─────────────────────────────────────────
    // 4. Unknown type falls back to 'text'
    // ─────────────────────────────────────────

    public function test_unknown_type_defaults_to_text(): void
    {
        $trade = new Trade([
            'booking_form_schema' => [
                'version' => 1,
                'fields'  => [
                    ['key' => 'foo', 'label' => 'Foo', 'type' => 'unknown_type'],
                ],
            ],
        ]);

        $fields = $this->renderer->fieldsForTrade($trade);

        $this->assertCount(1, $fields);
        $this->assertSame('text', $fields[0]['type']);
    }

    // ─────────────────────────────────────────
    // 5. Schema without 'fields' key but direct array uses it
    // ─────────────────────────────────────────

    public function test_fields_key_resolved_from_version1_schema(): void
    {
        $trade = new Trade([
            'booking_form_schema' => [
                'fields' => [
                    ['key' => 'nb_rooms', 'label' => 'Nombre de pièces', 'type' => 'number'],
                ],
            ],
        ]);

        $fields = $this->renderer->fieldsForTrade($trade);

        $this->assertCount(1, $fields);
        $this->assertSame('nb_rooms', $fields[0]['name']);
    }

    // ─────────────────────────────────────────
    // 6. Malformed field (missing key/label) is silently skipped
    // ─────────────────────────────────────────

    public function test_malformed_field_without_key_is_skipped(): void
    {
        $trade = new Trade([
            'booking_form_schema' => [
                'fields' => [
                    ['label' => 'No Key', 'type' => 'text'],           // missing key
                    ['key' => 'valid_field', 'label' => 'OK', 'type' => 'text'],
                ],
            ],
        ]);

        $fields = $this->renderer->fieldsForTrade($trade);

        $this->assertCount(1, $fields);
        $this->assertSame('valid_field', $fields[0]['name']);
    }

    // ─────────────────────────────────────────
    // 7. Default value is passed through
    // ─────────────────────────────────────────

    public function test_default_value_is_included(): void
    {
        $trade = new Trade([
            'booking_form_schema' => [
                'fields' => [
                    ['key' => 'nb_children', 'label' => 'Enfants', 'type' => 'number', 'default' => 1],
                ],
            ],
        ]);

        $fields = $this->renderer->fieldsForTrade($trade);

        $this->assertSame(1, $fields[0]['default']);
    }

    // ─────────────────────────────────────────
    // 8. Textarea max_length validation
    // ─────────────────────────────────────────

    public function test_textarea_max_length_becomes_validation(): void
    {
        $trade = new Trade([
            'booking_form_schema' => [
                'fields' => [
                    ['key' => 'notes', 'label' => 'Notes', 'type' => 'textarea', 'max_length' => 1000],
                ],
            ],
        ]);

        $fields = $this->renderer->fieldsForTrade($trade);

        $this->assertSame(['max' => 1000], $fields[0]['validation']);
    }
}
