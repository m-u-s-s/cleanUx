<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trace de la position au moment où la mission a été clôturée.
 *
 * `end_lat`/`end_lng` existaient déjà, mais n'étaient que décoratifs : rien ne les confrontait au
 * lieu de l'intervention. Une mission pouvait donc être close — et le paiement pré-autorisé
 * encaissé — depuis n'importe où, avec un code de fin photographié ou dicté au téléphone.
 *
 * Six chemins clôturent une mission, et tous n'ont pas de position à offrir : une clôture depuis
 * le tableau de bord web se fait derrière un bureau. `end_geo_verdict` distingue donc ce qui a été
 * VÉRIFIÉ de ce qui a seulement été autorisé faute de position — une distance nulle, elle, ne
 * saurait pas dire lequel des deux s'est produit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            if (! Schema::hasColumn('missions', 'end_accuracy_m')) {
                $table->decimal('end_accuracy_m', 8, 1)->nullable()->after('end_lng');
            }
            if (! Schema::hasColumn('missions', 'end_distance_m')) {
                $table->unsignedInteger('end_distance_m')->nullable()->after('end_accuracy_m');
            }
            if (! Schema::hasColumn('missions', 'end_geo_verdict')) {
                $table->string('end_geo_verdict', 32)->nullable()->after('end_distance_m');
            }
        });
    }

    public function down(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            foreach (['end_accuracy_m', 'end_distance_m', 'end_geo_verdict'] as $column) {
                if (Schema::hasColumn('missions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
