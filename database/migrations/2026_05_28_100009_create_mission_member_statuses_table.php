<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema-drift repair: create the `mission_member_statuses` table.
 *
 * App\Models\MissionMemberStatus is created/updated by
 * App\Services\Missions\TeamLeadOperationsService and is the target of the
 * MissionTaskSegmentAssignment hasMany relation, but no migration ever created
 * its table. Columns derived from $fillable / $casts.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mission_member_statuses')) {
            Schema::create('mission_member_statuses', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('mission_id')->nullable()->index();
                $table->unsignedBigInteger('mission_task_segment_id')->nullable()->index();
                $table->unsignedBigInteger('segment_assignment_id')->nullable()->index();
                $table->unsignedBigInteger('field_team_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('status')->nullable()->index();
                $table->string('readiness_status')->nullable();
                $table->integer('progress_percent')->nullable();
                $table->integer('minutes_spent')->nullable();
                $table->boolean('is_blocked')->default(false);
                $table->text('blocking_reason')->nullable();
                $table->timestamp('last_reported_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_member_statuses');
    }
};
