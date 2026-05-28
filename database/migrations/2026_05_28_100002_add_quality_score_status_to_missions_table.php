<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds quality_score and quality_status to the missions table.
 *
 * MissionQualityService::refreshMissionQuality() writes quality_score,
 * quality_status and quality_summary on completion. The 100001 migration
 * added quality_summary but missed the other two, so completeMission()
 * 500s on SQLite/test schemas. Added defensively to match that pattern.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('missions')) {
            return;
        }

        Schema::table('missions', function (Blueprint $table) {
            if (! Schema::hasColumn('missions', 'quality_score')) {
                $table->unsignedSmallInteger('quality_score')->nullable();
            }
            if (! Schema::hasColumn('missions', 'quality_status')) {
                $table->string('quality_status')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('missions')) {
            return;
        }

        Schema::table('missions', function (Blueprint $table) {
            foreach (['quality_score', 'quality_status'] as $col) {
                if (Schema::hasColumn('missions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
