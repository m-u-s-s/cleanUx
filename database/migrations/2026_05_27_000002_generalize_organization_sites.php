<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A2 — Generalize organization_sites from cleaning-specific to multi-trade.
 *
 * - Rename cleaning_frequency → service_frequency (only if column exists)
 * - Drop nombre_heures_nettoyage (only if column exists)
 * - Add trade_preferences JSON nullable
 *
 * All operations are guarded with Schema::hasColumn so the migration
 * is safe to run against any state of the schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_sites', function (Blueprint $table) {
            if (Schema::hasColumn('organization_sites', 'cleaning_frequency')
                && ! Schema::hasColumn('organization_sites', 'service_frequency')) {
                $table->renameColumn('cleaning_frequency', 'service_frequency');
            }

            if (Schema::hasColumn('organization_sites', 'nombre_heures_nettoyage')) {
                $table->dropColumn('nombre_heures_nettoyage');
            }

            if (! Schema::hasColumn('organization_sites', 'trade_preferences')) {
                $table->json('trade_preferences')->nullable()->after('metadata');
            }
        });
    }

    public function down(): void
    {
        Schema::table('organization_sites', function (Blueprint $table) {
            if (Schema::hasColumn('organization_sites', 'trade_preferences')) {
                $table->dropColumn('trade_preferences');
            }

            if (Schema::hasColumn('organization_sites', 'service_frequency')
                && ! Schema::hasColumn('organization_sites', 'cleaning_frequency')) {
                $table->renameColumn('service_frequency', 'cleaning_frequency');
            }

            if (! Schema::hasColumn('organization_sites', 'nombre_heures_nettoyage')) {
                $table->integer('nombre_heures_nettoyage')->nullable();
            }
        });
    }
};
