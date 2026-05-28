<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aligns the mission_reports table with the MissionReport model and
 * MissionQualityService::generateOrRefreshReport().
 *
 * mission_reports was originally created (2026_05_04) with a PDF-oriented
 * schema (type/disk/path/metadata) that nothing writes — the PDF lives in
 * storage with its path on missions.report_path. The model + quality service
 * instead persist a quality report (scores, counts, payload), so completeMission()
 * 500s with "no such column". Columns added defensively to match the model.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mission_reports')) {
            return;
        }

        Schema::table('mission_reports', function (Blueprint $table) {
            if (! Schema::hasColumn('mission_reports', 'generated_by_user_id')) {
                $table->unsignedBigInteger('generated_by_user_id')->nullable()->index();
            }
            if (! Schema::hasColumn('mission_reports', 'report_number')) {
                $table->string('report_number')->nullable()->index();
            }
            if (! Schema::hasColumn('mission_reports', 'summary')) {
                $table->text('summary')->nullable();
            }
            if (! Schema::hasColumn('mission_reports', 'checklist_completion_rate')) {
                $table->unsignedSmallInteger('checklist_completion_rate')->nullable();
            }
            if (! Schema::hasColumn('mission_reports', 'before_photos_count')) {
                $table->unsignedInteger('before_photos_count')->nullable();
            }
            if (! Schema::hasColumn('mission_reports', 'after_photos_count')) {
                $table->unsignedInteger('after_photos_count')->nullable();
            }
            if (! Schema::hasColumn('mission_reports', 'incident_count')) {
                $table->unsignedInteger('incident_count')->nullable();
            }
            if (! Schema::hasColumn('mission_reports', 'client_validation')) {
                $table->string('client_validation')->nullable();
            }
            if (! Schema::hasColumn('mission_reports', 'quality_score')) {
                $table->unsignedSmallInteger('quality_score')->nullable();
            }
            if (! Schema::hasColumn('mission_reports', 'report_payload')) {
                $table->json('report_payload')->nullable();
            }
            if (! Schema::hasColumn('mission_reports', 'pdf_path')) {
                $table->string('pdf_path')->nullable();
            }
        });

        // Legacy PDF-schema column left NOT NULL with no default; the quality
        // report writer never sets it, so relax it to nullable.
        if (Schema::hasColumn('mission_reports', 'path')) {
            Schema::table('mission_reports', function (Blueprint $table) {
                $table->string('path')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('mission_reports')) {
            return;
        }

        Schema::table('mission_reports', function (Blueprint $table) {
            foreach ([
                'generated_by_user_id', 'report_number', 'summary',
                'checklist_completion_rate', 'before_photos_count', 'after_photos_count',
                'incident_count', 'client_validation', 'quality_score', 'report_payload', 'pdf_path',
            ] as $col) {
                if (Schema::hasColumn('mission_reports', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
