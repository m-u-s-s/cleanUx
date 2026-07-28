<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Questions posées au PRESTATAIRE qui déclare exercer ce métier.
 *
 * `trades.booking_form_schema` existe déjà, mais il décrit les questions posées au CLIENT au
 * moment de réserver (type de lieu, surface, options tarifées). Rien ne décrivait ce qu'il faut
 * demander à un prestataire selon son métier — un électricien et un babysitter ne relèvent ni des
 * mêmes certifications ni des mêmes assurances.
 *
 * Même forme que booking_form_schema pour que les deux se lisent pareil :
 *   {"fields": [{"key": "...", "type": "select|text|number|boolean", "label": "...",
 *                "required": true, "options": [...]}]}
 *
 * Data-driven volontairement : ajouter une question à un métier ne doit demander aucun déploiement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trades', function (Blueprint $table) {
            $table->json('provider_form_schema')->nullable()->after('booking_form_schema');
        });
    }

    public function down(): void
    {
        Schema::table('trades', function (Blueprint $table) {
            $table->dropColumn('provider_form_schema');
        });
    }
};
