<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A1 — Make trade_id NOT NULL on service_catalogs.
 *
 * Step 1: backfill all rows where trade_id IS NULL to the "Nettoyage" trade
 *         (resolved by slug 'nettoyage' or 'cleaning').
 * Step 2: change the column to NOT NULL.
 *
 * Safe to re-run: uses Schema::hasColumn checks.
 * Runs after trade_zone_pricing (000001) and extends_trades (000000) are done.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('service_catalogs', 'trade_id')) {
            return;
        }

        // Backfill: resolve the cleaning trade by slug.
        $cleaningTradeId = DB::table('trades')
            ->where('slug', 'nettoyage')
            ->orWhere('slug', 'cleaning')
            ->value('id');

        if ($cleaningTradeId) {
            DB::table('service_catalogs')
                ->whereNull('trade_id')
                ->update(['trade_id' => $cleaningTradeId]);
        }

        // Only enforce NOT NULL when no NULL rows remain.
        // This allows the migration to run before seeding without failing.
        $remainingNulls = DB::table('service_catalogs')->whereNull('trade_id')->count();

        if ($remainingNulls === 0 && config('database.default') !== 'sqlite') {
            // MySQL refuse de passer une colonne en NOT NULL tant qu'elle porte
            // une FK ON DELETE SET NULL : on dépose la FK, on change, on recrée.
            Schema::table('service_catalogs', function (Blueprint $table) {
                try {
                    $table->dropForeign(['trade_id']);
                } catch (Throwable $e) {
                    // FK déjà absente : on continue.
                }
            });

            Schema::table('service_catalogs', function (Blueprint $table) {
                $table->foreignId('trade_id')->nullable(false)->change();
            });

            Schema::table('service_catalogs', function (Blueprint $table) {
                $table->foreign('trade_id')->references('id')->on('trades')
                    ->cascadeOnUpdate()->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('service_catalogs', 'trade_id')) {
            return;
        }

        Schema::table('service_catalogs', function (Blueprint $table) {
            $table->foreignId('trade_id')->nullable()->change();
        });
    }
};
