<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_code_redemptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('promo_code_id')
                ->constrained('promo_codes')->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')->cascadeOnDelete();

            $table->foreignId('booking_id')->nullable()
                ->constrained('bookings')->nullOnDelete();

            $table->enum('status', ['reserved', 'applied', 'reverted', 'expired'])
                ->default('applied');

            $table->decimal('booking_amount_before', 10, 2)->nullable();
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('booking_amount_after', 10, 2)->nullable();
            $table->string('currency', 3)->default('EUR');

            $table->timestamp('redeemed_at')->nullable();
            $table->timestamp('reverted_at')->nullable();
            $table->string('reverted_reason')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'promo_code_id']);
            $table->index(['promo_code_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_code_redemptions');
    }
};
