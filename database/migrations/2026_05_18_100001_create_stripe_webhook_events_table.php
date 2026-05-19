<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stripe_webhook_events', function (Blueprint $table) {
            $table->id();

            $table->string('stripe_event_id', 128)->unique();
            $table->string('type', 128);

            $table->enum('status', [
                'received',
                'processing',
                'processed',
                'ignored',
                'failed',
                'dead_letter',
            ])->default('received');

            $table->json('payload');
            $table->json('result')->nullable();

            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('max_attempts')->default(5);
            $table->text('last_error')->nullable();

            $table->timestamp('received_at');
            $table->timestamp('first_attempted_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();

            $table->string('account_id', 128)->nullable();

            $table->timestamps();

            $table->index(['status', 'next_retry_at']);
            $table->index(['type', 'status']);
            $table->index('account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_webhook_events');
    }
};
