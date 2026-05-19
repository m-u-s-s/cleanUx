<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('promo_campaign_id')->nullable()
                ->constrained('promo_campaigns')->nullOnDelete();

            $table->string('code', 64)->unique();
            $table->string('name')->nullable();
            $table->text('description')->nullable();

            $table->enum('discount_type', [
                'percent',
                'fixed_amount',
                'free_first_booking',
            ])->default('percent');

            $table->decimal('discount_value', 10, 2)->default(0);
            $table->decimal('max_discount_amount', 10, 2)->nullable();
            $table->decimal('min_booking_amount', 10, 2)->nullable();

            $table->unsignedInteger('max_total_uses')->nullable();
            $table->unsignedInteger('max_uses_per_user')->default(1);
            $table->unsignedInteger('total_uses')->default(0);

            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();

            $table->boolean('first_booking_only')->default(false);
            $table->boolean('stackable_with_credits')->default(true);
            $table->boolean('stackable_with_referral')->default(false);

            $table->enum('audience_scope', ['all', 'new_customers', 'returning_customers', 'b2b', 'specific_users'])
                ->default('all');

            $table->json('allowed_trade_ids')->nullable();
            $table->json('allowed_service_catalog_ids')->nullable();
            $table->json('allowed_country_ids')->nullable();
            $table->json('allowed_zone_ids')->nullable();
            $table->json('allowed_user_ids')->nullable();

            $table->enum('status', ['draft', 'active', 'paused', 'expired', 'archived'])
                ->default('draft');

            $table->enum('source', ['manual', 'campaign', 'referral', 'system'])
                ->default('manual');

            $table->foreignId('issued_to_user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->foreignId('created_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['status', 'valid_from', 'valid_until']);
            $table->index('promo_campaign_id');
            $table->index('issued_to_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_codes');
    }
};
