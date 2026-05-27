<?php

namespace Database\Factories;

use App\Models\Country;
use App\Models\CountryOperationalSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

class CountryOperationalSettingFactory extends Factory
{
    protected $model = CountryOperationalSetting::class;

    public function definition(): array
    {
        return [
            'country_id'                          => fn () => Country::factory()->create()->id,
            'booking_enabled'                     => true,
            'mission_enabled'                     => true,
            'billing_enabled'                     => true,
            'partner_network_enabled'             => false,
            'readiness_stage'                     => 'ready_for_launch',
            'default_tax_rate'                    => 21.00,
            'currency_symbol'                     => 'EUR',
            'date_format'                         => 'd/m/Y',
            'time_format'                         => 'H:i',
            'address_format'                      => null,
            'phone_format'                        => null,
            'requires_vat_number_for_companies'   => true,
            'default_distance_unit'               => 'km',
            'default_surface_unit'                => 'm2',
            'local_rules'                         => null,
            'metadata'                            => null,
        ];
    }
}
