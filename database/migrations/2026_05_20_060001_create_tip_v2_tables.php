<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_tips', function (Blueprint $table) {
            $table->id();

            $table->string('code', 32)->unique();

            $table->foreignId('booking_id')
                ->constrained('bookings')->cascadeOnDelete();

            $table->foreignId('client_user_id')
                ->constrained('users')->cascadeOnDelete();

            $table->foreignId('provider_user_id')
                ->constrained('users')->cascadeOnDelete();

            // Montants en cents (3 currency)
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3)->default('EUR');

            // Source et état
            $table->enum('status', [
                'pending',         // créé, en attente charge Stripe
                'charged',         // payé par le client (Stripe captured)
                'paid_out',        // versé au provider via Stripe Connect transfer
                'failed',          // charge Stripe failed
                'refunded',        // refundé (rare, par admin si litige)
                'cancelled',       // annulé par client avant charge
            ])->default('pending');

            // Stripe references
            $table->string('stripe_payment_intent_id', 128)->nullable();
            $table->string('stripe_transfer_id', 128)->nullable();

            // Bonus loyalty points crédités au client pour avoir tippé (incite)
            $table->unsignedInteger('client_bonus_points')->default(0);

            // Optional message client → provider
            $table->string('message', 280)->nullable();

            // Suggestion preset utilisée (10/15/20% ou custom)
            $table->string('preset_label', 16)->nullable();
            $table->unsignedTinyInteger('preset_percent')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamp('charged_at')->nullable();
            $table->timestamp('paid_out_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'status']);
            $table->index(['client_user_id', 'created_at']);
            $table->index(['provider_user_id', 'status', 'created_at']);
            $table->index('stripe_payment_intent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_tips');
    }
};
