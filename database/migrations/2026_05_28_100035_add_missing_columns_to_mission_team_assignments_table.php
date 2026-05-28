<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema-drift repair for App\Models\MissionTeamAssignment.
 *
 * The model declares lead_assignment_id (FK), started_at (datetime) and
 * instructions_snapshot (array cast -> json) as fillable/cast with no matching
 * column on mission_team_assignments. Added defensively.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mission_team_assignments')) {
            return;
        }

        Schema::table('mission_team_assignments', function (Blueprint $table) {
            if (! Schema::hasColumn('mission_team_assignments', 'lead_assignment_id')) {
                $table->unsignedBigInteger('lead_assignment_id')->nullable()->index();
            }
            if (! Schema::hasColumn('mission_team_assignments', 'started_at')) {
                $table->timestamp('started_at')->nullable();
            }
            if (! Schema::hasColumn('mission_team_assignments', 'instructions_snapshot')) {
                $table->json('instructions_snapshot')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('mission_team_assignments')) {
            return;
        }

        Schema::table('mission_team_assignments', function (Blueprint $table) {
            foreach ([
                'lead_assignment_id',
                'started_at',
                'instructions_snapshot',
            ] as $col) {
                if (Schema::hasColumn('mission_team_assignments', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
