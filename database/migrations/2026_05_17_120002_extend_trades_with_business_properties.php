<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier A — Étend la table `trades` avec les propriétés métier exploitées
 * par le pricing et le workflow (tarif horaire, multiplicateurs urgence/nuit/
 * weekend, validité devis, SLA, devis obligatoire).
 *
 * Idempotent : chaque colonne est ajoutée uniquement si elle n'existe pas
 * déjà (compatible avec un rejeu sur des bases déjà partiellement migrées).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('trades')) {
            return;
        }

        Schema::table('trades', function (Blueprint $table) {
            if (! Schema::hasColumn('trades', 'default_hourly_rate')) {
                $table->decimal('default_hourly_rate', 10, 2)->nullable()->after('description');
            }
            if (! Schema::hasColumn('trades', 'emergency_multiplier')) {
                $table->decimal('emergency_multiplier', 5, 2)->default(1.00)->after('default_hourly_rate');
            }
            if (! Schema::hasColumn('trades', 'night_multiplier')) {
                $table->decimal('night_multiplier', 5, 2)->default(1.00)->after('emergency_multiplier');
            }
            if (! Schema::hasColumn('trades', 'weekend_multiplier')) {
                $table->decimal('weekend_multiplier', 5, 2)->default(1.00)->after('night_multiplier');
            }
            if (! Schema::hasColumn('trades', 'quote_validity_days')) {
                $table->unsignedSmallInteger('quote_validity_days')->nullable()->after('weekend_multiplier');
            }
            if (! Schema::hasColumn('trades', 'requires_quote_by_default')) {
                $table->boolean('requires_quote_by_default')->default(false)->after('quote_validity_days');
            }
            if (! Schema::hasColumn('trades', 'sla_response_minutes')) {
                $table->unsignedSmallInteger('sla_response_minutes')->nullable()->after('requires_quote_by_default');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('trades')) {
            return;
        }

        Schema::table('trades', function (Blueprint $table) {
            foreach ([
                'default_hourly_rate',
                'emergency_multiplier',
                'night_multiplier',
                'weekend_multiplier',
                'quote_validity_days',
                'requires_quote_by_default',
                'sla_response_minutes',
            ] as $col) {
                if (Schema::hasColumn('trades', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
