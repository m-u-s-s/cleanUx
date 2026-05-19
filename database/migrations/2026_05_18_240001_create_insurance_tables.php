<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insurance_plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 191);
            $table->text('description')->nullable();
            $table->json('trade_codes')->nullable();  // null = applicable à tous trades
            $table->unsignedBigInteger('coverage_amount_cents');
            $table->unsignedBigInteger('premium_base_cents')->default(0);
            $table->decimal('premium_percent', 6, 4)->default(0);  // ex: 1.5000 = 1.5%
            $table->unsignedBigInteger('min_premium_cents')->default(0);
            $table->unsignedBigInteger('max_premium_cents')->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->boolean('is_active')->default(true);
            $table->string('terms_url', 500)->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('is_active');
        });

        Schema::create('booking_insurances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('booking_id');
            $table->foreignId('plan_id')
                ->constrained('insurance_plans')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->foreignId('provider_user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->string('policy_number', 64)->nullable();
            $table->unsignedBigInteger('premium_cents');
            $table->unsignedBigInteger('coverage_amount_cents');
            $table->string('currency', 3)->default('EUR');

            $table->string('status', 32)->default('proposed');
            // proposed | active | cancelled | expired | claimed

            $table->string('external_provider', 32)->default('mock');
            $table->string('external_id', 128)->nullable();

            $table->timestamp('purchased_at')->nullable();
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_until')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->string('idempotency_key', 191)->nullable()->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('booking_id');
            $table->index(['status', 'effective_until']);
            $table->index(['external_provider', 'external_id']);
        });

        Schema::create('insurance_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_insurance_id')
                ->constrained('booking_insurances')->cascadeOnDelete();
            $table->foreignId('claimant_user_id')
                ->constrained('users')->cascadeOnDelete();

            $table->string('status', 32)->default('filed');
            // filed | under_review | info_requested | accepted | rejected | paid | cancelled

            $table->string('incident_type', 64);
            $table->text('incident_description');
            $table->date('incident_date');
            $table->unsignedBigInteger('amount_claimed_cents');
            $table->unsignedBigInteger('amount_settled_cents')->nullable();
            $table->text('decision_reason')->nullable();

            $table->string('external_claim_id', 128)->nullable();

            $table->timestamp('filed_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->json('evidence')->nullable();
            $table->string('idempotency_key', 191)->nullable()->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'filed_at']);
            $table->index('claimant_user_id');
        });

        Schema::create('insurance_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->string('external_event_id', 128)->nullable();
            $table->string('event_type', 64)->nullable();
            $table->json('payload');
            $table->enum('status', ['received', 'processed', 'ignored', 'failed'])
                ->default('received');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'external_event_id'], 'insurance_webhook_unique');
            $table->index(['status', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_webhook_events');
        Schema::dropIfExists('insurance_claims');
        Schema::dropIfExists('booking_insurances');
        Schema::dropIfExists('insurance_plans');
    }
};
