<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LA FLOTTE APPARTIENT À QUELQU'UN (E27).
 *
 * Fleet v2 est complet — véhicules, équipements, certifications, maintenance, blocage d'une mission
 * si une certification a expiré — et entièrement PLATEFORME : `fleet_vehicles` n'a pas de colonne
 * d'organisation, si bien qu'un administrateur voit tous les camions de toutes les sociétés et
 * qu'aucune société ne voit les siens. Le module existait, son propriétaire n'était écrit nulle part.
 *
 * LA COLONNE EST NULLABLE, ET C'EST TOUT L'ENJEU DE LA MIGRATION. `null` signifie « à la
 * plateforme » — l'état actuel de chaque ligne. La rendre obligatoire aurait forcé à inventer un
 * propriétaire pour l'existant, c'est-à-dire à attribuer des véhicules au hasard puis à découvrir la
 * conséquence en production. Ici, rien ne change pour ce qui existe : la société ne voit que ce
 * qu'elle a déclaré.
 *
 * PAS DE CLÉ ÉTRANGÈRE EN CASCADE : la suppression d'une organisation ne doit pas emporter des
 * véhicules dont la maintenance et les certifications se relisent des années plus tard.
 */
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
