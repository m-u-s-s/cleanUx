<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_sessions', function (Blueprint $table) {
            $table->id();

            $table->string('session_id', 64)->unique();

            $table->foreignId('user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->string('anonymous_id', 64)->nullable();

            $table->string('source', 32)->nullable();  // web|mobile|api|server
            $table->string('platform', 32)->nullable();
            $table->string('locale', 8)->nullable();
            $table->string('country_code', 8)->nullable();

            $table->string('first_url', 500)->nullable();
            $table->string('first_referrer', 500)->nullable();
            $table->string('user_agent_short', 191)->nullable();

            $table->unsignedInteger('page_count')->default(0);
            $table->unsignedInteger('event_count')->default(0);

            $table->timestamp('started_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('ended_at')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index('user_id');
            $table->index('anonymous_id');
            $table->index('started_at');
        });

        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();

            $table->string('event_name', 128);
            $table->string('event_category', 32)->nullable();

            $table->string('session_id', 64)->nullable();
            $table->foreignId('user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('anonymous_id', 64)->nullable();

            $table->json('properties')->nullable();

            $table->string('source', 32)->nullable();
            $table->string('platform', 32)->nullable();
            $table->string('locale', 8)->nullable();
            $table->string('country_code', 8)->nullable();

            $table->string('url', 500)->nullable();
            $table->string('referrer', 500)->nullable();
            $table->string('user_agent_short', 191)->nullable();
            $table->char('ip_hash', 64)->nullable();

            $table->decimal('revenue_cents', 12, 0)->nullable();
            $table->string('currency', 3)->nullable();

            $table->string('idempotency_key', 191)->nullable()->unique();

            $table->timestamp('occurred_at')->index();

            $table->timestamps();

            $table->index(['event_name', 'occurred_at']);
            $table->index(['event_category', 'occurred_at']);
            $table->index(['user_id', 'occurred_at']);
            $table->index(['session_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
        Schema::dropIfExists('analytics_sessions');
    }
};
