<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->string('platform', 16);  // ios, android, web
            $table->string('provider', 16);  // fcm, apns, mock

            $table->text('token');
            $table->char('token_hash', 64);  // sha256 for unique index

            $table->string('app_version', 32)->nullable();
            $table->string('locale', 8)->nullable();
            $table->string('timezone', 64)->nullable();
            $table->string('device_model', 64)->nullable();

            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('invalidated_at')->nullable();
            $table->string('invalidation_reason', 64)->nullable();

            // Opt-in per category — JSON for flexibility
            $table->json('preferences')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->unique('token_hash');
            $table->index(['user_id', 'invalidated_at']);
            $table->index(['platform', 'provider']);
        });

        Schema::create('push_notifications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->foreignId('device_token_id')->nullable()
                ->constrained('device_tokens')->nullOnDelete();

            $table->string('provider', 16);
            $table->string('external_id', 128)->nullable();

            $table->string('title', 255)->nullable();
            $table->text('body');
            $table->json('data')->nullable();  // payload custom (deep links, etc.)

            $table->string('locale', 8)->nullable();
            $table->string('category', 32)->nullable();  // transactional|reminder|marketing|verification

            $table->enum('status', [
                'queued',
                'sent',
                'delivered',
                'failed',
                'opted_out',
                'invalid_token',
                'rate_limited',
            ])->default('queued');

            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('failed_reason')->nullable();
            $table->string('failure_code', 64)->nullable();

            $table->string('source_type', 64)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();

            $table->string('idempotency_key', 128)->nullable()->unique();

            $table->timestamp('queued_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['status', 'queued_at']);
            $table->index(['user_id', 'queued_at']);
            $table->index(['source_type', 'source_id']);
            $table->index('external_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_notifications');
        Schema::dropIfExists('device_tokens');
    }
};
