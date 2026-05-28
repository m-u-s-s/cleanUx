<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema-drift repair for App\Models\MissionReinforcementRequest.
 *
 * The model declares batch/day/segment/team/partner/resolver FKs plus
 * requested_members, requested_minutes and resolution_notes as fillable/cast
 * with no matching column on mission_reinforcement_requests. Added defensively.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mission_reinforcement_requests')) {
            return;
        }

        Schema::table('mission_reinforcement_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('mission_reinforcement_requests', 'mission_batch_id')) {
                $table->unsignedBigInteger('mission_batch_id')->nullable()->index();
            }
            if (! Schema::hasColumn('mission_reinforcement_requests', 'mission_batch_day_id')) {
                $table->unsignedBigInteger('mission_batch_day_id')->nullable()->index();
            }
            if (! Schema::hasColumn('mission_reinforcement_requests', 'mission_task_segment_id')) {
                $table->unsignedBigInteger('mission_task_segment_id')->nullable()->index();
            }
            if (! Schema::hasColumn('mission_reinforcement_requests', 'field_team_id')) {
                $table->unsignedBigInteger('field_team_id')->nullable()->index();
            }
            if (! Schema::hasColumn('mission_reinforcement_requests', 'service_partner_id')) {
                $table->unsignedBigInteger('service_partner_id')->nullable()->index();
            }
            if (! Schema::hasColumn('mission_reinforcement_requests', 'resolved_by_user_id')) {
                $table->unsignedBigInteger('resolved_by_user_id')->nullable()->index();
            }
            if (! Schema::hasColumn('mission_reinforcement_requests', 'requested_members')) {
                $table->unsignedInteger('requested_members')->nullable();
            }
            if (! Schema::hasColumn('mission_reinforcement_requests', 'requested_minutes')) {
                $table->unsignedInteger('requested_minutes')->nullable();
            }
            if (! Schema::hasColumn('mission_reinforcement_requests', 'resolution_notes')) {
                $table->text('resolution_notes')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('mission_reinforcement_requests')) {
            return;
        }

        Schema::table('mission_reinforcement_requests', function (Blueprint $table) {
            foreach ([
                'mission_batch_id',
                'mission_batch_day_id',
                'mission_task_segment_id',
                'field_team_id',
                'service_partner_id',
                'resolved_by_user_id',
                'requested_members',
                'requested_minutes',
                'resolution_notes',
            ] as $col) {
                if (Schema::hasColumn('mission_reinforcement_requests', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
