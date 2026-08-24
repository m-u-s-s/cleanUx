<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** LES MÉTIERS QUI EMMÈNENT QUELQU'UN D'UN POINT À UN AUTRE. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('questions') && ! Schema::hasColumn('questions', 'location_role')) {
            Schema::table('questions', function (Blueprint $table) {
                // Nulle pour tout type autre que `location` : le rôle ne veut rien dire sur un
                // compteur ou une photo.
                $table->string('location_role', 20)->nullable()->after('type');
            });
        }

        if (! Schema::hasTable('trades')) {
            return;
        }

        Schema::table('trades', function (Blueprint $table) {
            if (! Schema::hasColumn('trades', 'taxi_rules')) {
                // Défaut à faux, comme `allows_asap` : ouvrir une exigence est une décision
                // d'administrateur, jamais un oubli de migration.
                $table->boolean('taxi_rules')->default(false);
            }

            if (! Schema::hasColumn('trades', 'route_rules_since')) {
                $table->timestamp('route_rules_since')->nullable();
            }

            if (! Schema::hasColumn('trades', 'taxi_rules_since')) {
                $table->timestamp('taxi_rules_since')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('questions') && Schema::hasColumn('questions', 'location_role')) {
            Schema::table('questions', function (Blueprint $table) {
                $table->dropColumn('location_role');
            });
        }

        if (! Schema::hasTable('trades')) {
            return;
        }

        Schema::table('trades', function (Blueprint $table) {
            foreach (['taxi_rules', 'route_rules_since', 'taxi_rules_since'] as $colonne) {
                if (Schema::hasColumn('trades', $colonne)) {
                    $table->dropColumn($colonne);
                }
            }
        });
    }
};
