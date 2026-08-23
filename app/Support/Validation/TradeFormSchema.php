<?php

namespace App\Support\Validation;

/** Traduit `trades.provider_form_schema` en règles de validation Laravel. */
final class TradeFormSchema
{
    /** Longueur retenue pour un champ texte sans `max` déclaré. */
    private const DEFAULT_TEXT_MAX = 255;

    /**
     * @param  array<string, mixed>|null  $schema  Contenu de `provider_form_schema`.
     * @return array<string, array<int, string>> Règles indexées par `trade_answers.<clé>`.
     */
    public static function rulesFor(?array $schema, string $prefix = 'trade_answers'): array
    {
        $rules = [];

        foreach (self::fields($schema) as $field) {
            $key = $field['key'] ?? null;

            if (! is_string($key) || $key === '') {
                continue;
            }

            $rules["{$prefix}.{$key}"] = self::rulesForField($field);
        }

        return $rules;
    }

    /**
     * Libellés du schéma, pour que les messages d'erreur nomment la question telle qu'elle est posée à l'écran plutôt que « trade_answers.intervention_radius_km ».
     *
     * @param  array<string, mixed>|null  $schema
     * @return array<string, string>
     */
    public static function attributesFor(?array $schema, string $prefix = 'trade_answers'): array
    {
        $attributes = [];

        foreach (self::fields($schema) as $field) {
            $key = $field['key'] ?? null;
            $label = $field['label'] ?? null;

            if (is_string($key) && $key !== '' && is_string($label) && $label !== '') {
                $attributes["{$prefix}.{$key}"] = mb_strtolower($label);
            }
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<int, string>
     */
    private static function rulesForField(array $field): array
    {
        $required = (bool) ($field['required'] ?? false);

        // `boolean` décoché arrive à `false`, que `required` rejetterait comme vide. `present`
        // exige la clé sans juger la valeur, ce qui est bien l'intention d'une case à cocher
        // obligatoire.
        $rules = [$required ? ($field['type'] === 'boolean' ? 'present' : 'required') : 'nullable'];

        switch ($field['type'] ?? 'text') {
            case 'number':
                $rules[] = 'numeric';
                if (isset($field['min']) && is_numeric($field['min'])) {
                    $rules[] = 'min:'.$field['min'];
                }
                if (isset($field['max']) && is_numeric($field['max'])) {
                    $rules[] = 'max:'.$field['max'];
                }
                break;

            case 'boolean':
                $rules[] = 'boolean';
                break;

            default:
                // Type inconnu traité comme du texte : un schéma enrichi plus tard ne doit pas
                // rendre l'inscription infranchissable en attendant que ce code le rattrape.
                $rules[] = 'string';
                $rules[] = 'max:'.(isset($field['max']) && is_numeric($field['max'])
                    ? (int) $field['max']
                    : self::DEFAULT_TEXT_MAX);
                break;
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>|null  $schema
     * @return array<int, array<string, mixed>>
     */
    private static function fields(?array $schema): array
    {
        $fields = $schema['fields'] ?? null;

        if (! is_array($fields)) {
            return [];
        }

        return array_values(array_filter($fields, 'is_array'));
    }
}
