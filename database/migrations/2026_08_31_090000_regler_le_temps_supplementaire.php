<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** LE RÈGLEMENT DU TEMPS SUPPLÉMENTAIRE — une ligne par mission, qui ATTESTE puis encaisse. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mission_time_settlements')) {
            return;
        }

        Schema::create('mission_time_settlements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('mission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();

            // ── Ce qui a été constaté, figé au moment du calcul ──────────────────────────────
            $table->unsignedInteger('authorized_minutes');
            $table->unsignedInteger('purchased_minutes');
            $table->unsignedInteger('elapsed_minutes');
            $table->unsignedInteger('extension_minutes')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->unsignedInteger('grace_minutes')->default(0);
            $table->boolean('capped')->default(false);

            // ── L'argent ────────────────────────────────────────────────────────────────────
            $table->unsignedInteger('effective_hourly_rate_cents')->nullable();
            $table->decimal('overtime_multiplier', 5, 2)->default(1.00);
            $table->unsignedInteger('authorized_amount_cents')->default(0);
            $table->unsignedInteger('extension_amount_cents')->default(0);
            $table->unsignedInteger('overtime_amount_cents')->default(0);
            // CE QUI RESTE À ENCAISSER, et rien d'autre.
            $table->unsignedInteger('amount_due_cents')->default(0);
            $table->string('currency', 3)->default('EUR');

            // ── L'encaissement ──────────────────────────────────────────────────────────────
            $table->string('status', 20)->default('pending');
            $table->string('stripe_payment_intent_id')->nullable();
            $table->timestamp('charged_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->text('last_error')->nullable();

            $table->timestamps();

            // UNE SEULE LIGNE PAR MISSION.
            $table->unique('mission_id', 'mts_mission_unique');
            $table->index(['status', 'last_attempt_at'], 'mts_reprise_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_time_settlements');
    }
};
