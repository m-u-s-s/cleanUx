<?php

namespace App\Services\Assistant\Logging;

/** Phase 5.1 — Calcul de coût en USD à partir des tokens consommés. */
class CostCalculator
{
    /** Pricing en USD par 1M de tokens : [model_pattern => [input_per_1m, output_per_1m]] */
    private const PRICING = [
        // Anthropic
        'claude-sonnet-4' => [3.00,  15.00],
        'claude-sonnet-3-5' => [3.00,  15.00],
        'claude-sonnet-3' => [3.00,  15.00],
        'claude-opus-4' => [15.00, 75.00],
        'claude-opus-3' => [15.00, 75.00],
        'claude-haiku-4' => [0.80,  4.00],
        'claude-haiku-3-5' => [0.80,  4.00],
        'claude-haiku-3' => [0.25,  1.25],

        // OpenAI
        'gpt-4o-mini' => [0.15,  0.60],
        'gpt-4o' => [2.50,  10.00],
        'gpt-4-turbo' => [10.00, 30.00],

        // Mistral
        'mistral-small' => [0.20,  0.60],
        'mistral-large' => [3.00,  9.00],
    ];

    /**
     * Calcule le coût en USD pour un appel.
     *
     * @return float|null null si model non reconnu
     */
    public function compute(?string $model, ?int $inputTokens, ?int $outputTokens): ?float
    {
        if (! $model || $inputTokens === null || $outputTokens === null) {
            return null;
        }

        $pricing = $this->resolvePricing($model);
        if (! $pricing) {
            return null;
        }

        [$inputPer1M, $outputPer1M] = $pricing;

        $inputCost = ($inputTokens / 1_000_000) * $inputPer1M;
        $outputCost = ($outputTokens / 1_000_000) * $outputPer1M;

        return round($inputCost + $outputCost, 6);
    }

    /** Retourne [input_per_1m, output_per_1m] ou null si model non reconnu. */
    private function resolvePricing(string $model): ?array
    {
        $lower = mb_strtolower($model);

        // Match exact d'abord
        foreach (self::PRICING as $pattern => $rates) {
            if ($lower === $pattern) {
                return $rates;
            }
        }

        // Fallback : startsWith (pour matcher claude-sonnet-4-20250514 par ex.)
        foreach (self::PRICING as $pattern => $rates) {
            if (str_starts_with($lower, $pattern)) {
                return $rates;
            }
        }

        return null;
    }
}
