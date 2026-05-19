<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('referrer_user_id')
                ->constrained('users')->cascadeOnDelete();

            $table->foreignId('referee_user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->string('referee_email')->nullable();
            $table->string('referral_code', 64);

            $table->enum('status', [
                'invited',
                'signed_up',
                'qualified',
                'rewarded',
                'expired',
                'fraud_flagged',
            ])->default('invited');

            $table->foreignId('qualifying_booking_id')->nullable()
                ->constrained('bookings')->nullOnDelete();

            $table->timestamp('invited_at')->nullable();
            $table->timestamp('signed_up_at')->nullable();
            $table->timestamp('qualified_at')->nullable();
            $table->timestamp('rewarded_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->decimal('referrer_reward_amount', 10, 2)->nullable();
            $table->decimal('referee_reward_amount', 10, 2)->nullable();
            $table->string('currency', 3)->default('EUR');

            $table->string('source_channel')->nullable();
            $table->string('ip_signup')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['referrer_user_id', 'status']);
            $table->index(['referee_user_id', 'status']);
            $table->index('referral_code');
            $table->index('referee_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
