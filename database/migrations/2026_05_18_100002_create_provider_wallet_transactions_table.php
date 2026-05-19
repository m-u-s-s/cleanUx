<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_wallet_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('provider_user_id')
                ->constrained('users')->cascadeOnDelete();

            $table->enum('type', [
                'earning',          // capture mission completed → crédit
                'tip',              // pourboire client → crédit
                'payout',           // retrait Stripe → débit
                'platform_fee',     // commission plateforme → débit
                'refund_clawback',  // refund client → débit
                'adjustment_credit',
                'adjustment_debit',
                'bonus',
            ]);

            $table->enum('direction', ['credit', 'debit']);

            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('EUR');

            $table->decimal('balance_after', 12, 2)->nullable();

            $table->enum('status', [
                'pending',
                'available',
                'processing',
                'cleared',
                'reversed',
            ])->default('pending');

            $table->string('source_type', 64)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();

            $table->string('stripe_payment_intent_id', 128)->nullable();
            $table->string('stripe_transfer_id', 128)->nullable();
            $table->string('stripe_payout_id', 128)->nullable();
            $table->string('stripe_refund_id', 128)->nullable();
            $table->string('idempotency_key', 64)->nullable()->unique();

            $table->string('description')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['provider_user_id', 'occurred_at']);
            $table->index(['provider_user_id', 'status']);
            $table->index(['source_type', 'source_id']);
            $table->index('stripe_payment_intent_id');
            $table->index('stripe_payout_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_wallet_transactions');
    }
};
