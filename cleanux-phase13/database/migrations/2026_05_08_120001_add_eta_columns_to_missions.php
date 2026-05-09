<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 13 — Colonnes ETA sur missions.
 *
 * Stocke le résultat du calcul d'ETA (last_eta_meters, last_eta_seconds,
 * last_eta_calculated_at) pour éviter de recalculer à chaque requête client.
 *
 * Cache stratégie :
 *   - Recalculé à chaque MissionTrackingPoint (Observer)
 *   - Cache HTTP 60s côté API client/eta (cache::remember)
 *
 * Approche défensive : Schema::hasColumn pour idempotence.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('missions')) {
            return;
        }

        Schema::table('missions', function (Blueprint $table) {
            if (! Schema::hasColumn('missions', 'last_eta_meters')) {
                $table->unsignedInteger('last_eta_meters')->nullable()->after('end_lng');
            }
            if (! Schema::hasColumn('missions', 'last_eta_seconds')) {
                $table->unsignedInteger('last_eta_seconds')->nullable()->after('last_eta_meters');
            }
            if (! Schema::hasColumn('missions', 'last_eta_source')) {
                $table->string('last_eta_source', 30)->nullable()->after('last_eta_seconds');
            }
            if (! Schema::hasColumn('missions', 'last_eta_calculated_at')) {
                $table->timestamp('last_eta_calculated_at')->nullable()->after('last_eta_source');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('missions')) {
            return;
        }

        Schema::table('missions', function (Blueprint $table) {
            $columns = [
                'last_eta_meters',
                'last_eta_seconds',
                'last_eta_source',
                'last_eta_calculated_at',
            ];

            foreach ($columns as $col) {
                if (Schema::hasColumn('missions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
