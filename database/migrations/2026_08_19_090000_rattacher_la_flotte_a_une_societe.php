<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** LA FLOTTE APPARTIENT À QUELQU'UN (E27). */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['fleet_vehicles', 'fleet_equipment'] as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'organization_account_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->unsignedBigInteger('organization_account_id')->nullable()->after('id');
                // Nom court : MySQL refuse un index au-delà de 64 caractères.
                $blueprint->index('organization_account_id', substr($table, 0, 20).'_org_idx');
            });
        }
    }

    public function down(): void
    {
        foreach (['fleet_vehicles', 'fleet_equipment'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'organization_account_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                // SQLite porte l'index dans la définition de table : le laisser derrière la colonne
                // ferait échouer la suppression sur ce driver et pas sur MySQL.
                $blueprint->dropIndex(substr($table, 0, 20).'_org_idx');
                $blueprint->dropColumn('organization_account_id');
            });
        }
    }
};
