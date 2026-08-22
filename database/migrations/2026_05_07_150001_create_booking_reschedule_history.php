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
return new class extends Migration
{
    public function up(): void
    {
        $this->corpsInitial();
        $this->fusion20260808180000TracerLeContexteEtLeLieuDesReprogrammations();
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_reschedule_history');
    }

    /** Le corps d origine, extrait pour que son `return` ne quitte que lui. */
    private function corpsInitial(): void
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

    /** Fusionne depuis 2026_08_08_180000_tracer_le_contexte_et_le_lieu_des_reprogrammations */
    private function fusion20260808180000TracerLeContexteEtLeLieuDesReprogrammations(): void
    {
        if (! Schema::hasTable('booking_reschedule_history')) {
            return;
        }

        Schema::table('booking_reschedule_history', function (Blueprint $table) {
            if (! Schema::hasColumn('booking_reschedule_history', 'actor_context')) {
                // client | admin | provider — à quel titre la personne a agi.
                $table->string('actor_context', 20)->nullable();
            }

            if (! Schema::hasColumn('booking_reschedule_history', 'old_site_id')) {
                $table->unsignedBigInteger('old_site_id')->nullable();
            }

            if (! Schema::hasColumn('booking_reschedule_history', 'new_site_id')) {
                $table->unsignedBigInteger('new_site_id')->nullable();
            }

            if (! Schema::hasColumn('booking_reschedule_history', 'old_address')) {
                $table->string('old_address', 255)->nullable();
            }

            if (! Schema::hasColumn('booking_reschedule_history', 'new_address')) {
                $table->string('new_address', 255)->nullable();
            }
        });
    }
};
