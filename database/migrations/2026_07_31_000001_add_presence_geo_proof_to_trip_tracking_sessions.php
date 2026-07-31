<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trace de la position au moment où la présence a été confirmée.
 *
 * Le code affiché par le client atteste d'une possession, pas d'une présence : photographié ou
 * dicté au téléphone, il se valide depuis n'importe où pendant ses dix minutes de vie. La position
 * du prestataire au moment du scan est ce qui referme cet écart.
 *
 * Ces colonnes ne servent pas le contrôle — il a déjà eu lieu quand on écrit ici. Elles servent le
 * litige : six mois plus tard, « le prestataire n'est jamais venu » se tranche avec une distance
 * mesurée, pas avec une conviction.
 *
 * `presence_geo_verdict` porte le VERDICT et non seulement la distance, parce qu'une distance
 * absente est ambiguë : contrôle désactivé, lieu sans coordonnées, ou position refusée par
 * l'appareil ? Ces trois cas se lisent identiquement sur une colonne nulle, et ne se plaident pas
 * pareil.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_tracking_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('trip_tracking_sessions', 'presence_confirmed_lat')) {
                $table->decimal('presence_confirmed_lat', 10, 7)->nullable()->after('presence_confirmed_by_user_id');
            }
            if (! Schema::hasColumn('trip_tracking_sessions', 'presence_confirmed_lng')) {
                $table->decimal('presence_confirmed_lng', 10, 7)->nullable()->after('presence_confirmed_lat');
            }
            if (! Schema::hasColumn('trip_tracking_sessions', 'presence_confirmed_accuracy_m')) {
                $table->decimal('presence_confirmed_accuracy_m', 8, 1)->nullable()->after('presence_confirmed_lng');
            }
            if (! Schema::hasColumn('trip_tracking_sessions', 'presence_confirmed_distance_m')) {
                $table->unsignedInteger('presence_confirmed_distance_m')->nullable()->after('presence_confirmed_accuracy_m');
            }
            if (! Schema::hasColumn('trip_tracking_sessions', 'presence_geo_verdict')) {
                $table->string('presence_geo_verdict', 32)->nullable()->after('presence_confirmed_distance_m');
            }
        });
    }

    public function down(): void
    {
        Schema::table('trip_tracking_sessions', function (Blueprint $table) {
            foreach ([
                'presence_confirmed_lat',
                'presence_confirmed_lng',
                'presence_confirmed_accuracy_m',
                'presence_confirmed_distance_m',
                'presence_geo_verdict',
            ] as $column) {
                if (Schema::hasColumn('trip_tracking_sessions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
