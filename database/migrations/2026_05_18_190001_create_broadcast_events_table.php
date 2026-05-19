<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcast_events', function (Blueprint $table) {
            $table->id();

            // Channel name (mission.42, user.5, presence-team.10, etc.)
            $table->string('channel', 128);

            // Event PHP class FQCN or short broadcastAs() name
            $table->string('event_class', 191);
            $table->string('broadcast_as', 128)->nullable();

            // Audience scoping for filtering in admin
            // per_user | per_channel | presence | broadcast
            $table->string('audience', 32)->default('per_channel');
            $table->unsignedBigInteger('audience_id')->nullable();  // user_id if per_user, channel_id if per_channel

            $table->string('category', 32)->nullable();  // mission_eta|presence|chat|notification|status

            $table->json('payload')->nullable();

            $table->enum('status', ['queued', 'sent', 'failed'])->default('queued');

            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('failed_reason')->nullable();

            $table->string('source_type', 64)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();

            $table->string('idempotency_key', 191)->nullable()->unique();

            $table->timestamp('queued_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            $table->timestamps();

            $table->index(['channel', 'queued_at']);
            $table->index(['status', 'queued_at']);
            $table->index(['audience', 'audience_id']);
            $table->index(['source_type', 'source_id']);
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_events');
    }
};
