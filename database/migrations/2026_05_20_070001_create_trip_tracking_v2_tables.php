<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->corpsInitial();
        $this->fusion20260611000003AddPauseToTripTrackingSessions();
        $this->fusion20260730000001AddPresenceConfirmationToTripTrackingSessions();
        $this->fusion20260731000001AddPresenceGeoProofToTripTrackingSessions();
        $this->fusion20260813090000CumulerLeTempsDePauseDesMissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_tracking_points');
        Schema::dropIfExists('trip_tracking_sessions');
    }

    /** Le corps d origine, extrait pour que son `return` ne quitte que lui. */
    private function corpsInitial(): void
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

    /** Fusionne depuis 2026_06_11_000003_add_pause_to_trip_tracking_sessions */
    private function fusion20260611000003AddPauseToTripTrackingSessions(): void
    {
        Schema::table('trip_tracking_sessions', function (Blueprint $table) {
            $table->boolean('is_paused')->default(false);
            $table->timestamp('paused_at')->nullable();
        });
    }

    /** Fusionne depuis 2026_07_30_000001_add_presence_confirmation_to_trip_tracking_sessions */
    private function fusion20260730000001AddPresenceConfirmationToTripTrackingSessions(): void
    {
        Schema::table('trip_tracking_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('trip_tracking_sessions', 'presence_code_hash')) {
                $table->string('presence_code_hash')->nullable();
            }
            if (! Schema::hasColumn('trip_tracking_sessions', 'presence_code_expires_at')) {
                $table->timestamp('presence_code_expires_at')->nullable();
            }
            if (! Schema::hasColumn('trip_tracking_sessions', 'presence_code_attempts')) {
                $table->unsignedSmallInteger('presence_code_attempts')->default(0);
            }
            if (! Schema::hasColumn('trip_tracking_sessions', 'presence_confirmed_at')) {
                $table->timestamp('presence_confirmed_at')->nullable();
            }
            if (! Schema::hasColumn('trip_tracking_sessions', 'presence_confirmed_by_user_id')) {
                $table->unsignedBigInteger('presence_confirmed_by_user_id')->nullable();
            }
        });
    }

    /** Fusionne depuis 2026_07_31_000001_add_presence_geo_proof_to_trip_tracking_sessions */
    private function fusion20260731000001AddPresenceGeoProofToTripTrackingSessions(): void
    {
        Schema::table('trip_tracking_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('trip_tracking_sessions', 'presence_confirmed_lat')) {
                $table->decimal('presence_confirmed_lat', 10, 7)->nullable();
            }
            if (! Schema::hasColumn('trip_tracking_sessions', 'presence_confirmed_lng')) {
                $table->decimal('presence_confirmed_lng', 10, 7)->nullable();
            }
            if (! Schema::hasColumn('trip_tracking_sessions', 'presence_confirmed_accuracy_m')) {
                $table->decimal('presence_confirmed_accuracy_m', 8, 1)->nullable();
            }
            if (! Schema::hasColumn('trip_tracking_sessions', 'presence_confirmed_distance_m')) {
                $table->unsignedInteger('presence_confirmed_distance_m')->nullable();
            }
            if (! Schema::hasColumn('trip_tracking_sessions', 'presence_geo_verdict')) {
                $table->string('presence_geo_verdict', 32)->nullable();
            }
        });
    }

    /** Fusionne depuis 2026_08_13_090000_cumuler_le_temps_de_pause_des_missions */
    private function fusion20260813090000CumulerLeTempsDePauseDesMissions(): void
    {
        if (! Schema::hasTable('trip_tracking_sessions')) {
            return;
        }

        Schema::table('trip_tracking_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('trip_tracking_sessions', 'paused_total_seconds')) {
                // Défaut à zéro et non nul : « aucune pause » est une valeur, pas une absence de
                // valeur. Un nul obligerait chaque lecteur à décider quoi en faire, et l'un d'eux
                // se tromperait.
                $table->unsignedInteger('paused_total_seconds')->default(0);
            }
        });
    }
};
