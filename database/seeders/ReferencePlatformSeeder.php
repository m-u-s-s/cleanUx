<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

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
            // Cinq alertes du chemin de l'argent, données de référence — pas de démo.
            ReglesDAlerteMetierSeeder::class,
            // Configuration, pas données de démo : le parcours de vérification et les questions
            // métier doivent exister sur TOUS les profils, production comprise. Sans le parcours,
            // aucune vérification ne s'applique à un compte créé. Placés après TradeSeeder, dont
            // les questions dérivent des drapeaux de chaque métier.
            ProviderOnboardingJourneySeeder::class,
            ProviderTradeQuestionsSeeder::class,
            // LE PARCOURS DE COMMANDE LUI-MÊME — secteurs, métiers, questions, options.
            OrderEngineCatalogSeeder::class,
            // La grille (métier, zone) : activation ET prix.
            TradeZonePricingSeeder::class,
            // LE MÉTIER DE COURSE — point A vers point B.
            CourseCatalogSeeder::class,
            // LE RATTACHEMENT MÉTIER → SECTEUR, EN DERNIER ET POUR CETTE RAISON-LÀ.
            TradeSectorLinkSeeder::class,
            // LE CATALOGUE DANS LES CINQ AUTRES LANGUES ACTIVES.
            CatalogueTraductionsSeeder::class,
        ]);

        $this->reglagesDeBase();

        $this->command?->info('✅ Référentiel plateforme chargé (géographie, trades, services multi-métiers, modules, zones, paramètres, parcours prestataire, catalogue de commande, grille métier × zone, réglages de base des modules).');
    }

    /**
     * LES REGLAGES DE BASE DES MODULES — ils vivaient dans le seul profil `production`.
     *
     * Un niveau de fidelite, une devise, une regle de risque, une police d'annulation ou un
     * modele de contrat ne sont pas des DONNEES de production : ce sont les reglages sans
     * lesquels le module ne peut pas fonctionner. Les reserver a la production laissait huit
     * ecrans vides sur toute machine de developpement, de test ou de demonstration — et donnait
     * a croire que ces modules etaient casses.
     */
    protected function reglagesDeBase(): void
    {
        foreach ([
            'loyalty_tiers' => LoyaltyTierSeeder::class,
            'provider_badges' => ProviderBadgesSeeder::class,
            'api_token_scopes' => ApiTokenScopesSeeder::class,
            'audit_redaction_rules' => AuditDefaultsSeeder::class,
            'currencies' => CurrenciesSeeder::class,
            'insurance_plans' => InsurancePlansSeeder::class,
            'risk_rules' => RiskRulesSeeder::class,
            'quality_checklists' => QualityChecklistsSeeder::class,
            'onboarding_journeys' => OnboardingJourneysSeeder::class,
            'cancellation_policies' => CancellationPoliciesSeeder::class,
            'cancellation_questions' => CancellationQuestionnaireSeeder::class,
            'pricing_rules' => PricingV2Seeder::class,
            'contract_templates' => ContractTemplatesSeeder::class,
            'subscription_plans_v2' => SubscriptionPlansV2Seeder::class,
            'webhook_endpoints' => WebhookEndpointsSeeder::class,
        ] as $table => $seeder) {
            // Un module desinstalle n'a pas sa table : on passe, on n'echoue pas.
            if (! Schema::hasTable($table)) {
                $this->command?->line("   ↷ {$seeder} ignore (table {$table} absente)");

                continue;
            }

            try {
                $this->call($seeder);
            } catch (\Throwable $e) {
                $this->command?->warn("   ⚠ {$seeder} a echoue : ".$e->getMessage());
            }
        }
    }
}
