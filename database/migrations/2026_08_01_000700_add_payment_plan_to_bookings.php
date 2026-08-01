<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L'acompte : un second mouvement d'argent, donc ses propres colonnes.
 *
 * Un acompte est DÉBITÉ, le solde est seulement BLOQUÉ. Deux natures différentes, deux
 * PaymentIntent Stripe distincts — l'un capturé immédiatement, l'autre en attente. Les écraser
 * dans les colonnes existantes rendrait impossible de dire, six mois plus tard, ce qui a été
 * réellement encaissé et ce qui n'était qu'une empreinte.
 *
 * `payment_plan` est écrit sur la réservation, pas déduit des montants : c'est le CHOIX du client,
 * et il doit rester lisible même si les montants changent ensuite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'payment_plan')) {
                $table->string('payment_plan', 20)->nullable()->after('payment_status');
            }

            if (! Schema::hasColumn('bookings', 'deposit_payment_intent_id')) {
                $table->string('deposit_payment_intent_id')->nullable()->after('payment_plan');
            }

            if (! Schema::hasColumn('bookings', 'deposit_amount_cents')) {
                $table->unsignedInteger('deposit_amount_cents')->nullable()->after('deposit_payment_intent_id');
            }

            if (! Schema::hasColumn('bookings', 'deposit_captured_at')) {
                $table->timestamp('deposit_captured_at')->nullable()->after('deposit_amount_cents');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            foreach (['payment_plan', 'deposit_payment_intent_id', 'deposit_amount_cents', 'deposit_captured_at'] as $column) {
                if (Schema::hasColumn('bookings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
