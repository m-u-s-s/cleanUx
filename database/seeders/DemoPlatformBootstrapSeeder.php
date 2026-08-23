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
            // APRÈS `DemoPlatformSeeder`, ET PAS AVANT : il a besoin de la société prestataire, de ses membres et des sites clients que celui-ci crée.
            EspacesSocieteDemoSeeder::class,
            // L'EXPLOITATION DE LA SOCIÉTÉ — planning, heures, congés, stock, devis, recrutement, flotte.
            ExploitationSocieteDemoSeeder::class,
            // LE CARNET DE LIEUX (E2) ET LE BÉNÉFICIAIRE (E1).
            CarnetClientDemoSeeder::class,
            LimitesJournaliereSeeder::class,
            StatutRendezVousSeeder::class,
            // LE PILOTAGE D'UNE ENTREPRISE CLIENTE (E7, E8) — APRÈS `StatutRendezVousSeeder`, et c'est la seule position qui marche : celui-ci réattribue un statut ALÉATOIRE à chaque réservation, et écrasait donc la demande en attente d'approbation qu'on venait de poser.
            PilotageEntrepriseDemoSeeder::class,
            // LA PROGRESSION D'UN PRESTATAIRE (E13, E16, E33).
            ProgressionPrestataireDemoSeeder::class,
            // LES SUPPLÉMENTS PROPOSÉS SUR PLACE (F3, F12).
            ExtrasDeMissionDemoSeeder::class,
            FeedbackSeeder::class,
            // LE SCÉNARIO DE DÉMONSTRATION DU MOTEUR DE RÉPARTITION.
            DispatchDemoSeeder::class,
        ]);

        $this->command?->info('✅ Bootstrap démo chargé (utilisateurs, disponibilités, rendez-vous, feedbacks).');
    }
}
