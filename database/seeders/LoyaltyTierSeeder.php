<?php

namespace Database\Seeders;

use App\Models\LoyaltyTier;
use Illuminate\Database\Seeder;

class LoyaltyTierSeeder extends Seeder
{
    public function run(): void
    {
        foreach ((array) config('loyalty.tiers', []) as $tier) {
            LoyaltyTier::updateOrCreate(
                ['slug' => $tier['slug']],
                array_merge($tier, ['is_active' => true])
            );
        }
    }
}
