<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_favorites', function (Blueprint $table) {
            $table->id();

            $table->foreignId('client_user_id')
                ->constrained('users')->cascadeOnDelete();

            $table->string('label', 128)->nullable();
            $table->foreignId('source_booking_id')->nullable()
                ->constrained('bookings')->nullOnDelete();

            $table->foreignId('preferred_provider_user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->foreignId('trade_id')->nullable()
                ->constrained('trades')->nullOnDelete();

            $table->foreignId('service_zone_id')->nullable();

            // Snapshot des détails à rebooker (adresse, durée estimée, options)
            $table->json('snapshot')->nullable();

            $table->unsignedInteger('use_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['client_user_id', 'last_used_at']);
            $table->index(['preferred_provider_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_favorites');
    }
};
