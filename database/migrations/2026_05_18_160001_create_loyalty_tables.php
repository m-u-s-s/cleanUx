<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 32)->unique();
            $table->string('name', 64);
            $table->unsignedInteger('min_period_points')->default(0);
            $table->unsignedSmallInteger('rank')->default(0);
            $table->string('color', 16)->nullable();
            $table->string('icon', 16)->nullable();
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->boolean('priority_dispatch')->default(false);
            $table->boolean('vip_support')->default(false);
            $table->json('benefits')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'rank']);
            $table->index('min_period_points');
        });

        Schema::create('loyalty_accounts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')->cascadeOnDelete();

            $table->foreignId('current_tier_id')->nullable()
                ->constrained('loyalty_tiers')->nullOnDelete();

            $table->unsignedInteger('lifetime_points')->default(0);
            $table->unsignedInteger('period_points')->default(0);
            $table->unsignedInteger('redeemable_points')->default(0);

            $table->timestamp('tier_started_at')->nullable();
            $table->timestamp('tier_evaluated_at')->nullable();
            $table->timestamp('points_period_started_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->unique('user_id');
            $table->index('current_tier_id');
        });

        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('loyalty_account_id')
                ->constrained('loyalty_accounts')->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')->cascadeOnDelete();

            $table->enum('type', [
                'earn_booking',
                'earn_referral',
                'earn_rating',
                'earn_signup_bonus',
                'earn_anniversary',
                'earn_promo',
                'earn_adjustment',
                'redeem',
                'expire',
                'penalty',
                'admin_adjust',
            ]);

            $table->enum('direction', ['credit', 'debit']);
            $table->integer('points');
            $table->integer('balance_after')->nullable();

            $table->string('source_type', 64)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();

            $table->string('idempotency_key', 96)->nullable()->unique();
            $table->text('reason')->nullable();
            $table->foreignId('actor_user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->json('metadata')->nullable();

            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['user_id', 'occurred_at']);
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_transactions');
        Schema::dropIfExists('loyalty_accounts');
        Schema::dropIfExists('loyalty_tiers');
    }
};
