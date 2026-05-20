<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_presence', function (Blueprint $table) {
            $table->id();

            $table->foreignId('provider_user_id')
                ->constrained('users')->cascadeOnDelete();

            $table->enum('status', ['online', 'busy', 'on_break', 'offline'])->default('offline');

            // Position courante (optionnelle)
            $table->decimal('current_lat', 10, 7)->nullable();
            $table->decimal('current_lng', 10, 7)->nullable();
            $table->unsignedInteger('available_radius_km')->nullable();

            // Heartbeat tracking
            $table->timestamp('heartbeat_at')->nullable();
            $table->timestamp('last_status_change_at')->nullable();
            $table->timestamp('last_online_at')->nullable();

            // Snapshot des minutes online cette semaine/jour (analytics)
            $table->unsignedInteger('online_minutes_today')->default(0);
            $table->unsignedInteger('online_minutes_week')->default(0);

            $table->string('device_info', 255)->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->unique('provider_user_id');
            $table->index(['status', 'heartbeat_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_presence');
    }
};
