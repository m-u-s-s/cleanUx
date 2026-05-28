<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema-drift repair: create the `mission_tracking_points` table.
 *
 * App\Models\MissionTrackingPoint is created/queried by
 * App\Services\Missions\MissionTrackingService, observed by
 * App\Observers\MissionTrackingPointObserver, and is the target of the
 * MissionTrackingSession hasMany relation, but no migration ever created its
 * table (it replaced the dropped legacy `mission_positions` table — see
 * 2026_05_07_180001_drop_dead_tables). Columns derived from $fillable / $casts.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mission_tracking_points')) {
            Schema::create('mission_tracking_points', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tracking_session_id')->nullable()->index();
                $table->timestamp('recorded_at')->nullable();
                $table->decimal('lat', 10, 7)->nullable();
                $table->decimal('lng', 10, 7)->nullable();
                $table->decimal('accuracy_meters', 8, 2)->nullable();
                $table->decimal('speed_kmh', 8, 2)->nullable();
                $table->decimal('heading', 8, 2)->nullable();
                $table->integer('battery_level')->nullable();
                $table->string('source')->nullable();
                $table->string('app_state')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_tracking_points');
    }
};
