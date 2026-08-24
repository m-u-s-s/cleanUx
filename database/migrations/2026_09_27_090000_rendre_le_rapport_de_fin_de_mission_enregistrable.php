<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Ces trois colonnes ont ete supprimees des migrations le 2026-05-05 sans que le code qui les
 * ecrit ni les vingt et un sites qui les lisent ne bougent : enregistrer un rapport de fin de
 * mission levait `no such column`. Elles reviennent aupres de leurs soeurs de terrain, qui, elles,
 * ont survecu — `remarque_terrain`, `duree_reelle`, `photos_apres`, `client_presence_confirmed_at`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'commentaire_fin_mission')) {
                $table->text('commentaire_fin_mission')->nullable()->after('remarque_terrain');
            }

            if (! Schema::hasColumn('bookings', 'incident_terrain')) {
                $table->text('incident_terrain')->nullable()->after('remarque_terrain');
            }

            if (! Schema::hasColumn('bookings', 'client_signature_path')) {
                $table->string('client_signature_path')->nullable()->after('client_presence_confirmed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            foreach (['commentaire_fin_mission', 'incident_terrain', 'client_signature_path'] as $colonne) {
                if (Schema::hasColumn('bookings', $colonne)) {
                    $table->dropColumn($colonne);
                }
            }
        });
    }
};
