<?php

namespace App\Support;

/**
 * Helper de schema de formulaire dynamique par Trade.
 *
 * Le schema est un JSON stocké sur Trade.booking_form_schema. Il décrit
 * les champs que le client doit remplir quand il réserve un service de
 * ce métier (remplace les champs cleaning hardcodés).
 *
 * Format :
 * {
 *   "version": 1,
 *   "fields": [
 *     {
 *       "key": "nb_enfants",              // identifiant unique du champ (snake_case)
 *       "label": "Nombre d'enfants",      // libellé affiché au client
 *       "type": "number",                  // number|boolean|select|multiselect|text|textarea
 *       "required": true,                  // optionnel, false par défaut
 *       "default": 1,                      // valeur initiale, optionnel
 *       "help": "Combien d'enfants...",    // optionnel
 *       "unit": "enfants",                 // optionnel (m², h, étage…)
 *
 *       // number :
 *       "min": 1, "max": 10, "step": 1,
 *
 *       // text / textarea :
 *       "max_length": 2000,
 *
 *       // select / multiselect :
 *       "options": [
 *         {"value": "simple", "label": "Serrure simple", "price_delta": 0},
 *         {"value": "blindee", "label": "Porte blindée", "price_delta": 50}
 *       ],
 *
 *       // pricing direct (number/boolean uniquement, select via options[].price_delta) :
 *       "pricing": {"modifier": "per_unit|fixed|percent", "value": 5.0}
 *     }
 *   ]
 * }
 */
class TradeFormSchema
{
    public const SUPPORTED_TYPES = ['number', 'boolean', 'select', 'multiselect', 'text', 'textarea'];
    public const SUPPORTED_MODIFIERS = ['fixed', 'percent', 'per_unit'];

    /**
     * Valide la structure d'un schema. Renvoie ['ok' => bool, 'errors' => [...], 'normalized' => [...]].
     *
     * - Si schema est null ou [] → ok = true, normalized = ['version' => 1, 'fields' => []]
     * - Sinon, valide la forme (clés uniques, types supportés, options bien formées…)
     */
    public static function validate(mixed $schema): array
    {
        $errors = [];

        if ($schema === null || $schema === '' || $schema === []) {
            return ['ok' => true, 'errors' => [], 'normalized' => ['version' => 1, 'fields' => []]];
        }

        if (! is_array($schema)) {
            return ['ok' => false, 'errors' => ['Le schema doit être un objet JSON.'], 'normalized' => null];
        }

        $fields = $schema['fields'] ?? null;
        if (! is_array($fields)) {
            return ['ok' => false, 'errors' => ['Le schema doit contenir un tableau "fields".'], 'normalized' => null];
        }

        $normalizedFields = [];
        $seenKeys = [];

        foreach ($fields as $idx => $field) {
            $position = $idx + 1;

            if (! is_array($field)) {
                $errors[] = "Champ #$position : doit être un objet.";
                continue;
            }

            $key = $field['key'] ?? null;
            if (! is_string($key) || ! preg_match('/^[a-z][a-z0-9_]{0,79}$/', $key)) {
                $errors[] = "Champ #$position : la clé \"key\" est requise (snake_case, max 80 chars, commençant par une lettre).";
                continue;
            }
            if (isset($seenKeys[$key])) {
                $errors[] = "Champ #$position : clé \"$key\" dupliquée.";
                continue;
            }
            $seenKeys[$key] = true;

            $type = $field['type'] ?? null;
            if (! in_array($type, self::SUPPORTED_TYPES, true)) {
                $errors[] = "Champ \"$key\" : type \"$type\" non supporté (autorisés: ".implode(', ', self::SUPPORTED_TYPES).').';
                continue;
            }

            $label = $field['label'] ?? null;
            if (! is_string($label) || trim($label) === '') {
                $errors[] = "Champ \"$key\" : label requis.";
                continue;
            }

            $normalized = [
                'key'       => $key,
                'label'     => trim($label),
                'type'      => $type,
                'required'  => (bool) ($field['required'] ?? false),
                'help'      => isset($field['help']) && is_string($field['help']) ? trim($field['help']) : null,
                'unit'      => isset($field['unit']) && is_string($field['unit']) ? trim($field['unit']) : null,
                'default'   => $field['default'] ?? null,
            ];

            // Spécifique au type
            if ($type === 'number') {
                $normalized['min']  = isset($field['min']) && is_numeric($field['min']) ? (float) $field['min'] : null;
                $normalized['max']  = isset($field['max']) && is_numeric($field['max']) ? (float) $field['max'] : null;
                $normalized['step'] = isset($field['step']) && is_numeric($field['step']) ? (float) $field['step'] : null;

                if ($normalized['min'] !== null && $normalized['max'] !== null && $normalized['min'] > $normalized['max']) {
                    $errors[] = "Champ \"$key\" : min > max.";
                }
            }

            if (in_array($type, ['text', 'textarea'], true)) {
                $normalized['max_length'] = isset($field['max_length']) && is_int($field['max_length']) && $field['max_length'] > 0
                    ? $field['max_length']
                    : null;
            }

            if (in_array($type, ['select', 'multiselect'], true)) {
                $options = $field['options'] ?? null;
                if (! is_array($options) || $options === []) {
                    $errors[] = "Champ \"$key\" : type $type requiert un tableau \"options\" non vide.";
                    continue;
                }

                $normalizedOptions = [];
                $seenValues = [];
                foreach ($options as $optIdx => $opt) {
                    if (! is_array($opt)) {
                        $errors[] = "Champ \"$key\", option #" . ($optIdx + 1) . " : doit être un objet.";
                        continue;
                    }
                    $value = $opt['value'] ?? null;
                    $optLabel = $opt['label'] ?? null;
                    if (! is_string($value) && ! is_int($value)) {
                        $errors[] = "Champ \"$key\", option #" . ($optIdx + 1) . " : \"value\" requis (string ou int).";
                        continue;
                    }
                    $value = (string) $value;
                    if (! is_string($optLabel) || trim($optLabel) === '') {
                        $errors[] = "Champ \"$key\", option \"$value\" : label requis.";
                        continue;
                    }
                    if (isset($seenValues[$value])) {
                        $errors[] = "Champ \"$key\", option \"$value\" : value dupliquée.";
                        continue;
                    }
                    $seenValues[$value] = true;
                    $normalizedOptions[] = [
                        'value'       => $value,
                        'label'       => trim($optLabel),
                        'price_delta' => isset($opt['price_delta']) && is_numeric($opt['price_delta'])
                            ? (float) $opt['price_delta']
                            : 0.0,
                    ];
                }
                $normalized['options'] = $normalizedOptions;
            }

            // Pricing (number / boolean uniquement)
            $pricing = $field['pricing'] ?? null;
            if (is_array($pricing) && isset($pricing['modifier'])) {
                $modifier = $pricing['modifier'];
                $value = $pricing['value'] ?? null;
                if (! in_array($modifier, self::SUPPORTED_MODIFIERS, true)) {
                    $errors[] = "Champ \"$key\" : pricing.modifier invalide.";
                } elseif (! is_numeric($value)) {
                    $errors[] = "Champ \"$key\" : pricing.value doit être numérique.";
                } elseif (! in_array($type, ['number', 'boolean'], true)) {
                    $errors[] = "Champ \"$key\" : pricing direct n'est pas supporté pour le type \"$type\" (utiliser options[].price_delta).";
                } else {
                    $normalized['pricing'] = [
                        'modifier' => $modifier,
                        'value'    => (float) $value,
                    ];
                }
            }

            $normalizedFields[] = $normalized;
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors, 'normalized' => null];
        }

        return [
            'ok'         => true,
            'errors'     => [],
            'normalized' => ['version' => (int) ($schema['version'] ?? 1), 'fields' => $normalizedFields],
        ];
    }

    /**
     * Retourne les règles de validation Laravel pour les answers correspondant
     * à ce schema. Le préfixe est par exemple "tradeFormAnswers".
     */
    public static function answerValidationRules(array $schema, string $prefix): array
    {
        $fields = $schema['fields'] ?? [];
        $rules = [];

        foreach ($fields as $field) {
            $key = $field['key'];
            $base = $field['required'] ?? false ? ['required'] : ['nullable'];

            $path = "$prefix.$key";

            switch ($field['type']) {
                case 'number':
                    $r = array_merge($base, ['numeric']);
                    if (($field['min'] ?? null) !== null) $r[] = 'min:'.$field['min'];
                    if (($field['max'] ?? null) !== null) $r[] = 'max:'.$field['max'];
                    $rules[$path] = $r;
                    break;
                case 'boolean':
                    $rules[$path] = array_merge($base, ['boolean']);
                    break;
                case 'select':
                    $values = collect($field['options'] ?? [])->pluck('value')->all();
                    $r = $values !== []
                        ? array_merge($base, ['string', 'in:'.implode(',', $values)])
                        : array_merge($base, ['string']);
                    $rules[$path] = $r;
                    break;
                case 'multiselect':
                    $values = collect($field['options'] ?? [])->pluck('value')->all();
                    $rules[$path] = array_merge($base, ['array']);
                    if ($values !== []) {
                        $rules["$path.*"] = ['string', 'in:'.implode(',', $values)];
                    } else {
                        $rules["$path.*"] = ['string'];
                    }
                    break;
                case 'text':
                case 'textarea':
                    $r = array_merge($base, ['string']);
                    if (($field['max_length'] ?? null) !== null) $r[] = 'max:'.$field['max_length'];
                    $rules[$path] = $r;
                    break;
            }
        }

        return $rules;
    }

    /**
     * Construit le tableau des valeurs par défaut pour un schema (utilisé
     * par le trait Livewire à l'initialisation).
     */
    public static function defaultAnswers(array $schema): array
    {
        $out = [];
        foreach (($schema['fields'] ?? []) as $field) {
            $key = $field['key'];
            if (array_key_exists('default', $field) && $field['default'] !== null) {
                $out[$key] = $field['default'];
                continue;
            }
            $out[$key] = match ($field['type']) {
                'boolean'      => false,
                'multiselect'  => [],
                'number'       => null,
                default        => '',
            };
        }
        return $out;
    }

    /**
     * Calcule le delta de prix appliqué par les answers. Renvoie :
     *   ['total' => float, 'breakdown' => [['key' => ..., 'label' => ..., 'delta' => float]]]
     *
     * - number per_unit  : answer × pricing.value
     * - number fixed     : pricing.value si answer > 0
     * - boolean fixed    : pricing.value si true
     * - boolean percent  : base_price × pricing.value / 100 si true
     * - select           : options[answer].price_delta
     * - multiselect      : somme des options sélectionnées price_delta
     */
    public static function computePriceDelta(array $schema, array $answers, float $basePrice = 0.0): array
    {
        $breakdown = [];
        $total = 0.0;

        foreach (($schema['fields'] ?? []) as $field) {
            $key = $field['key'];
            $answer = $answers[$key] ?? null;
            $delta = 0.0;

            switch ($field['type']) {
                case 'number':
                    $pricing = $field['pricing'] ?? null;
                    if ($pricing && is_numeric($answer) && (float) $answer > 0) {
                        $delta = match ($pricing['modifier']) {
                            'per_unit' => (float) $answer * (float) $pricing['value'],
                            'fixed'    => (float) $pricing['value'],
                            default    => 0.0,
                        };
                    }
                    break;

                case 'boolean':
                    $pricing = $field['pricing'] ?? null;
                    if ($pricing && (bool) $answer === true) {
                        $delta = match ($pricing['modifier']) {
                            'fixed'   => (float) $pricing['value'],
                            'percent' => $basePrice * (float) $pricing['value'] / 100.0,
                            default   => 0.0,
                        };
                    }
                    break;

                case 'select':
                    if ($answer !== null && $answer !== '') {
                        foreach (($field['options'] ?? []) as $opt) {
                            if ((string) $opt['value'] === (string) $answer) {
                                $delta = (float) ($opt['price_delta'] ?? 0);
                                break;
                            }
                        }
                    }
                    break;

                case 'multiselect':
                    if (is_array($answer)) {
                        foreach (($field['options'] ?? []) as $opt) {
                            if (in_array((string) $opt['value'], array_map('strval', $answer), true)) {
                                $delta += (float) ($opt['price_delta'] ?? 0);
                            }
                        }
                    }
                    break;
            }

            if (abs($delta) > 0.0001) {
                $breakdown[] = [
                    'key'   => $key,
                    'label' => $field['label'],
                    'delta' => round($delta, 2),
                ];
                $total += $delta;
            }
        }

        return ['total' => round($total, 2), 'breakdown' => $breakdown];
    }
}
