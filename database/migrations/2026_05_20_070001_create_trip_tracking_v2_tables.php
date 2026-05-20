<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Session de tracking : 1 par (provider, booking, attempt)
        Schema::create('trip_tracking_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();

            $table->foreignId('booking_id')
                ->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('provider_user_id')
                ->constrained('users')->cascadeOnDelete();

            // Statut session
            $table->enum('status', [
                'enroute',     // provider en route vers le client
                'arrived',     // arrivé sur place (geofence atteinte)
                'in_mission', // mission démarrée
                'ended',       // terminé
                'cancelled',   // annulée par provider/admin
            ])->default('enroute');

            // Coordonnées destination (snapshot booking)
            $table->decimal('destination_lat', 10, 7)->nullable();
            $table->decimal('destination_lng', 10, 7)->nullable();
            $table->unsignedInteger('geofence_radius_m')->default(150);

            // Coordonnées départ
            $table->decimal('start_lat', 10, 7)->nullable();
            $table->decimal('start_lng', 10, 7)->nullable();

            // Métriques agrégées (mises à jour à chaque ping)
            $table->unsignedInteger('points_count')->default(0);
            $table->unsignedInteger('total_distance_m')->default(0);
            $table->unsignedInteger('current_eta_seconds')->nullable();
            $table->decimal('last_lat', 10, 7)->nullable();
            $table->decimal('last_lng', 10, 7)->nullable();
            $table->decimal('last_speed_mps', 6, 2)->nullable();

            $table->json('metadata')->nullable();

            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('in_mission_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('last_ping_at')->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'status']);
            $table->index(['provider_user_id', 'status']);
            $table->index('last_ping_at');
        });

        // Points GPS : 1 par ping
        Schema::create('trip_tracking_points', function (Blueprint $table) {
            $table->id();

            $table->foreignId('session_id')
                ->constrained('trip_tracking_sessions')->cascadeOnDelete();

            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->decimal('accuracy_m', 6, 1)->nullable();
            $table->decimal('speed_mps', 6, 2)->nullable();
            $table->decimal('heading_deg', 5, 1)->nullable();

            // Distance cumulée depuis début (calculé serveur)
            $table->unsignedInteger('cumulative_distance_m')->default(0);
            // Distance restant à parcourir (calc haversine vs destination)
            $table->unsignedInteger('distance_to_dest_m')->nullable();
            // ETA calculé en secondes
            $table->unsignedInteger('eta_seconds')->nullable();

            // Sequence client-side pour ordre + dedup
            $table->string('client_sequence', 64)->nullable();

            $table->timestamp('recorded_at')->useCurrent();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['session_id', 'recorded_at']);
            $table->unique(['session_id', 'client_sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_tracking_points');
        Schema::dropIfExists('trip_tracking_sessions');
    }
};
