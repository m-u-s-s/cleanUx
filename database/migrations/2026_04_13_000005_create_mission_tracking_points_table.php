<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mission_tracking_points', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tracking_session_id')
                ->constrained('mission_tracking_sessions')
                ->cascadeOnDelete();

            $table->dateTime('recorded_at');

            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);

            $table->decimal('accuracy_meters', 8, 2)->nullable();
            $table->decimal('speed_kmh', 8, 2)->nullable();
            $table->decimal('heading', 8, 2)->nullable();
            $table->unsignedSmallInteger('battery_level')->nullable();

            $table->string('source')->default('browser'); // browser, app, manual
            $table->string('app_state')->nullable(); // foreground, background
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['tracking_session_id', 'recorded_at']);
            $table->index('recorded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_tracking_points');
    }
};