<?php

namespace Database\Seeders;

use App\Models\ProviderBadge;
use Illuminate\Database\Seeder;

class ProviderBadgesSeeder extends Seeder
{
    public function run(): void
    {
        $badges = [
            // Missions count
            ['code' => 'rookie_10',    'name' => 'Premiers pas',     'description' => '10 missions terminées',   'icon' => '🌱', 'tier' => 'bronze',   'criterion_type' => 'missions_count', 'threshold' => 10],
            ['code' => 'pro_50',       'name' => 'Pro',              'description' => '50 missions terminées',   'icon' => '⭐', 'tier' => 'silver',   'criterion_type' => 'missions_count', 'threshold' => 50],
            ['code' => 'expert_200',   'name' => 'Expert',           'description' => '200 missions terminées',  'icon' => '🏆', 'tier' => 'gold',     'criterion_type' => 'missions_count', 'threshold' => 200],
            ['code' => 'legend_500',   'name' => 'Légende',          'description' => '500 missions terminées',  'icon' => '👑', 'tier' => 'platinum', 'criterion_type' => 'missions_count', 'threshold' => 500],

            // Rating avg × 100 (4.8★ = 480)
            ['code' => 'good_rated',   'name' => 'Bien noté',        'description' => 'Note moyenne ≥ 4.0',      'icon' => '👍', 'tier' => 'bronze',   'criterion_type' => 'rating_avg', 'threshold' => 400],
            ['code' => 'top_rated',    'name' => 'Excellent',        'description' => 'Note moyenne ≥ 4.5',      'icon' => '🌟', 'tier' => 'silver',   'criterion_type' => 'rating_avg', 'threshold' => 450],
            ['code' => 'elite_rated',  'name' => 'Élite',            'description' => 'Note moyenne ≥ 4.8',      'icon' => '💎', 'tier' => 'gold',     'criterion_type' => 'rating_avg', 'threshold' => 480],

            // Tips
            ['code' => 'tipped_10',    'name' => 'Apprécié',         'description' => '10 pourboires reçus',     'icon' => '💰', 'tier' => 'bronze',   'criterion_type' => 'tips_received', 'threshold' => 10],
            ['code' => 'tipped_50',    'name' => 'Star du tip',      'description' => '50 pourboires reçus',     'icon' => '💸', 'tier' => 'gold',     'criterion_type' => 'tips_received', 'threshold' => 50],

            // Tenure
            ['code' => 'veteran_180',  'name' => 'Vétéran 6 mois',   'description' => '180 jours sur la plateforme', 'icon' => '🎖️', 'tier' => 'silver', 'criterion_type' => 'tenure_days',  'threshold' => 180],
            ['code' => 'veteran_365',  'name' => 'Vétéran 1 an',     'description' => '365 jours sur la plateforme', 'icon' => '🏅', 'tier' => 'gold',   'criterion_type' => 'tenure_days',  'threshold' => 365],

            // Streak 5★
            ['code' => 'streak_10',    'name' => 'Streak parfait',   'description' => '10 missions consécutives 5★', 'icon' => '🔥', 'tier' => 'gold',     'criterion_type' => 'streak_5stars', 'threshold' => 10],
            ['code' => 'streak_25',    'name' => 'Streak légendaire','description' => '25 missions consécutives 5★', 'icon' => '⚡', 'tier' => 'platinum', 'criterion_type' => 'streak_5stars', 'threshold' => 25],
        ];

        foreach ($badges as $b) {
            ProviderBadge::query()->updateOrCreate(
                ['code' => $b['code']],
                array_merge($b, ['is_active' => true]),
            );
        }
    }
}
