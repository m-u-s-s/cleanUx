<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mission_tracking_sessions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('mission_id')
                ->constrained('missions')
                ->cascadeOnDelete();

            $table->foreignId('assignment_id')
                ->nullable()
                ->constrained('mission_assignments')
                ->nullOnDelete();

            $table->foreignId('employee_user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('tracking_mode')->default('to_client'); // to_client, on_site, disabled
            $table->boolean('is_client_visible')->default(true);
            $table->boolean('is_active')->default(true);

            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable();

            $table->decimal('start_lat', 10, 7)->nullable();
            $table->decimal('start_lng', 10, 7)->nullable();
            $table->decimal('last_lat', 10, 7)->nullable();
            $table->decimal('last_lng', 10, 7)->nullable();

            $table->unsignedInteger('point_count')->default(0);
            $table->unsignedInteger('distance_meters')->default(0);

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['mission_id', 'is_active']);
            $table->index(['employee_user_id', 'is_active']);
            $table->index(['tracking_mode', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_tracking_sessions');
    }
};