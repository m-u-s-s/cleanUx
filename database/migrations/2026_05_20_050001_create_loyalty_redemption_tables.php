<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_rewards', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 191);
            $table->text('description')->nullable();
            $table->string('reward_type', 24);
            // discount_code | service_credit | physical_item | partner_voucher | charity_donation
            $table->string('category', 32)->nullable();
            $table->unsignedInteger('points_cost');
            $table->unsignedInteger('value_cents')->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->string('image_url', 500)->nullable();
            $table->string('partner_name', 191)->nullable();
            $table->unsignedSmallInteger('min_tier_level')->default(0);
            // 0=Bronze, 1=Silver, 2=Gold, 3=Platinum
            $table->unsignedInteger('stock_remaining')->nullable();
            $table->unsignedInteger('stock_initial')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'points_cost']);
            $table->index(['category', 'reward_type']);
        });

        Schema::create('loyalty_redemptions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reward_id')->constrained('loyalty_rewards')->restrictOnDelete();
            $table->unsignedInteger('points_spent');
            $table->string('status', 16)->default('pending');
            // pending | confirmed | delivered | cancelled | refunded
            $table->string('delivery_method', 24)->nullable();
            // email_code | postal | in_app_credit | manual
            $table->string('voucher_code', 64)->nullable();
            $table->string('shipping_address', 500)->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['reward_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_redemptions');
        Schema::dropIfExists('loyalty_rewards');
    }
};
