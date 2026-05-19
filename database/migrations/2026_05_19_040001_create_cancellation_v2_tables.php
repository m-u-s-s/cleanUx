<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cancellation_policies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 191);
            $table->text('description')->nullable();
            $table->json('trade_codes')->nullable();   // null = all trades
            $table->string('actor_role', 16);          // client | provider | both
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['actor_role', 'is_active']);
        });

        Schema::create('cancellation_policy_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_id')
                ->constrained('cancellation_policies')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->unsignedInteger('min_hours_before');     // inclusive
            $table->unsignedInteger('max_hours_before')->nullable();  // exclusive, null = no upper bound
            $table->decimal('fee_percent', 6, 2)->default(0);
            $table->unsignedInteger('fee_flat_cents')->default(0);
            $table->string('description', 191)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['policy_id', 'position']);
            $table->index(['policy_id', 'min_hours_before']);
        });

        Schema::create('cancellation_exempt_reasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_id')->nullable()
                ->constrained('cancellation_policies')->cascadeOnDelete();
            $table->string('reason_code', 64);
            $table->string('label', 191);
            $table->boolean('requires_proof')->default(false);
            $table->unsignedSmallInteger('max_per_user_per_30d')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['policy_id', 'reason_code']);
        });

        Schema::create('booking_cancellations_v2', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('booking_id');
            $table->foreignId('cancelled_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('actor_role', 16);  // client | provider | admin

            $table->foreignId('policy_id')->nullable()
                ->constrained('cancellation_policies')->nullOnDelete();
            $table->foreignId('tier_id')->nullable()
                ->constrained('cancellation_policy_tiers')->nullOnDelete();

            $table->string('reason_code', 64)->nullable();
            $table->text('reason_text')->nullable();

            $table->decimal('fee_percent_applied', 6, 2)->default(0);
            $table->unsignedInteger('fee_amount_cents')->default(0);
            $table->unsignedInteger('refund_amount_cents')->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->string('refund_method', 24)->nullable();  // stripe | wallet | promo_credit | none

            $table->boolean('exempt_applied')->default(false);
            $table->foreignId('override_admin_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->text('override_reason')->nullable();

            $table->string('booking_status_before', 32)->nullable();
            $table->string('booking_status_after', 32)->nullable();

            $table->json('integrations_log')->nullable();    // stripe.refund_id, loyalty.tx_id, etc.

            $table->string('idempotency_key', 191)->nullable()->unique();
            $table->timestamp('cancelled_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('booking_id');
            $table->index(['actor_role', 'cancelled_at']);
            $table->index(['policy_id', 'cancelled_at']);
        });

        Schema::create('cancellation_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cancellation_id')->nullable()
                ->constrained('booking_cancellations_v2')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('action', 32);  // created | overridden | refunded | refund_failed
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['cancellation_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cancellation_audits');
        Schema::dropIfExists('booking_cancellations_v2');
        Schema::dropIfExists('cancellation_exempt_reasons');
        Schema::dropIfExists('cancellation_policy_tiers');
        Schema::dropIfExists('cancellation_policies');
    }
};
