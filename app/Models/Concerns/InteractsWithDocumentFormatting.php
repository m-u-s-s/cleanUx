<?php

namespace App\Models\Concerns;

use Illuminate\Support\Carbon;

trait InteractsWithDocumentFormatting
{
    protected function documentFormattingPayload(): array
    {
        return array_merge(
            (array) data_get($this->snapshot ?? [], 'document_formatting', []),
            (array) data_get($this->meta ?? [], 'document_formatting', []),
        );
    }

    public function documentCurrencySymbol(): string
    {
        $payload = $this->documentFormattingPayload();

        return (string) ($payload['currency_symbol'] ?? match ((string) ($this->currency ?? 'EUR')) {
            'USD' => '$',
            'GBP' => '£',
            'CHF' => 'CHF',
            default => '€',
        });
    }

    public function documentCurrencyPosition(): string
    {
        return (string) ($this->documentFormattingPayload()['currency_position'] ?? 'after');
    }

    public function documentDecimalSeparator(): string
    {
        return (string) ($this->documentFormattingPayload()['decimal_separator'] ?? ',');
    }

    public function documentThousandsSeparator(): string
    {
        return (string) ($this->documentFormattingPayload()['thousands_separator'] ?? ' ');
    }

    public function documentDateFormat(): string
    {
        return (string) ($this->documentFormattingPayload()['date_format'] ?? 'd/m/Y');
    }

    public function documentTaxLabel(): string
    {
        return (string) ($this->documentFormattingPayload()['tax_label'] ?? 'TVA');
    }

    public function formatDocumentMoney(float|int|string|null $amount): string
    {
        $formatted = number_format(
            (float) $amount,
            2,
            $this->documentDecimalSeparator(),
            $this->documentThousandsSeparator(),
        );

        return $this->documentCurrencyPosition() === 'before'
            ? $this->documentCurrencySymbol() . ' ' . $formatted
            : $formatted . ' ' . $this->documentCurrencySymbol();
    }

    public function formatDocumentDate($date): string
    {
        if (! $date) {
            return '—';
        }

        if (! $date instanceof Carbon) {
            $date = Carbon::parse($date);
        }

        return $date->format($this->documentDateFormat());
    }
}
