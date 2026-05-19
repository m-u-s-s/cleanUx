<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F1 — schema de formulaire de réservation par Trade.
 *
 * Stocke en JSON la liste des champs que le client doit remplir quand il
 * réserve un service de ce métier. Permet de remplacer les champs cleaning
 * hardcodés (surface_m2, frequence, zones_specifiques...) par une
 * configuration dynamique par métier.
 *
 * Structure attendue (validée par App\Support\TradeFormSchema) :
 * {
 *   "version": 1,
 *   "fields": [
 *     {"key": "...", "label": "...", "type": "number|boolean|select|multiselect|text|textarea",
 *      "required": bool, "default": ..., "min": ..., "max": ..., "step": ...,
 *      "options": [{"value": "...", "label": "...", "price_delta": float}],
 *      "pricing": {"modifier": "fixed|percent|per_unit", "value": float},
 *      "help": "...", "unit": "...", "max_length": int}
 *   ]
 * }
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('trades') || Schema::hasColumn('trades', 'booking_form_schema')) {
            return;
        }

        Schema::table('trades', function (Blueprint $table) {
            $table->json('booking_form_schema')->nullable()->after('metadata');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('trades') && Schema::hasColumn('trades', 'booking_form_schema')) {
            Schema::table('trades', function (Blueprint $table) {
                $table->dropColumn('booking_form_schema');
            });
        }
    }
};
