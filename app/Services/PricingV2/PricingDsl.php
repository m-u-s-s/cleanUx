<?php

namespace App\Services\PricingV2;

use Illuminate\Support\Facades\Config;

/**
 * PricingDsl — évaluateur whitelisté pour `pricing_rules.applies_when`.
 *
 * DSL JSON (récursif) :
 *   {"and": [ {leaf}, {leaf} ]}
 *   {"or":  [ {leaf}, {leaf} ]}
 *   {"not": {leaf}}
 *
 * Leaf :
 *   {"field": "surface_m2", "op": "gte", "value": 50}
 *
 * Champs whitelistés via `config('pricing_v2.variable_keys')`.
 * Opérateurs whitelistés via `config('pricing_v2.condition_operators')`.
 * Field/op invalide → la rule ne matche jamais (fail-closed).
 */
class PricingDsl
{
    /**
     * @param array<string,mixed> $variables
     */
    public function evaluate(array $tree, array $variables): bool
    {
        if (empty($tree)) {
            return true;  // empty conditions = always match
        }

        if (isset($tree['and']) && is_array($tree['and'])) {
            foreach ($tree['and'] as $sub) {
                if (! $this->evaluate($sub, $variables)) {
                    return false;
                }
            }
            return true;
        }

        if (isset($tree['or']) && is_array($tree['or'])) {
            foreach ($tree['or'] as $sub) {
                if ($this->evaluate($sub, $variables)) {
                    return true;
                }
            }
            return false;
        }

        if (isset($tree['not'])) {
            return ! $this->evaluate($tree['not'], $variables);
        }

        return $this->evaluateLeaf($tree, $variables);
    }

    protected function evaluateLeaf(array $leaf, array $variables): bool
    {
        $field = (string) ($leaf['field'] ?? '');
        $op = (string) ($leaf['op'] ?? '');
        $value = $leaf['value'] ?? null;

        $allowedFields = (array) Config::get('pricing_v2.variable_keys', []);
        $allowedOps = (array) Config::get('pricing_v2.condition_operators', []);

        if (! in_array($field, $allowedFields, true) || ! in_array($op, $allowedOps, true)) {
            return false;  // fail-closed
        }

        $varValue = $variables[$field] ?? null;

        return match ($op) {
            'eq'  => $varValue == $value,
            'neq' => $varValue != $value,
            'in'  => is_array($value) && in_array($varValue, $value, true),
            'not_in' => is_array($value) && ! in_array($varValue, $value, true),
            'gt'  => $varValue !== null && $varValue > $value,
            'gte' => $varValue !== null && $varValue >= $value,
            'lt'  => $varValue !== null && $varValue < $value,
            'lte' => $varValue !== null && $varValue <= $value,
            'between' => is_array($value) && $varValue !== null
                && $varValue >= ($value[0] ?? PHP_INT_MIN)
                && $varValue <= ($value[1] ?? PHP_INT_MAX),
            'is_true' => $varValue === true || $varValue === 1 || $varValue === '1',
            'is_false' => $varValue === false || $varValue === 0 || $varValue === '0' || $varValue === null,
            'is_null' => $varValue === null,
            'is_not_null' => $varValue !== null,
            'contains' => is_string($varValue) && is_string($value) && str_contains($varValue, $value),
            default => false,
        };
    }
}
