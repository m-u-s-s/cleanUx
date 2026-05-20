<?php

namespace App\Services\AccountingV2;

class ChartOfAccounts
{
    /**
     * Retourne le nom d'un compte ou null si inconnu.
     */
    public function name(string $code): ?string
    {
        $accounts = (array) config('accounting_v2.chart_of_accounts', []);
        return $accounts[$code]['name'] ?? null;
    }

    public function exists(string $code): bool
    {
        return $this->name($code) !== null;
    }

    public function classOf(string $code): ?int
    {
        $accounts = (array) config('accounting_v2.chart_of_accounts', []);
        return isset($accounts[$code]) ? (int) ($accounts[$code]['class'] ?? 0) : null;
    }

    /**
     * @return array<string, array{name:string,class:int}>
     */
    public function all(): array
    {
        return (array) config('accounting_v2.chart_of_accounts', []);
    }

    /**
     * Compte client par défaut (PCG 411).
     */
    public function clientAccount(): string
    {
        return '411000';
    }

    public function providerAccount(): string
    {
        return '401000';
    }

    public function vatCollected(): string
    {
        return '4457';
    }

    public function vatDeductible(): string
    {
        return '4456';
    }

    public function bankAccount(string $kind = 'stripe'): string
    {
        return match ($kind) {
            'stripe' => '512100',
            default => '512',
        };
    }

    public function salesAccount(string $kind = 'booking'): string
    {
        return match ($kind) {
            'subscription' => '701200',
            'cancellation_fee' => '708',
            default => '701100',
        };
    }

    public function stripeWalletAccount(): string
    {
        return '467';
    }

    public function refundAccount(): string
    {
        return '658100';
    }

    public function stripeFeesAccount(): string
    {
        return '627';
    }

    public function platformCommissionAccount(): string
    {
        return '622';
    }
}
