<?php

namespace Database\Seeders;

use App\Models\PromoCampaign;
use App\Models\PromoCode;
use Illuminate\Database\Seeder;

class PromotionDemoSeeder extends Seeder
{
    public function run(): void
    {
        $welcome = PromoCampaign::firstOrCreate(
            ['slug' => 'welcome-2026'],
            [
                'name' => 'Bienvenue 2026',
                'description' => 'Campagne d\'acquisition pour nouveaux clients.',
                'status' => PromoCampaign::STATUS_ACTIVE,
                'starts_at' => now()->subDays(7),
                'ends_at' => now()->addMonths(6),
                'budget_cap' => 5000,
                'target_audience' => 'Nouveaux clients particuliers',
            ]
        );

        PromoCode::firstOrCreate(
            ['code' => 'WELCOME10'],
            [
                'promo_campaign_id' => $welcome->id,
                'name' => 'Bienvenue -10%',
                'description' => '10% sur la première réservation.',
                'discount_type' => PromoCode::TYPE_PERCENT,
                'discount_value' => 10,
                'max_uses_per_user' => 1,
                'first_booking_only' => true,
                'audience_scope' => PromoCode::SCOPE_NEW,
                'status' => PromoCode::STATUS_ACTIVE,
                'source' => PromoCode::SOURCE_CAMPAIGN,
                'valid_from' => now()->subDays(7),
                'valid_until' => now()->addMonths(6),
            ]
        );

        PromoCode::firstOrCreate(
            ['code' => 'SUMMER25'],
            [
                'promo_campaign_id' => $welcome->id,
                'name' => 'Été 2026 - 25%',
                'description' => 'Promo estivale, plafond 30€.',
                'discount_type' => PromoCode::TYPE_PERCENT,
                'discount_value' => 25,
                'max_discount_amount' => 30,
                'max_uses_per_user' => 2,
                'min_booking_amount' => 50,
                'audience_scope' => PromoCode::SCOPE_ALL,
                'status' => PromoCode::STATUS_ACTIVE,
                'source' => PromoCode::SOURCE_CAMPAIGN,
                'valid_from' => now(),
                'valid_until' => now()->addMonths(3),
            ]
        );

        PromoCode::firstOrCreate(
            ['code' => 'FIXED15'],
            [
                'name' => 'Remise fixe 15€',
                'description' => '15€ de remise sur toute réservation > 100€.',
                'discount_type' => PromoCode::TYPE_FIXED,
                'discount_value' => 15,
                'min_booking_amount' => 100,
                'max_uses_per_user' => 1,
                'max_total_uses' => 500,
                'audience_scope' => PromoCode::SCOPE_ALL,
                'status' => PromoCode::STATUS_ACTIVE,
                'source' => PromoCode::SOURCE_MANUAL,
            ]
        );
    }
}
