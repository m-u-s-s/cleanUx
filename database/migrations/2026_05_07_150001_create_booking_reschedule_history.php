<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6.1 — Historique des reprogrammations de bookings.
 *
 * Trace toutes les fois où un user a déplacé un booking via drag-and-drop
 * dans le calendrier (ou via toute autre méthode).
 *
 * Permet :
 *   - audit (qui a bougé quoi quand)
 *   - rollback potentiel d'un déplacement accidentel
 *   - statistiques (taux de reschedule par client / par mois)
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('booking_reschedule_history')) {
            return;
        }

        Schema::create('booking_reschedule_history', function (Blueprint $table) {
            $table->id();

            $table->foreignId('booking_id')
                ->constrained('bookings')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->date('old_date');
            $table->time('old_time')->nullable();

            $table->date('new_date');
            $table->time('new_time')->nullable();

            $table->string('reason', 500)->nullable();

            $table->timestamps();

            $table->index(['booking_id', 'created_at']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_reschedule_history');
    }
};
