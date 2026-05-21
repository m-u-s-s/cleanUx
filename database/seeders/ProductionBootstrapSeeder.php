<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Seeder à exécuter sur une installation production fraîche.
 *
 *   php artisan db:seed --class=ProductionBootstrapSeeder
 *
 * Idempotent : tous les seeders appelés utilisent updateOrCreate ou firstOrCreate.
 *
 * Charge UNIQUEMENT les données de référence et les catalogues v2 minimums :
 *  - Réfs plateforme (rôles, statuts, etc.)
 *  - Catalogues services + trades + geo BE
 *  - Tiers/badges loyalty + récompenses base
 *  - API token scopes + audit defaults + currencies
 *  - Insurance plans + KYB sanctions + risk rules + quality checklists
 *  - Onboarding journeys + pricing v2 + contracts templates
 *  - Subscription plans v2 + webhook endpoints
 *
 * N'inclut PAS : demo data, fake users, fake bookings. Pour démo, utiliser
 * `DemoPlatformBootstrapSeeder`.
 */
class ProductionBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('🚀 Bootstrap production CleanUx — démarrage...');

        // ─── 1. Données de référence plateforme (rôles/statuts/etc.) ───
        $this->safeCall(ReferencePlatformSeeder::class);
        $this->safeCall(CoreSettingsSeeder::class);

        // ─── 2. Catalogues services + trades ───
        $this->safeCall(TradeSeeder::class);
        $this->safeCall(ServiceCatalogSeeder::class);
        $this->safeCall(ServiceCatalogTradeBackfillSeeder::class);
        $this->safeCall(StatutRendezVousSeeder::class);

        // ─── 3. Géographie BE/EU ───
        $this->safeCall(BelgiumGeographySeeder::class);
        $this->safeCall(ZoneManagementSeeder::class);

        // ─── 4. Modules v2 catalogues ───
        $this->safeIf('loyalty_tiers', LoyaltyTierSeeder::class);
        $this->safeIf('provider_badges', ProviderBadgesSeeder::class);
        $this->safeIf('api_token_scopes', ApiTokenScopesSeeder::class);
        $this->safeIf('audit_redaction_rules', AuditDefaultsSeeder::class);
        $this->safeIf('currencies', CurrenciesSeeder::class);
        $this->safeIf('insurance_plans', InsurancePlansSeeder::class);
        $this->safeIf('risk_rules', RiskRulesSeeder::class);
        $this->safeIf('quality_checklists', QualityChecklistsSeeder::class);
        $this->safeIf('onboarding_journeys', OnboardingJourneysSeeder::class);
        $this->safeIf('cancellation_policies', CancellationPoliciesSeeder::class);
        $this->safeIf('pricing_rules', PricingV2Seeder::class);
        $this->safeIf('contract_templates', ContractTemplatesSeeder::class);
        $this->safeIf('subscription_plans_v2', SubscriptionPlansV2Seeder::class);
        $this->safeIf('webhook_endpoints', WebhookEndpointsSeeder::class);
        $this->safeIf('tenants', TenantsSeeder::class);

        // ─── 5. Templates récurrents ───
        $this->safeCall(RecurringTemplateSystemSeeder::class);
        $this->safeCall(PlatformModuleSeeder::class);

        $this->command?->info('✅ Bootstrap production CleanUx terminé.');
        $this->command?->line('   Lance ensuite : php artisan ops:check-providers --strict');
    }

    /**
     * Exécute un seeder en soft-fail (log + continue si erreur).
     */
    protected function safeCall(string $class): void
    {
        try {
            $this->call($class);
        } catch (\Throwable $e) {
            $this->command?->warn("   ⚠ {$class} a échoué : " . $e->getMessage());
        }
    }

    /**
     * Exécute un seeder uniquement si la table associée existe (module activé).
     */
    protected function safeIf(string $table, string $class): void
    {
        if (! Schema::hasTable($table)) {
            $this->command?->line("   ↷ Skip {$class} (table {$table} absente)");
            return;
        }
        $this->safeCall($class);
    }
}
