<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

class TenantsSeeder extends Seeder
{
    public function run(): void
    {
        // Tenant principal CleanUx (la marketplace native)
        Tenant::query()->updateOrCreate(
            ['code' => 'main'],
            [
                'name' => 'CleanUx Main',
                'slug' => 'main',
                'plan_code' => 'enterprise',
                'status' => Tenant::STATUS_ACTIVE,
                'primary_domain' => 'cleanux.com',
                'contact_email' => 'support@cleanux.com',
                'default_locale' => 'fr',
                'default_currency' => 'EUR',
                'default_country_code' => 'BE',
                'activated_at' => now(),
                'theming' => [],
                'features' => ['platform_admin' => true],
            ],
        );

        // Tenant demo white-label
        Tenant::query()->updateOrCreate(
            ['code' => 'acme-demo'],
            [
                'name' => 'Acme Cleaning (Demo)',
                'slug' => 'acme-demo',
                'plan_code' => 'growth',
                'status' => Tenant::STATUS_TRIAL,
                'primary_domain' => 'acme-demo.cleanux.com',
                'contact_email' => 'demo@acme.example.com',
                'default_locale' => 'fr',
                'default_currency' => 'EUR',
                'default_country_code' => 'FR',
                'trial_ends_at' => now()->addDays(30),
                'theming' => [
                    'primary_color' => '#DC2626',
                    'app_name' => 'Acme Cleaning',
                ],
            ],
        );
    }
}
