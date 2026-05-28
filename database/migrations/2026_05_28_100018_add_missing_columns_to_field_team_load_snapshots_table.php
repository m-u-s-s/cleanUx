<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema-drift repair for App\Models\FieldTeamLoadSnapshot.
 *
 * The model declares these capacity/load count + minute fields (cast to
 * integer) as fillable but the field_team_load_snapshots table is missing
 * the columns. Added defensively as nullable unsigned integers.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('field_team_load_snapshots')) {
            return;
        }

        Schema::table('field_team_load_snapshots', function (Blueprint $table) {
            if (! Schema::hasColumn('field_team_load_snapshots', 'planned_segments_count')) {
                $table->unsignedInteger('planned_segments_count')->nullable();
            }
            if (! Schema::hasColumn('field_team_load_snapshots', 'planned_minutes')) {
                $table->unsignedInteger('planned_minutes')->nullable();
            }
            if (! Schema::hasColumn('field_team_load_snapshots', 'assigned_members_count')) {
                $table->unsignedInteger('assigned_members_count')->nullable();
            }
            if (! Schema::hasColumn('field_team_load_snapshots', 'capacity_minutes')) {
                $table->unsignedInteger('capacity_minutes')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('field_team_load_snapshots')) {
            return;
        }

        Schema::table('field_team_load_snapshots', function (Blueprint $table) {
            foreach ([
                'planned_segments_count', 'planned_minutes',
                'assigned_members_count', 'capacity_minutes',
            ] as $col) {
                if (Schema::hasColumn('field_team_load_snapshots', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
