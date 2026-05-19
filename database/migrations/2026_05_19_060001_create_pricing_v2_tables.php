<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_catalog_v2', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 191);
            $table->text('description')->nullable();
            $table->string('trade_code', 64)->nullable();
            $table->unsignedBigInteger('base_price_cents')->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->string('unit', 24)->default('per_visit');  // per_hour | per_m2 | per_visit | flat | per_kg
            $table->unsignedBigInteger('min_price_cents')->default(0);
            $table->unsignedBigInteger('max_price_cents')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('parent_version_id')->nullable()
                ->constrained('service_catalog_v2')->nullOnDelete();
            $table->json('locale_overrides')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['trade_code', 'is_active']);
        });

        Schema::create('pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 191);
            $table->text('description')->nullable();
            $table->string('service_code', 64)->nullable();    // null = applies to all matching trades
            $table->string('trade_code', 64)->nullable();
            $table->unsignedSmallInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->json('applies_when');     // DSL conditions tree
            $table->json('adjustments');      // list of {kind, value, ...}
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'priority']);
            $table->index(['service_code']);
            $table->index(['trade_code']);
        });

        Schema::create('price_quotes', function (Blueprint $table) {
            $table->id();
            $table->string('service_code', 64);
            $table->string('trade_code', 64)->nullable();
            $table->unsignedBigInteger('base_price_cents');
            $table->unsignedBigInteger('computed_price_cents');
            $table->string('currency', 3)->default('EUR');
            $table->json('variables_snapshot')->nullable();
            $table->json('applied_rules')->nullable();
            $table->string('variant_label', 32)->nullable();
            $table->foreignId('user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->string('idempotency_key', 191)->nullable()->unique();
            $table->timestamp('quoted_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['service_code', 'quoted_at']);
            $table->index(['user_id', 'quoted_at']);
            $table->index('booking_id');
        });

        Schema::create('ab_pricing_experiments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 191);
            $table->text('description')->nullable();
            $table->json('service_codes')->nullable();           // list of service codes; null = all
            $table->json('variants');                            // [{label, rules_override}]
            $table->json('traffic_allocation')->nullable();      // {variant_label: percent}
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ab_pricing_experiments');
        Schema::dropIfExists('price_quotes');
        Schema::dropIfExists('pricing_rules');
        Schema::dropIfExists('service_catalog_v2');
    }
};
