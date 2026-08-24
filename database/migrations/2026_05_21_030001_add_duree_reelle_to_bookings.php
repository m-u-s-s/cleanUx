<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Schema drift fix : `duree_reelle` (minutes) référencée dans 7+ fichiers code (AdminDashboardInsights, MesRendezVous, AnalyticsCenter, etc.) mais perdue lors de la refonte de migrations. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bookings')) {
            return;
        }
        if (Schema::hasColumn('bookings', 'duree_reelle')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table) {
            $table->integer('duree_reelle')->nullable()->after('duree_estimee')
                ->comment('Durée réelle en minutes (mission_finished - started)');
        });

        // Backfill : pour les missions terminées avec timestamps, calculer la durée
        // Utilise une syntaxe SQL générique compatible MySQL + SQLite.
        if (Schema::hasColumn('bookings', 'mission_started_at')
            && Schema::hasColumn('bookings', 'mission_finished_at')) {
            try {
                $driver = DB::connection()->getDriverName();
                if ($driver === 'mysql' || $driver === 'mariadb') {
                    DB::statement(<<<'SQL'
                        UPDATE bookings
                        SET duree_reelle = TIMESTAMPDIFF(MINUTE, mission_started_at, mission_finished_at)
                        WHERE mission_started_at IS NOT NULL
                          AND mission_finished_at IS NOT NULL
                          AND duree_reelle IS NULL
                    SQL);
                } elseif ($driver === 'sqlite') {
                    DB::statement(<<<'SQL'
                        UPDATE bookings
                        SET duree_reelle = CAST(
                            (julianday(mission_finished_at) - julianday(mission_started_at)) * 24 * 60
                            AS INTEGER
                        )
                        WHERE mission_started_at IS NOT NULL
                          AND mission_finished_at IS NOT NULL
                          AND duree_reelle IS NULL
                    SQL);
                } elseif ($driver === 'pgsql') {
                    DB::statement(<<<'SQL'
                        UPDATE bookings
                        SET duree_reelle = EXTRACT(EPOCH FROM (mission_finished_at - mission_started_at))::int / 60
                        WHERE mission_started_at IS NOT NULL
                          AND mission_finished_at IS NOT NULL
                          AND duree_reelle IS NULL
                    SQL);
                }
            } catch (Throwable $e) {
                // Backfill best-effort — ne bloque pas la migration si échec
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bookings') && Schema::hasColumn('bookings', 'duree_reelle')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dropColumn('duree_reelle');
            });
        }
    }
};
