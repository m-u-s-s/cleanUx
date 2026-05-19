<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute les colonnes Stripe Connect manquantes sur `bookings`.
 *
 * Note: pas de `->after()` ici — la table `bookings` est très large et certaines
 * colonnes "logiques" (ex: payment_failed_at) sont fillable dans le model mais
 * absentes du schéma MySQL réel. On laisse MySQL placer les colonnes en fin de
 * table — l'ordre logique n'a pas d'impact fonctionnel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'stripe_payment_intent_id')) {
                $table->string('stripe_payment_intent_id', 128)->nullable();
            }
            if (! Schema::hasColumn('bookings', 'payment_status')) {
                $table->string('payment_status', 32)->nullable();
            }
            if (! Schema::hasColumn('bookings', 'payment_amount_cents')) {
                $table->unsignedBigInteger('payment_amount_cents')->nullable();
            }
            if (! Schema::hasColumn('bookings', 'provider_amount_cents')) {
                $table->unsignedBigInteger('provider_amount_cents')->nullable();
            }
            if (! Schema::hasColumn('bookings', 'platform_fee_cents')) {
                $table->unsignedBigInteger('platform_fee_cents')->nullable();
            }
            if (! Schema::hasColumn('bookings', 'payment_refunded_at')) {
                $table->timestamp('payment_refunded_at')->nullable();
            }

            // Colonnes payment_*_at référencées par le model Booking mais souvent
            // absentes en MySQL — on les crée si manquantes (idempotent).
            foreach ([
                'payment_authorized_at',
                'payment_captured_at',
                'payment_cancelled_at',
                'payment_failed_at',
            ] as $col) {
                if (! Schema::hasColumn('bookings', $col)) {
                    $table->timestamp($col)->nullable();
                }
            }
        });

        try {
            Schema::table('bookings', function (Blueprint $table) {
                $table->index('stripe_payment_intent_id', 'bookings_stripe_payment_intent_id_index');
            });
        } catch (\Throwable $e) {
            // index already exists — ignore
        }
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            try {
                $table->dropIndex('bookings_stripe_payment_intent_id_index');
            } catch (\Throwable $e) {
                // index missing — ignore
            }

            foreach ([
                'stripe_payment_intent_id',
                'payment_status',
                'payment_amount_cents',
                'provider_amount_cents',
                'platform_fee_cents',
                'payment_refunded_at',
            ] as $col) {
                if (Schema::hasColumn('bookings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
