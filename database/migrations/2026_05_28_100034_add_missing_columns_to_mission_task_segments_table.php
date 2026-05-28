<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema-drift repair for App\Models\MissionTaskSegment.
 *
 * The model declares mission_batch_day_id, service_partner_id, zone_label,
 * service_date, estimated_minutes, crew_size, sequence and notes as
 * fillable/cast with no matching column on mission_task_segments. Added
 * defensively with types matching the casts.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mission_task_segments')) {
            return;
        }

        Schema::table('mission_task_segments', function (Blueprint $table) {
            if (! Schema::hasColumn('mission_task_segments', 'mission_batch_day_id')) {
                $table->unsignedBigInteger('mission_batch_day_id')->nullable()->index();
            }
            if (! Schema::hasColumn('mission_task_segments', 'service_partner_id')) {
                $table->unsignedBigInteger('service_partner_id')->nullable()->index();
            }
            if (! Schema::hasColumn('mission_task_segments', 'zone_label')) {
                $table->string('zone_label')->nullable();
            }
            if (! Schema::hasColumn('mission_task_segments', 'service_date')) {
                $table->date('service_date')->nullable();
            }
            if (! Schema::hasColumn('mission_task_segments', 'estimated_minutes')) {
                $table->unsignedInteger('estimated_minutes')->nullable();
            }
            if (! Schema::hasColumn('mission_task_segments', 'crew_size')) {
                $table->unsignedInteger('crew_size')->nullable();
            }
            if (! Schema::hasColumn('mission_task_segments', 'sequence')) {
                $table->unsignedInteger('sequence')->nullable();
            }
            if (! Schema::hasColumn('mission_task_segments', 'notes')) {
                $table->text('notes')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('mission_task_segments')) {
            return;
        }

        Schema::table('mission_task_segments', function (Blueprint $table) {
            foreach ([
                'mission_batch_day_id',
                'service_partner_id',
                'zone_label',
                'service_date',
                'estimated_minutes',
                'crew_size',
                'sequence',
                'notes',
            ] as $col) {
                if (Schema::hasColumn('mission_task_segments', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
