<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** F1 — réponses au schema de formulaire du Trade pour ce booking. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bookings')) {
            return;
        }

        if (! Schema::hasColumn('bookings', 'trade_form_answers')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->json('trade_form_answers')->nullable();
            });
        }

        if (! Schema::hasColumn('bookings', 'trade_id') && Schema::hasTable('trades')) {
            Schema::table('bookings', function (Blueprint $table) {
                // `nullOnDelete` plutôt que `cascade` : archiver un métier ne doit pas effacer
                // l'historique des interventions qu'il a produites — ni les factures qui s'y
                // rattachent.
                $table->foreignId('trade_id')->nullable()->after('service_catalog_id')
                    ->constrained('trades')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('bookings')) {
            return;
        }

        if (Schema::hasColumn('bookings', 'trade_id')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dropConstrainedForeignId('trade_id');
            });
        }

        if (Schema::hasColumn('bookings', 'trade_form_answers')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dropColumn('trade_form_answers');
            });
        }
    }
};
