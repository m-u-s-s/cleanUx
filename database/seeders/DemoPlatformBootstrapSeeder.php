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
            /*
             * APRÈS `DemoPlatformSeeder`, ET PAS AVANT : il a besoin de la société prestataire, de
             * ses membres et des sites clients que celui-ci crée. Placé plus haut, il ne trouverait
             * rien et se contenterait d'un avertissement.
             */
            EspacesSocieteDemoSeeder::class,
            LimitesJournaliereSeeder::class,
            StatutRendezVousSeeder::class,
            FeedbackSeeder::class,
        ]);

        $this->command?->info('✅ Bootstrap démo chargé (utilisateurs, disponibilités, rendez-vous, feedbacks).');
    }
}
