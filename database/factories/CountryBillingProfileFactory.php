<?php

namespace Database\Factories;

use App\Models\Country;
use App\Models\CountryBillingProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class CountryBillingProfileFactory extends Factory
{
    protected $model = CountryBillingProfile::class;

    public function definition(): array
    {
        return [
            'country_id' => fn () => Country::factory()->create()->id,
            'currency_code' => 'EUR',
            'currency_symbol' => 'EUR',
            'currency_position' => 'after',
            'invoice_prefix' => 'INV',
            'quote_prefix' => 'QUO',
            'tax_label' => 'TVA',
            'default_tax_rate' => 21.00,
            'prices_include_tax' => false,
            'rounding_mode' => 'half_up',
            'decimal_separator' => ',',
            'thousands_separator' => '.',
            'payment_terms_days' => 30,
            'quote_validity_days' => 30,
            'date_format' => 'd/m/Y',
            'time_format' => 'H:i',
            'requires_vat_number' => true,
            'supports_invoicing' => true,
            'supports_credit_notes' => true,
            'invoice_settings' => null,
            'payment_settings' => null,
            'metadata' => null,
        ];
    }
}
