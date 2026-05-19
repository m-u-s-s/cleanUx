<?php

namespace Database\Seeders;

use App\Models\PricingRule;
use App\Models\ServiceCatalogV2;
use Illuminate\Database\Seeder;

class PricingV2Seeder extends Seeder
{
    public function run(): void
    {
        $services = [
            [
                'code' => 'cleaning_standard',
                'name' => 'Nettoyage standard',
                'trade_code' => 'cleaning',
                'base_price_cents' => 5000,
                'unit' => 'per_visit',
                'min_price_cents' => 3000,
                'max_price_cents' => 100000,
            ],
            [
                'code' => 'painting_room',
                'name' => 'Peinture pièce standard',
                'trade_code' => 'painting',
                'base_price_cents' => 25000,
                'unit' => 'per_visit',
                'min_price_cents' => 15000,
                'max_price_cents' => 500000,
            ],
        ];

        foreach ($services as $svc) {
            ServiceCatalogV2::query()->updateOrCreate(
                ['code' => $svc['code']],
                array_merge($svc, ['is_active' => true, 'version' => 1, 'currency' => 'EUR']),
            );
        }

        $rules = [
            [
                'code' => 'surface_per_m2',
                'name' => 'Tarif par m² au-delà 50m²',
                'service_code' => 'cleaning_standard',
                'priority' => 10,
                'is_active' => true,
                'applies_when' => ['field' => 'surface_m2', 'op' => 'gte', 'value' => 50],
                'adjustments' => [
                    ['kind' => 'per_unit_cents', 'value' => 50, 'unit_key' => 'surface_m2'],
                ],
            ],
            [
                'code' => 'urgency_premium',
                'name' => 'Supplément urgence (<24h)',
                'service_code' => null,  // applies to all
                'priority' => 20,
                'is_active' => true,
                'applies_when' => ['field' => 'urgency', 'op' => 'eq', 'value' => 'urgent'],
                'adjustments' => [
                    ['kind' => 'add_percent', 'value' => 20],
                ],
            ],
            [
                'code' => 'recurrent_discount',
                'name' => 'Réduction client récurrent (-10%)',
                'service_code' => null,
                'priority' => 30,
                'is_active' => true,
                'applies_when' => ['field' => 'is_recurrent', 'op' => 'is_true', 'value' => null],
                'adjustments' => [
                    ['kind' => 'add_percent', 'value' => -10],
                ],
            ],
            [
                'code' => 'weekend_surcharge',
                'name' => 'Supplément weekend +15%',
                'service_code' => null,
                'priority' => 40,
                'is_active' => true,
                'applies_when' => ['field' => 'day_of_week', 'op' => 'in', 'value' => ['saturday', 'sunday']],
                'adjustments' => [
                    ['kind' => 'add_percent', 'value' => 15],
                ],
            ],
        ];

        foreach ($rules as $rule) {
            PricingRule::query()->updateOrCreate(
                ['code' => $rule['code']],
                $rule,
            );
        }
    }
}
