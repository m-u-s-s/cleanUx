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
            /*
             * L'EXPLOITATION DE LA SOCIÉTÉ — planning, heures, congés, stock, devis, recrutement,
             * flotte. Après `EspacesSocieteDemoSeeder` parce qu'il a besoin de ses membres, et
             * après `DemoPlatformSeeder` pour le client à qui adresser un devis.
             *
             * SANS LUI, « pas de planning » et « la table n'est jamais remplie » se ressemblent —
             * et l'absence de planning est justement un état MÉTIER significatif, que la
             * disponibilité traite comme « la contrainte ne s'applique pas ».
             */
            ExploitationSocieteDemoSeeder::class,
            /*
             * LE CARNET DE LIEUX (E2) ET LE BÉNÉFICIAIRE (E1). Après les réservations de démo :
             * le seeder en rattache une à un lieu, seul moyen de voir la fiche d'accès sur place
             * révéler quelque chose à l'arrivée.
             */
            CarnetClientDemoSeeder::class,
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
