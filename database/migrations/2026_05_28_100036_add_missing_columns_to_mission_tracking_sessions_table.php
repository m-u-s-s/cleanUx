<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema-drift repair for App\Models\MissionTrackingSession.
 *
 * The model declares assignment_id, is_client_visible, ended_at, start_lat,
 * start_lng, point_count, distance_meters and meta as fillable/cast with no
 * matching column on mission_tracking_sessions. Added defensively with types
 * matching the casts.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mission_tracking_sessions')) {
            return;
        }

        Schema::table('mission_tracking_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('mission_tracking_sessions', 'assignment_id')) {
                $table->unsignedBigInteger('assignment_id')->nullable()->index();
            }
            if (! Schema::hasColumn('mission_tracking_sessions', 'is_client_visible')) {
                $table->boolean('is_client_visible')->default(false);
            }
            if (! Schema::hasColumn('mission_tracking_sessions', 'ended_at')) {
                $table->timestamp('ended_at')->nullable();
            }
            if (! Schema::hasColumn('mission_tracking_sessions', 'start_lat')) {
                $table->decimal('start_lat', 10, 7)->nullable();
            }
            if (! Schema::hasColumn('mission_tracking_sessions', 'start_lng')) {
                $table->decimal('start_lng', 10, 7)->nullable();
            }
            if (! Schema::hasColumn('mission_tracking_sessions', 'point_count')) {
                $table->unsignedInteger('point_count')->nullable();
            }
            if (! Schema::hasColumn('mission_tracking_sessions', 'distance_meters')) {
                $table->integer('distance_meters')->nullable();
            }
            if (! Schema::hasColumn('mission_tracking_sessions', 'meta')) {
                $table->json('meta')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('mission_tracking_sessions')) {
            return;
        }

        Schema::table('mission_tracking_sessions', function (Blueprint $table) {
            foreach ([
                'assignment_id',
                'is_client_visible',
                'ended_at',
                'start_lat',
                'start_lng',
                'point_count',
                'distance_meters',
                'meta',
            ] as $col) {
                if (Schema::hasColumn('mission_tracking_sessions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
