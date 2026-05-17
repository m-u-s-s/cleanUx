<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DemoPlatformBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            MultiTradeDemoServicesSeeder::class,
            DemoPlatformSeeder::class,
            LimitesJournaliereSeeder::class,
            StatutRendezVousSeeder::class,
            FeedbackSeeder::class,
        ]);

        $this->command?->info('✅ Bootstrap démo chargé (utilisateurs, disponibilités, rendez-vous, feedbacks).');
    }
}
