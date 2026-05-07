<?php

namespace App\View\Components;

use App\Services\Localization\Money as MoneyService;
use Illuminate\View\Component;

/**
 * Phase 9 — Composant Blade <x-money :amount="..." currency="EUR" />
 *
 * Usage :
 *   <x-money :amount="1234.56" />                       → 1 234,56 €
 *   <x-money :amount="100" currency="USD" />            → $100.00
 *   <x-money :amount="100" currency="USD" convert />    → convertit en devise user
 *   <x-money :amount="$invoice->total_amount" :currency="$invoice->currency" />
 */
class Money extends Component
{
    public string $formatted;

    public function __construct(
        float $amount,
        string $currency = MoneyService::DEFAULT_CURRENCY,
        bool $convert = false,
        ?string $locale = null,
    ) {
        $service = app(MoneyService::class);

        $finalCurrency = $currency;

        // Mode 'convert' : convertit vers la devise préférée du user
        if ($convert) {
            $userCurrency = auth()->user()?->preferred_currency
                ?? auth()->user()?->currentOrganization?->preferred_currency
                ?? MoneyService::DEFAULT_CURRENCY;

            if ($userCurrency !== $currency) {
                $amount = $service->convert($amount, $currency, $userCurrency);
                $finalCurrency = $userCurrency;
            }
        }

        $this->formatted = $service->format($amount, $finalCurrency, $locale);
    }

    public function render()
    {
        return view('components.money');
    }
}
