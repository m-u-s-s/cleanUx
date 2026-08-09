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
            /*
             * LE SCÉNARIO DE DÉMONSTRATION DU MOTEUR DE RÉPARTITION.
             *
             * Le dispatch immédiat exige QUATRE choses simultanément — un métier ouvert en immédiat
             * dans la zone, des prestataires vérifiés, déclarés sur ce métier, et en ligne avec une
             * position fraîche. Il suffit qu'une seule manque pour que la recherche s'épuise en
             * silence, et rien à l'écran ne dit laquelle.
             *
             * En DERNIER : il s'appuie sur les zones, le catalogue et la grille métier × zone.
             */
            DispatchDemoSeeder::class,
        ]);

        $this->command?->info('✅ Bootstrap démo chargé (utilisateurs, disponibilités, rendez-vous, feedbacks).');
    }
}
