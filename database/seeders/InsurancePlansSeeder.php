<?php

namespace Database\Seeders;

use App\Models\InsurancePlan;
use Illuminate\Database\Seeder;

class InsurancePlansSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            [
                'code' => 'basic',
                'name' => 'Basic',
                'description' => 'Couverture standard dommages directs jusqu\'à 5 000 €',
                'trade_codes' => null,  // tous trades
                'coverage_amount_cents' => 500000,
                'premium_base_cents' => 200,
                'premium_percent' => 1.0000,
                'min_premium_cents' => 199,
                'max_premium_cents' => 1500,
                'currency' => 'EUR',
                'is_active' => true,
            ],
            [
                'code' => 'standard',
                'name' => 'Standard',
                'description' => 'Dommages + vol jusqu\'à 15 000 €, franchise réduite',
                'trade_codes' => null,
                'coverage_amount_cents' => 1500000,
                'premium_base_cents' => 500,
                'premium_percent' => 2.0000,
                'min_premium_cents' => 499,
                'max_premium_cents' => 4000,
                'currency' => 'EUR',
                'is_active' => true,
            ],
            [
                'code' => 'premium',
                'name' => 'Premium',
                'description' => 'Couverture complète jusqu\'à 50 000 € + RC pro',
                'trade_codes' => null,
                'coverage_amount_cents' => 5000000,
                'premium_base_cents' => 1500,
                'premium_percent' => 3.5000,
                'min_premium_cents' => 1499,
                'max_premium_cents' => 12000,
                'currency' => 'EUR',
                'is_active' => true,
            ],
        ];

        foreach ($defaults as $plan) {
            InsurancePlan::query()->updateOrCreate(['code' => $plan['code']], $plan);
        }
    }
}
