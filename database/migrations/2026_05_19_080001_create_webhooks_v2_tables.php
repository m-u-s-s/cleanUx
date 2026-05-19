<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 191);
            $table->text('description')->nullable();
            $table->foreignId('owner_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('url', 500);
            $table->string('secret', 128);
            $table->json('headers')->nullable();
            $table->unsignedSmallInteger('timeout_seconds')->default(15);
            $table->unsignedSmallInteger('max_attempts')->default(6);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->boolean('is_suspended')->default(false);
            $table->text('suspension_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['owner_user_id', 'is_active']);
            $table->index(['is_active', 'is_suspended']);
        });

        Schema::create('webhook_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('endpoint_id')
                ->constrained('webhook_endpoints')->cascadeOnDelete();
            $table->string('event_code', 64);
            $table->json('filters')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['endpoint_id', 'event_code'], 'webhook_subs_endpoint_event_unique');
            $table->index(['event_code', 'is_active']);
        });

        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id', 64)->unique();
            $table->string('event_code', 64);
            $table->json('payload');
            $table->string('idempotency_key', 191)->nullable()->unique();
            $table->string('source_type', 64)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['event_code', 'occurred_at']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')
                ->constrained('webhook_events')->cascadeOnDelete();
            $table->foreignId('endpoint_id')
                ->constrained('webhook_endpoints')->cascadeOnDelete();
            $table->string('status', 24)->default('pending');
            // pending | in_flight | delivered | failed | dead | cancelled
            $table->unsignedSmallInteger('attempt')->default(0);
            $table->unsignedSmallInteger('max_attempts')->default(6);
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->unsignedSmallInteger('last_response_status')->nullable();
            $table->text('last_response_body')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedInteger('last_latency_ms')->nullable();
            $table->char('signature_sent', 64)->nullable();
            $table->string('idempotency_key_sent', 191)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'next_retry_at']);
            $table->index(['endpoint_id', 'status']);
            $table->index(['event_id', 'endpoint_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('webhook_subscriptions');
        Schema::dropIfExists('webhook_endpoints');
    }
};
