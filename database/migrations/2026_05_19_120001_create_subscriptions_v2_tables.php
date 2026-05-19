<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Module Subscription v2 utilise des tables *_v2 pour ne pas entrer
        // en collision avec Laravel Cashier (`subscriptions`) ni le legacy
        // `subscription_plans` (Phase early du projet).
        Schema::create('subscription_plans_v2', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 191);
            $table->text('description')->nullable();
            $table->json('trade_codes')->nullable();
            $table->string('billing_period', 16);   // weekly | biweekly | monthly | quarterly | yearly
            $table->unsignedInteger('price_cents');
            $table->string('currency', 3)->default('EUR');
            $table->unsignedSmallInteger('included_units_per_cycle')->default(0);
            $table->string('included_unit_type', 24)->nullable();   // hours | visits | sessions | kg | sqm
            $table->unsignedInteger('overage_unit_price_cents')->nullable();
            $table->unsignedSmallInteger('trial_days')->default(0);
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('version', 32)->default('v1');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'billing_period']);
        });

        Schema::create('subscriptions_v2', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->foreignId('plan_id')->constrained('subscription_plans_v2');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('provider_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 24)->default('trialing');
            // trialing | active | paused | past_due | cancelled | expired
            $table->timestamp('started_at');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_cycle_start')->nullable();
            $table->timestamp('current_cycle_end')->nullable();
            $table->timestamp('next_billing_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->string('billing_currency', 3)->default('EUR');
            $table->unsignedInteger('billing_cycle_count')->default(0);
            $table->unsignedBigInteger('total_billed_cents')->default(0);
            $table->unsignedSmallInteger('consecutive_failed_charges')->default(0);
            $table->string('stripe_subscription_id', 191)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'next_billing_at']);
            $table->index(['provider_user_id', 'status']);
        });

        Schema::create('subscription_cycles_v2', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('subscriptions_v2')->cascadeOnDelete();
            $table->unsignedInteger('cycle_number');
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->unsignedInteger('planned_amount_cents');
            $table->unsignedInteger('billed_amount_cents')->nullable();
            $table->unsignedSmallInteger('used_units')->default(0);
            $table->string('billing_status', 16)->default('pending');
            // pending | invoiced | paid | failed | skipped
            $table->timestamp('billed_at')->nullable();
            $table->foreignId('invoice_id')->nullable();
            $table->json('billing_raw')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamps();

            $table->unique(['subscription_id', 'cycle_number'], 'sub_cycles_v2_sub_num_unique');
            $table->index(['billing_status']);
        });

        Schema::create('subscription_invoices_v2', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->foreignId('subscription_id')->constrained('subscriptions_v2')->cascadeOnDelete();
            $table->foreignId('cycle_id')->nullable()->constrained('subscription_cycles_v2')->nullOnDelete();
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3)->default('EUR');
            $table->string('status', 16)->default('draft');
            // draft | open | paid | failed | void
            $table->string('stripe_invoice_id', 191)->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['subscription_id', 'status']);
            $table->index(['status', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_invoices_v2');
        Schema::dropIfExists('subscription_cycles_v2');
        Schema::dropIfExists('subscriptions_v2');
        Schema::dropIfExists('subscription_plans_v2');
    }
};
