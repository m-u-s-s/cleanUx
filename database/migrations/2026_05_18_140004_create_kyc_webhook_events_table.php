<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kyc_webhook_events', function (Blueprint $table) {
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

            $table->unique(['provider', 'external_event_id'], 'kyc_webhook_events_unique');
            $table->index(['status', 'received_at']);
            $table->index(['provider', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_webhook_events');
    }
};
