<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P4 — provision columns that application code reads/writes but were never migrated, so the writes
 * were silently dropped (or the read returned null). hasColumn-guarded so it is idempotent and safe
 * across environments that may already have some of them.
 *
 *  - bookings.feedback_demande_envoye_at  — SendRendezVousReminders queries whereNull(...) + stamps it
 *  - bookings.remarque_terrain            — Employe\MesRendezVous persists the field-note on save
 *  - enterprise_work_orders.generated_batch_id / generation_status / generation_started_at
 *                                         — EnterpriseWorkOrderMissionGeneratorService forceFills them
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'feedback_demande_envoye_at')) {
                $table->timestamp('feedback_demande_envoye_at')->nullable();
            }
            if (! Schema::hasColumn('bookings', 'remarque_terrain')) {
                $table->text('remarque_terrain')->nullable();
            }
        });

        Schema::table('enterprise_work_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('enterprise_work_orders', 'generated_batch_id')) {
                $table->unsignedBigInteger('generated_batch_id')->nullable()->index();
            }
            if (! Schema::hasColumn('enterprise_work_orders', 'generation_status')) {
                $table->string('generation_status')->nullable();
            }
            if (! Schema::hasColumn('enterprise_work_orders', 'generation_started_at')) {
                $table->timestamp('generation_started_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            foreach (['feedback_demande_envoye_at', 'remarque_terrain'] as $col) {
                if (Schema::hasColumn('bookings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('enterprise_work_orders', function (Blueprint $table) {
            foreach (['generated_batch_id', 'generation_status', 'generation_started_at'] as $col) {
                if (Schema::hasColumn('enterprise_work_orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
