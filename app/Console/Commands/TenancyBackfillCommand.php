<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TenancyBackfillCommand extends Command
{
    protected $signature = 'tenancy:backfill
        {--tenant=main : Code du tenant cible}
        {--tables=users : Liste de tables séparées par virgule}
        {--dry-run : Lister les changements sans les appliquer}
        {--chunk=500 : Taille batch update}';

    protected $description = 'Backfill tenant_id sur les tables existantes avec le tenant par défaut (multi-tenancy v2 Phase 2)';

    public function handle(): int
    {
        if (! Schema::hasTable('tenants')) {
            $this->error('Table tenants absente. Run la migration tenancy_v2 d\'abord.');
            return self::FAILURE;
        }

        $code = (string) $this->option('tenant');
        $tenant = Tenant::query()->where('code', $code)->first();
        if (! $tenant) {
            $this->error("Tenant '{$code}' introuvable. Run TenantsSeeder ou créer via API.");
            return self::FAILURE;
        }

        $tables = array_filter(array_map('trim', explode(',', (string) $this->option('tables'))));
        $dryRun = (bool) $this->option('dry-run');
        $chunk = (int) $this->option('chunk');

        $totalUpdated = 0;
        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                $this->warn("  - Table '{$table}' absente. Skip.");
                continue;
            }
            if (! Schema::hasColumn($table, 'tenant_id')) {
                $this->warn("  - Table '{$table}' n'a pas de colonne tenant_id. Skip (run la migration optionnelle d'abord).");
                continue;
            }
            $missing = DB::table($table)->whereNull('tenant_id')->count();
            $this->info(sprintf('Table %s : %d rows sans tenant_id.', $table, $missing));
            if ($missing === 0) {
                continue;
            }
            if ($dryRun) {
                continue;
            }
            $updated = 0;
            DB::table($table)->whereNull('tenant_id')
                ->orderBy('id')
                ->chunkById($chunk, function ($rows) use ($table, $tenant, &$updated) {
                    $ids = $rows->pluck('id')->all();
                    $count = DB::table($table)->whereIn('id', $ids)->update(['tenant_id' => $tenant->id]);
                    $updated += $count;
                });
            $totalUpdated += $updated;
            $this->info(sprintf('  ✓ %d rows mises à jour sur %s.', $updated, $table));
        }

        $this->info(sprintf('Backfill terminé : %d rows totales mises à jour vers tenant=%s.', $totalUpdated, $code));
        return self::SUCCESS;
    }
}
