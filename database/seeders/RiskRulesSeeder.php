<?php

namespace Database\Seeders;

use App\Models\RiskRule;
use Illuminate\Database\Seeder;

class RiskRulesSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            [
                'code' => 'booking.velocity',
                'name' => 'Booking velocity',
                'description' => 'Trop de bookings créés dans une fenêtre courte (signal bot / fraude)',
                'severity' => RiskRule::SEVERITY_MEDIUM,
                'score_delta' => 30,
                'is_active' => true,
                'params' => ['window_minutes' => 60, 'max_per_window' => 5],
            ],
            [
                'code' => 'payment.decline_burst',
                'name' => 'Multiple payment declines',
                'description' => 'Plusieurs déclines de paiement dans les dernières 24h',
                'severity' => RiskRule::SEVERITY_HIGH,
                'score_delta' => 50,
                'is_active' => true,
                'params' => ['threshold' => 3],
            ],
            [
                'code' => 'ip.flagged_network',
                'name' => 'IP in flagged network',
                'description' => 'IP appartient à un réseau flaggé (proxy, datacenter, TOR)',
                'severity' => RiskRule::SEVERITY_HIGH,
                'score_delta' => 40,
                'is_active' => true,
                'params' => ['cidrs' => [], 'score' => 40],
            ],
            [
                'code' => 'account.very_new',
                'name' => 'Account very new',
                'description' => 'Compte créé il y a moins de N heures, action sensible immédiate',
                'severity' => RiskRule::SEVERITY_LOW,
                'score_delta' => 20,
                'is_active' => true,
                'params' => ['threshold_hours' => 24, 'max_score' => 30],
            ],
            [
                'code' => 'geo.country_mismatch',
                'name' => 'Country mismatch',
                'description' => 'Pays IP ≠ pays déclaré (billing/profile)',
                'severity' => RiskRule::SEVERITY_MEDIUM,
                'score_delta' => 25,
                'is_active' => true,
                'params' => ['score' => 25],
            ],
        ];

        foreach ($defaults as $rule) {
            RiskRule::query()->updateOrCreate(
                ['code' => $rule['code']],
                $rule,
            );
        }
    }
}
