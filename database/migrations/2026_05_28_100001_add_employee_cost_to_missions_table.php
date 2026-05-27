<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the employee_cost column to the missions table.
 *
 * This column is referenced in MissionProfitService and Mission::$fillable
 * but was absent from the original create_mission_tables migration.
 * Adding it defensively here enables the MissionEndCodeFlowTest to run
 * in SQLite test environments without the sqlite_guard skip.
 *
 * Also adds travel_duration_minutes which MissionProfitService writes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('missions')) {
            return;
        }

        Schema::table('missions', function (Blueprint $table) {
            if (! Schema::hasColumn('missions', 'employee_cost')) {
                $table->decimal('employee_cost', 10, 2)->nullable()->after('margin');
            }
            if (! Schema::hasColumn('missions', 'travel_duration_minutes')) {
                $table->unsignedInteger('travel_duration_minutes')->nullable()->after('actual_duration_minutes');
            }
            if (! Schema::hasColumn('missions', 'quality_summary')) {
                $table->json('quality_summary')->nullable();
            }
            if (! Schema::hasColumn('missions', 'client_final_validated_at')) {
                $table->timestamp('client_final_validated_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('missions')) {
            return;
        }

        Schema::table('missions', function (Blueprint $table) {
            foreach (['employee_cost', 'travel_duration_minutes', 'quality_summary', 'client_final_validated_at'] as $col) {
                if (Schema::hasColumn('missions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
