<?php

namespace Database\Seeders;

use App\Models\SubscriptionsV2\SubscriptionPlanV2;
use Illuminate\Database\Seeder;

class SubscriptionPlansV2Seeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'code' => 'cleaning_weekly_basic',
                'name' => 'Nettoyage hebdomadaire — Basic',
                'description' => '4 passages par mois, 2h chacun, produits inclus',
                'trade_codes' => ['cleaning'],
                'billing_period' => 'monthly',
                'price_cents' => 15900,   // 159 € / mois
                'currency' => 'EUR',
                'included_units_per_cycle' => 4,
                'included_unit_type' => 'visits',
                'overage_unit_price_cents' => 4900,
                'trial_days' => 0,
                'features' => [
                    'priority_booking' => true,
                    'fixed_slot' => true,
                    'product_included' => true,
                ],
                'version' => 'v1',
            ],
            [
                'code' => 'cleaning_biweekly_premium',
                'name' => 'Nettoyage bimensuel — Premium',
                'description' => '2 passages par mois, 3h chacun, produits éco-responsables',
                'trade_codes' => ['cleaning'],
                'billing_period' => 'monthly',
                'price_cents' => 11900,
                'currency' => 'EUR',
                'included_units_per_cycle' => 2,
                'included_unit_type' => 'visits',
                'overage_unit_price_cents' => 5900,
                'trial_days' => 7,
                'features' => [
                    'priority_booking' => true,
                    'eco_friendly_products' => true,
                    'flexible_rescheduling' => true,
                ],
                'version' => 'v1',
            ],
            [
                'code' => 'maintenance_annual_basic',
                'name' => 'Contrat maintenance annuelle — Basic',
                'description' => '12 visites de maintenance préventive sur 12 mois',
                'trade_codes' => ['maintenance', 'roofing', 'plumbing'],
                'billing_period' => 'yearly',
                'price_cents' => 99900,
                'currency' => 'EUR',
                'included_units_per_cycle' => 12,
                'included_unit_type' => 'visits',
                'overage_unit_price_cents' => 8900,
                'trial_days' => 0,
                'features' => [
                    'priority_dispatch' => true,
                    'discount_on_repairs' => 15,
                    'emergency_24_7' => false,
                ],
                'version' => 'v1',
            ],
        ];

        foreach ($plans as $p) {
            SubscriptionPlanV2::query()->updateOrCreate(
                ['code' => $p['code']],
                array_merge($p, ['is_active' => true]),
            );
        }
    }
}
