<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Schema-drift repair: create the `mission_task_segment_assignments` table. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mission_task_segment_assignments')) {
            Schema::create('mission_task_segment_assignments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('mission_task_segment_id')->nullable()->index();
                $table->unsignedBigInteger('mission_id')->nullable()->index();
                $table->unsignedBigInteger('field_team_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('assigned_by_user_id')->nullable()->index();
                $table->string('assignment_role')->nullable();
                $table->string('status')->nullable()->index();
                $table->integer('planned_minutes')->nullable();
                $table->integer('actual_minutes')->nullable();
                $table->integer('sequence_order')->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_task_segment_assignments');
    }
};
