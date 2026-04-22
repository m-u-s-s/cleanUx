<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mission_task_segment_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mission_task_segment_id')->constrained('mission_task_segments')->cascadeOnDelete();
            $table->foreignId('mission_id')->nullable()->constrained('missions')->nullOnDelete();
            $table->foreignId('field_team_id')->nullable()->constrained('field_teams')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('assignment_role')->default('operator');
            $table->string('status')->default('assigned');
            $table->unsignedInteger('planned_minutes')->nullable();
            $table->unsignedInteger('actual_minutes')->nullable();
            $table->unsignedInteger('sequence_order')->default(1);
            $table->text('notes')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['mission_task_segment_id', 'user_id'], 'segment_assignment_unique_user');
        });

        Schema::create('mission_member_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mission_id')->nullable()->constrained('missions')->nullOnDelete();
            $table->foreignId('mission_task_segment_id')->constrained('mission_task_segments')->cascadeOnDelete();
            $table->foreignId('segment_assignment_id')->constrained('mission_task_segment_assignments')->cascadeOnDelete();
            $table->foreignId('field_team_id')->nullable()->constrained('field_teams')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('assigned');
            $table->string('readiness_status')->default('pending');
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->unsignedInteger('minutes_spent')->default(0);
            $table->boolean('is_blocked')->default(false);
            $table->text('blocking_reason')->nullable();
            $table->timestamp('last_reported_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['segment_assignment_id', 'user_id'], 'member_status_unique_assignment_user');
        });

        Schema::create('mission_reinforcement_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mission_id')->nullable()->constrained('missions')->nullOnDelete();
            $table->foreignId('mission_batch_id')->nullable()->constrained('mission_batches')->nullOnDelete();
            $table->foreignId('mission_batch_day_id')->nullable()->constrained('mission_batch_days')->nullOnDelete();
            $table->foreignId('mission_task_segment_id')->nullable()->constrained('mission_task_segments')->nullOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('field_team_id')->nullable()->constrained('field_teams')->nullOnDelete();
            $table->foreignId('service_partner_id')->nullable()->constrained('service_partners')->nullOnDelete();
            $table->string('status')->default('open');
            $table->string('priority')->default('haute');
            $table->unsignedTinyInteger('requested_members')->default(1);
            $table->unsignedInteger('requested_minutes')->default(60);
            $table->text('reason');
            $table->text('resolution_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_reinforcement_requests');
        Schema::dropIfExists('mission_member_statuses');
        Schema::dropIfExists('mission_task_segment_assignments');
    }
};
