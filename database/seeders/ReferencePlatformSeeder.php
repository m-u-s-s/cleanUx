<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ReferencePlatformSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            BelgiumGeographySeeder::class,
            // Phase 1 — Trades AVANT ServiceCatalogSeeder pour pouvoir
            // rattacher les services à un trade dès leur création.
            TradeSeeder::class,
            ServiceCatalogSeeder::class,
            // Phase 1 — Backfill trade_id pour les services qui auraient été
            // créés sans (idempotent : ne touche que les NULL).
            ServiceCatalogTradeBackfillSeeder::class,
            PlatformModuleSeeder::class,
            ZoneManagementSeeder::class,
            CoreSettingsSeeder::class,
            // Configuration, pas données de démo : le parcours de vérification et les questions
            // métier doivent exister sur TOUS les profils, production comprise. Sans le parcours,
            // aucune vérification ne s'applique à un compte créé. Placés après TradeSeeder, dont
            // les questions dérivent des drapeaux de chaque métier.
            ProviderOnboardingJourneySeeder::class,
            ProviderTradeQuestionsSeeder::class,
            /*
             * LE PARCOURS DE COMMANDE LUI-MÊME — secteurs, métiers, questions, options.
             *
             * Il n'était appelé PAR AUCUNE chaîne de seeding : seuls les tests le connaissaient.
             * Une base fraîchement semée servait donc `/commander` sans un seul questionnaire, et
             * la page paraissait cassée alors que le moteur fonctionnait — il n'avait rien à
             * afficher. C'est de la configuration, pas de la démonstration : elle vaut pour tous
             * les profils, production comprise.
             */
            OrderEngineCatalogSeeder::class,
            /*
             * La grille (métier, zone) : activation ET prix. Sans elle, aucun métier n'est vendu
             * nulle part et la confirmation refuse toute commande — pour une raison correcte, mais
             * parfaitement incompréhensible sur une base neuve.
             *
             * APRÈS le catalogue et les zones : elle est le produit cartésien des deux.
             */
            TradeZonePricingSeeder::class,
            /*
             * LE MÉTIER DE COURSE — point A vers point B.
             *
             * APRÈS la grille : il ouvre lui-même ses lignes (métier, zone) avec un tarif au
             * kilomètre, ce que la grille générique ne sait pas faire. Ajout pur : aucun métier
             * existant n'est touché, et sans lui tout le parcours de trajet n'existerait qu'en
             * tests — le piège maison d'un module complet dont personne ne crée les lignes.
             */
            CourseCatalogSeeder::class,
            /*
             * LE RATTACHEMENT MÉTIER → SECTEUR, EN DERNIER ET POUR CETTE RAISON-LÀ.
             *
             * `TradeSeeder` passe en troisième position, avant qu'aucun secteur n'existe : il ne
             * peut rien rattacher. Les catalogues ci-dessus posent chacun les leurs. Ce semeur
             * arrive quand ils sont tous là, et comble ce qui reste — six métiers entièrement
             * tarifés qu'aucun écran de commande n'affichait, faute de secteur.
             */
            TradeSectorLinkSeeder::class,
        ]);

        $this->command?->info('✅ Référentiel plateforme chargé (géographie, trades, services multi-métiers, modules, zones, paramètres, parcours prestataire, catalogue de commande, grille métier × zone).');
    }
}
