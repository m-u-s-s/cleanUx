<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema-drift repair: create the `mission_batch_days` table.
 *
 * App\Models\MissionBatchDay is created by
 * App\Services\Missions\MissionBatchPlannerService and
 * EnterpriseWorkOrderMissionGeneratorService, and is the parent of
 * MissionTaskSegment / MissionReinforcementRequest via mission_batch_day_id,
 * but no migration ever created its table. Columns derived from $fillable / $casts.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mission_batch_days')) {
            Schema::create('mission_batch_days', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('mission_batch_id')->nullable()->index();
                $table->unsignedBigInteger('field_team_id')->nullable()->index();
                $table->unsignedBigInteger('service_partner_id')->nullable()->index();
                $table->string('status')->nullable()->index();
                $table->date('service_date')->nullable();
                $table->timestamp('planned_start_at')->nullable();
                $table->timestamp('planned_end_at')->nullable();
                $table->integer('target_mission_count')->nullable();
                $table->json('metadata')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_batch_days');
    }
};
