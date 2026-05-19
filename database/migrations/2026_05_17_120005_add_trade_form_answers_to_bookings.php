<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F1 — réponses au schema de formulaire du Trade pour ce booking.
 *
 * Structure : objet plat keyé par field.key du schema du Trade.
 *   { "nb_enfants": 2, "type_serrure": "blindee", "options_extras": ["fournitures_incluses"] }
 *
 * Le calcul du delta de prix se fait via App\Support\TradeFormSchema::computePriceDelta().
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bookings') || Schema::hasColumn('bookings', 'trade_form_answers')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table) {
            $table->json('trade_form_answers')->nullable();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('bookings') && Schema::hasColumn('bookings', 'trade_form_answers')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dropColumn('trade_form_answers');
            });
        }
    }
};
