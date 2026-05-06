<?php

namespace Database\Seeders;

use App\Models\ServiceCatalog;
use App\Models\Trade;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Phase 1 — Backfill trade_id pour les ServiceCatalog existants.
 *
 * Tous les services pré-existants (avant Phase 1) sont rattachés au Trade "Nettoyage"
 * puisque la plateforme historique CleanUx ne couvrait que ce métier.
 *
 * À exécuter APRÈS TradeSeeder.
 *
 * Idempotent : ne touche que les services dont trade_id est NULL.
 */
class ServiceCatalogTradeBackfillSeeder extends Seeder
{
    public function run(): void
    {
        $cleaning = Trade::where('slug', 'nettoyage')->first();

        if (! $cleaning) {
            $this->command?->error("Trade 'nettoyage' introuvable. Lance d'abord TradeSeeder.");
            return;
        }

        $count = DB::table('service_catalogs')
            ->whereNull('trade_id')
            ->update(['trade_id' => $cleaning->id]);

        $this->command?->info("ServiceCatalogTradeBackfillSeeder: {$count} service(s) rattaché(s) au Trade Nettoyage.");

        // Garde-fou : aucun service ne devrait rester sans trade
        $orphans = ServiceCatalog::whereNull('trade_id')->count();
        if ($orphans > 0) {
            $this->command?->warn("Attention : {$orphans} service(s) sans trade_id après backfill.");
        }
    }
}
