<?php

namespace App\View\Components;

use App\Services\Localization\Money as MoneyService;
use Illuminate\View\Component;

/** Phase 9 — Composant Blade <x-money :amount="..." currency="EUR" /> Usage : <x-money :amount="1234.56" /> → 1 234,56 € <x-money :amount="100" currency="USD" /> → $100.00 <x-money :amount="100" currency="USD" convert /> → convertit en devise user <x-money :amount="$invoice->total_amount" :currency="$invoice->currency" /> */
class Money extends Component
{
    public string $formatted;

    public function __construct(
        float $amount,
        ?string $currency = null,
        bool $convert = false,
        ?string $locale = null,
        ?int $decimals = null,
    ) {
        $service = app(MoneyService::class);

        // Sans devise explicite, on prend CELLE DU CONTEXTE — pas l'euro. Une vue qui affiche
        // un montant sans savoir sa devise en montrait un faux au client marocain.
        $finalCurrency = $currency ?: self::deviseDuContexte();

        // Mode 'convert' : convertit vers la devise préférée du user
        if ($convert) {
            $userCurrency = self::deviseDuContexte();

            if ($userCurrency !== $finalCurrency) {
                $amount = $service->convert($amount, $finalCurrency, $userCurrency);
                $finalCurrency = $userCurrency;
            }
        }

        $this->formatted = $service->format($amount, $finalCurrency, $locale, $decimals);
    }

    /** La devise du compte connecte, puis celle de son organisation, puis celle du marche. */
    public static function deviseDuContexte(): string
    {
        $utilisateur = auth()->user();

        $posee = $utilisateur?->preferred_currency
            ?: $utilisateur?->currentOrganization?->preferred_currency
            ?: $utilisateur?->currentOrganization?->default_currency;

        return is_string($posee) && trim($posee) !== ''
            ? strtoupper(trim($posee))
            : strtoupper((string) config('fx.base_currency', MoneyService::DEFAULT_CURRENCY));
    }

    public function render()
    {
        return view('components.money');
    }
}
