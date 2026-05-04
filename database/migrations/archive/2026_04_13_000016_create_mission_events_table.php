<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mission_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('mission_id')
                ->constrained('missions')
                ->cascadeOnDelete();

            $table->foreignId('actor_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('event_type');
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('happened_at')->nullable();

            $table->timestamps();

            $table->index(['mission_id', 'event_type']);
            $table->index(['mission_id', 'happened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_events');
    }
};