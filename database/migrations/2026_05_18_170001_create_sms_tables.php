<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();

            $table->string('provider', 32);
            $table->string('external_id', 128)->nullable();

            $table->string('to_phone', 32);
            $table->string('from_phone', 32)->nullable();
            $table->text('body');
            $table->string('locale', 8)->nullable();

            $table->enum('status', [
                'queued',
                'sent',
                'delivered',
                'failed',
                'undelivered',
                'rate_limited',
                'rejected',
            ])->default('queued');

            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('failed_reason')->nullable();
            $table->string('failure_code', 32)->nullable();

            $table->string('source_type', 64)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->string('idempotency_key', 128)->nullable()->unique();
            $table->string('category', 32)->nullable();

            $table->decimal('cost_eur', 8, 4)->nullable();

            $table->timestamp('queued_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['status', 'queued_at']);
            $table->index(['user_id', 'queued_at']);
            $table->index(['source_type', 'source_id']);
            $table->index('to_phone');
            $table->index('external_id');
        });

        Schema::create('sms_webhook_events', function (Blueprint $table) {
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

            $table->unique(['provider', 'external_event_id'], 'sms_webhook_events_unique');
            $table->index(['status', 'received_at']);
        });

        Schema::create('phone_verification_codes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')->cascadeOnDelete();

            $table->string('phone', 32);
            $table->string('code_hash', 128);

            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('max_attempts')->default(5);

            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->string('purpose', 32)->default('phone_verification');

            $table->string('ip_address', 45)->nullable();

            $table->timestamps();

            $table->index(['user_id', 'purpose']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_verification_codes');
        Schema::dropIfExists('sms_webhook_events');
        Schema::dropIfExists('sms_messages');
    }
};
