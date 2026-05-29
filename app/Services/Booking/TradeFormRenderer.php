<?php

namespace App\Services\Booking;

use App\Models\Trade;

/**
 * TradeFormRenderer — normalise le booking_form_schema d'un Trade
 * en définitions de champs exploitables côté API et front-end.
 *
 * La normalisation garantit que les champs renvoyés respectent
 * toujours la même structure, indépendamment de ce qui est stocké
 * en base, et ne révèle jamais les données internes de pricing.
 */
final class TradeFormRenderer
{
    /** Types de champ exposés au client. */
    private const ALLOWED_TYPES = ['text', 'number', 'select', 'checkbox', 'textarea', 'boolean', 'multiselect'];

    /**
     * Retourne les définitions de champs normalisées pour un Trade donné.
     *
     * @return array<int, array{name: string, type: string, label: string, required: bool, options: mixed, placeholder: string|null, validation: mixed}>
     */
    public function fieldsForTrade(Trade $trade): array
    {
        $schema = $trade->booking_form_schema ?? [];

        if (empty($schema) || ! is_array($schema)) {
            return [];
        }

        $fields = $schema['fields'] ?? $schema;

        if (! is_array($fields) || empty($fields)) {
            return [];
        }

        return collect($fields)
            ->filter(fn ($field) => is_array($field) && isset($field['key'], $field['label']))
            ->map(fn ($field) => $this->normalizeField($field))
            ->values()
            ->all();
    }

    private function normalizeField(array $field): array
    {
        $type = $field['type'] ?? 'text';

        if (! in_array($type, self::ALLOWED_TYPES, true)) {
            $type = 'text';
        }

        return [
            'name' => $field['key'],
            'type' => $type,
            'label' => $field['label'],
            'required' => (bool) ($field['required'] ?? false),
            'options' => $this->normalizeOptions($field),
            'placeholder' => isset($field['placeholder']) ? (string) $field['placeholder'] : null,
            'validation' => $this->buildValidation($field),
            'help' => isset($field['help']) ? (string) $field['help'] : null,
            'unit' => isset($field['unit']) ? (string) $field['unit'] : null,
            'default' => $field['default'] ?? null,
        ];
    }

    /** @return array<int, array{value: string, label: string}>|null */
    private function normalizeOptions(array $field): ?array
    {
        $options = $field['options'] ?? null;

        if (! is_array($options) || empty($options)) {
            return null;
        }

        return collect($options)
            ->map(fn ($opt) => [
                'value' => (string) ($opt['value'] ?? ''),
                'label' => (string) ($opt['label'] ?? ''),
            ])
            ->filter(fn ($opt) => $opt['value'] !== '' && $opt['label'] !== '')
            ->values()
            ->all();
    }

    /** @return array<string, mixed>|null */
    private function buildValidation(array $field): ?array
    {
        $rules = $field['validation'] ?? null;

        if ($rules !== null) {
            return is_array($rules) ? $rules : null;
        }

        // Construct from field constraints when no explicit validation
        $built = [];

        if (($field['type'] ?? '') === 'number') {
            if (isset($field['min'])) {
                $built['min'] = $field['min'];
            }
            if (isset($field['max'])) {
                $built['max'] = $field['max'];
            }
        }

        if (in_array($field['type'] ?? '', ['text', 'textarea'], true) && isset($field['max_length'])) {
            $built['max'] = $field['max_length'];
        }

        return empty($built) ? null : $built;
    }
}
