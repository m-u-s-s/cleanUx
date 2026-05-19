<?php

namespace App\Services\PricingV2;

use App\Models\AbPricingExperiment;
use App\Models\PriceQuote;
use App\Models\PricingRule;
use App\Models\ServiceCatalogV2;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * PricingEngine v2 — calcule un quote en appliquant les pricing_rules.
 *
 * Workflow :
 *   1. Charge le service catalog (active)
 *   2. Sanitize les variables (whitelist via config)
 *   3. Détermine variant A/B (deterministic crc32(experiment.code:user_id))
 *   4. Filtre les rules applicables (service_code OR trade_code match + valid_from/until)
 *   5. Trie par priority asc, évalue applies_when via PricingDsl
 *   6. Applique adjustments dans l'ordre, clamp final entre min/max du service
 *   7. Persist PriceQuote ledger + ActivityLogger
 *   8. Idempotent via key
 *
 * Soft-fail : si rule lève une exception, on l'ignore + Log warning, on continue
 * avec les autres rules.
 */
class PricingEngine
{
    public function __construct(protected PricingDsl $dsl)
    {
    }

    /**
     * @param array<string,mixed> $variables
     */
    public function quote(
        string $serviceCode,
        array $variables = [],
        ?User $user = null,
        ?string $idempotencyKey = null,
        ?int $bookingId = null,
    ): PriceQuote {
        if (! Config::get('pricing_v2.enabled', true)) {
            throw ValidationException::withMessages(['module' => 'Pricing v2 disabled.']);
        }

        if ($idempotencyKey) {
            $existing = PriceQuote::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }
        }

        $service = ServiceCatalogV2::query()->where('code', $serviceCode)->active()->first();
        if (! $service) {
            throw ValidationException::withMessages(['service_code' => "Service '{$serviceCode}' introuvable ou inactif."]);
        }

        $variables = $this->sanitizeVariables($variables);
        $variant = $this->assignVariant($service, $user);

        $appliedRules = [];
        $currentPrice = (int) $service->base_price_cents;

        $rules = $this->loadApplicableRules($service, $variant);

        foreach ($rules as $rule) {
            try {
                if (! $this->dsl->evaluate((array) $rule->applies_when, $variables)) {
                    continue;
                }
                $before = $currentPrice;
                $currentPrice = $this->applyAdjustments($currentPrice, (array) $rule->adjustments, $variables);
                $appliedRules[] = [
                    'code' => $rule->code,
                    'priority' => $rule->priority,
                    'price_before_cents' => $before,
                    'price_after_cents' => $currentPrice,
                    'delta_cents' => $currentPrice - $before,
                ];
            } catch (\Throwable $e) {
                Log::warning('PricingEngine: rule eval failed', [
                    'rule' => $rule->code,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $currentPrice = $this->clamp($currentPrice, $service);

        $row = PriceQuote::create([
            'service_code' => $service->code,
            'trade_code' => $service->trade_code,
            'base_price_cents' => $service->base_price_cents,
            'computed_price_cents' => $currentPrice,
            'currency' => $service->currency,
            'variables_snapshot' => $variables,
            'applied_rules' => $appliedRules,
            'variant_label' => $variant,
            'user_id' => $user?->id,
            'booking_id' => $bookingId,
            'idempotency_key' => $idempotencyKey,
            'quoted_at' => now(),
        ]);

        ActivityLogger::log('pricing_v2.quoted', $row, [
            'service_code' => $service->code,
            'computed_price_cents' => $currentPrice,
            'rules_applied_count' => count($appliedRules),
            'variant' => $variant,
        ]);

        return $row;
    }

    /**
     * Pure compute : preview without persistence. Useful for "live price" UI.
     */
    public function preview(string $serviceCode, array $variables = [], ?User $user = null): array
    {
        $service = ServiceCatalogV2::query()->where('code', $serviceCode)->active()->first();
        if (! $service) {
            throw ValidationException::withMessages(['service_code' => "Service '{$serviceCode}' introuvable."]);
        }

        $variables = $this->sanitizeVariables($variables);
        $variant = $this->assignVariant($service, $user);
        $appliedRules = [];
        $currentPrice = (int) $service->base_price_cents;

        foreach ($this->loadApplicableRules($service, $variant) as $rule) {
            try {
                if (! $this->dsl->evaluate((array) $rule->applies_when, $variables)) {
                    continue;
                }
                $before = $currentPrice;
                $currentPrice = $this->applyAdjustments($currentPrice, (array) $rule->adjustments, $variables);
                $appliedRules[] = ['code' => $rule->code, 'delta_cents' => $currentPrice - $before];
            } catch (\Throwable $e) {
            }
        }

        $currentPrice = $this->clamp($currentPrice, $service);

        return [
            'service_code' => $service->code,
            'base_price_cents' => (int) $service->base_price_cents,
            'computed_price_cents' => $currentPrice,
            'currency' => $service->currency,
            'variant_label' => $variant,
            'applied_rules' => $appliedRules,
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, PricingRule>
     */
    protected function loadApplicableRules(ServiceCatalogV2 $service, ?string $variant): \Illuminate\Support\Collection
    {
        $now = now();
        $rules = PricingRule::query()
            ->active()
            ->where(function ($q) use ($service) {
                $q->where('service_code', $service->code)
                    ->orWhereNull('service_code');
            })
            ->where(function ($q) use ($service) {
                $q->where('trade_code', $service->trade_code)
                    ->orWhereNull('trade_code');
            })
            ->orderBy('priority')
            ->get()
            ->filter(fn (PricingRule $r) => $r->isWithinValidity($now));

        if ($variant) {
            $rules = $this->overlayExperimentRules($rules, $service, $variant);
        }

        return $rules;
    }

    /**
     * Allows experiments to override/extend the rule set for the assigned variant.
     */
    protected function overlayExperimentRules(\Illuminate\Support\Collection $rules, ServiceCatalogV2 $service, string $variant): \Illuminate\Support\Collection
    {
        $experiment = $this->resolveExperimentForService($service);
        if (! $experiment) {
            return $rules;
        }

        $variantDef = collect((array) $experiment->variants)->firstWhere('label', $variant);
        if (! $variantDef || empty($variantDef['rules_override'])) {
            return $rules;
        }

        $overrideCodes = (array) $variantDef['rules_override'];

        // Filter existing rules: keep only those whose code is in override list,
        // OR add new "virtual rules" inline. For simplicity, just filter to overrides.
        return $rules->whereIn('code', $overrideCodes)->values();
    }

    protected function resolveExperimentForService(ServiceCatalogV2 $service): ?AbPricingExperiment
    {
        if (! Config::get('pricing_v2.ab_test_enabled', true)) {
            return null;
        }

        return AbPricingExperiment::query()
            ->running()
            ->get()
            ->first(fn (AbPricingExperiment $e) => $e->appliesToService($service->code));
    }

    protected function assignVariant(ServiceCatalogV2 $service, ?User $user): ?string
    {
        $experiment = $this->resolveExperimentForService($service);
        if (! $experiment) {
            return null;
        }
        $variants = (array) $experiment->variants;
        if (empty($variants)) {
            return null;
        }

        $key = $user?->id ?? mt_rand(0, PHP_INT_MAX);
        $hash = crc32($experiment->code . ':' . $key);
        $idx = $hash % count($variants);

        return $variants[$idx]['label'] ?? null;
    }

    protected function applyAdjustments(int $currentPrice, array $adjustments, array $variables): int
    {
        $allowed = (array) Config::get('pricing_v2.adjustment_kinds', []);
        $price = $currentPrice;

        foreach ($adjustments as $adj) {
            $kind = (string) ($adj['kind'] ?? '');
            if (! in_array($kind, $allowed, true)) {
                continue;
            }
            $value = $adj['value'] ?? 0;

            switch ($kind) {
                case 'add_flat_cents':
                    $price += (int) $value;
                    break;
                case 'add_percent':
                    $price = (int) round($price * (1 + ((float) $value / 100)));
                    break;
                case 'multiply':
                    $price = (int) round($price * (float) $value);
                    break;
                case 'per_unit_cents':
                    $unitKey = (string) ($adj['unit_key'] ?? '');
                    $units = (float) ($variables[$unitKey] ?? 0);
                    $price += (int) round($units * (float) $value);
                    break;
                case 'set_minimum':
                    if ($price < (int) $value) {
                        $price = (int) $value;
                    }
                    break;
                case 'set_maximum':
                    if ($price > (int) $value) {
                        $price = (int) $value;
                    }
                    break;
                case 'replace_base':
                    $price = (int) $value;
                    break;
            }
        }

        return max(0, $price);
    }

    protected function clamp(int $price, ServiceCatalogV2 $service): int
    {
        if ($price < (int) $service->min_price_cents) {
            $price = (int) $service->min_price_cents;
        }
        if ($service->max_price_cents !== null && $price > (int) $service->max_price_cents) {
            $price = (int) $service->max_price_cents;
        }
        return max(0, $price);
    }

    protected function sanitizeVariables(array $variables): array
    {
        $allowed = (array) Config::get('pricing_v2.variable_keys', []);
        return array_intersect_key($variables, array_flip($allowed));
    }
}
