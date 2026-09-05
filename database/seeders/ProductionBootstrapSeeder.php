<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/** Seeder à exécuter sur une installation production fraîche. */
class ProductionBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('🚀 Bootstrap production Brio — démarrage...');

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

        // ─── 4. Reglages de base des modules ───
        // Ils ont rejoint `ReferencePlatformSeeder`, appele en 1 : un niveau de fidelite ou une
        // devise n'est pas une donnee de production, c'est un reglage sans lequel l'ecran est vide.
        $this->safeIf('tenants', TenantsSeeder::class);

        // ─── 5. Templates récurrents ───
        $this->safeCall(RecurringTemplateSystemSeeder::class);
        $this->safeCall(PlatformModuleSeeder::class);

        $this->command?->info('✅ Bootstrap production Brio terminé.');
        $this->command?->line('   Lance ensuite : php artisan ops:check-providers --strict');
    }

    /** Exécute un seeder en soft-fail (log + continue si erreur). */
    protected function safeCall(string $class): void
    {
        try {
            $this->call($class);
        } catch (\Throwable $e) {
            $this->command?->warn("   ⚠ {$class} a échoué : ".$e->getMessage());
        }
    }

    /** Exécute un seeder uniquement si la table associée existe (module activé). */
    protected function safeIf(string $table, string $class): void
    {
        if (! Schema::hasTable($table)) {
            $this->command?->line("   ↷ Skip {$class} (table {$table} absente)");

            return;
        }
        $this->safeCall($class);
    }
}
