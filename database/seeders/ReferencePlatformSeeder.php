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
        ]);

        $this->command?->info('✅ Référentiel plateforme chargé (géographie, trades, services multi-métiers, modules, zones, paramètres, parcours prestataire).');
    }
}
