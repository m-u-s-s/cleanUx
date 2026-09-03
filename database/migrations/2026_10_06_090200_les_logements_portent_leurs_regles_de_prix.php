<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LES LOGEMENTS PORTENT LEURS MAJORATIONS, COMME LES VÉHICULES.
 *
 * Week-end et haute saison valent pour les deux : un studio se paie plus cher un samedi de
 * juillet, tout comme une voiture. Le véhicule a déjà `pricing_rules` ; le logement l'obtient.
 *
 * UNE COLONNE, PAS UNE CLÉ DE `metadata`. Ce dépôt a déjà payé le prix des données utiles cachées
 * dans un fourre-tout JSON : elles échappent aux requêtes, aux index, et à toute relecture.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('peer_stays', function (Blueprint $table) {
            $table->json('pricing_rules')->nullable()->after('extra_guest_price_cents');
        });
    }

    public function down(): void
    {
        Schema::table('peer_stays', function (Blueprint $table) {
            $table->dropColumn('pricing_rules');
        });
    }
};
