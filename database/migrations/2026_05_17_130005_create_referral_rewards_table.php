<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_rewards', function (Blueprint $table) {
            $table->id();

            $table->foreignId('referral_id')
                ->constrained('referrals')->cascadeOnDelete();

            $table->foreignId('beneficiary_user_id')
                ->constrained('users')->cascadeOnDelete();

            $table->enum('role', ['referrer', 'referee']);

            $table->enum('reward_type', ['credit', 'promo_code', 'cash_payout'])
                ->default('credit');

            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('EUR');

            $table->enum('status', ['pending', 'granted', 'consumed', 'revoked', 'expired'])
                ->default('pending');

            $table->foreignId('customer_credit_id')->nullable()
                ->constrained('customer_credits')->nullOnDelete();

            $table->foreignId('promo_code_id')->nullable()
                ->constrained('promo_codes')->nullOnDelete();

            $table->timestamp('granted_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['referral_id', 'role']);
            $table->index(['beneficiary_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_rewards');
    }
};
