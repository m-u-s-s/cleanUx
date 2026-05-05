<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('field_team_load_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('field_team_id')->constrained('field_teams')->cascadeOnDelete();
            $table->date('snapshot_date');
            $table->unsignedInteger('active_missions_count')->default(0);
            $table->unsignedInteger('planned_segments_count')->default(0);
            $table->unsignedInteger('planned_minutes')->default(0);
            $table->unsignedInteger('assigned_members_count')->default(0);
            $table->unsignedInteger('capacity_minutes')->default(0);
            $table->decimal('utilization_percent', 6, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['field_team_id', 'snapshot_date'], 'field_team_load_unique');
        });

        Schema::create('service_partner_load_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_partner_id')->constrained('service_partners')->cascadeOnDelete();
            $table->date('snapshot_date');
            $table->unsignedInteger('active_missions_count')->default(0);
            $table->unsignedInteger('planned_segments_count')->default(0);
            $table->unsignedInteger('planned_minutes')->default(0);
            $table->unsignedInteger('daily_capacity')->default(0);
            $table->decimal('utilization_percent', 6, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['service_partner_id', 'snapshot_date'], 'service_partner_load_unique');
        });

        Schema::table('enterprise_work_orders', function (Blueprint $table) {
            $table->boolean('auto_generate_missions')->default(true)->after('metadata');
            $table->string('generation_mode')->default('batch')->after('auto_generate_missions');
            $table->string('generation_status')->default('pending')->after('generation_mode');
            $table->foreignId('generated_batch_id')->nullable()->after('generation_status')->constrained('mission_batches')->nullOnDelete();
            $table->dateTime('generation_started_at')->nullable()->after('generated_batch_id');
            $table->dateTime('generation_completed_at')->nullable()->after('generation_started_at');
            $table->unsignedInteger('generated_missions_count')->default(0)->after('generation_completed_at');
        });

        Schema::table('mission_batches', function (Blueprint $table) {
            $table->boolean('auto_generate_missions')->default(true)->after('estimated_total_cost');
            $table->string('generation_status')->default('pending')->after('auto_generate_missions');
            $table->unsignedInteger('generated_missions_count')->default(0)->after('generation_status');
            $table->unsignedInteger('total_segment_minutes')->default(0)->after('generated_missions_count');
        });

        Schema::table('mission_task_segments', function (Blueprint $table) {
            $table->foreignId('service_catalog_id')->nullable()->after('assigned_user_id')->constrained('service_catalogs')->nullOnDelete();
            $table->foreignId('service_zone_id')->nullable()->after('service_catalog_id')->constrained('service_zones')->nullOnDelete();
            $table->boolean('auto_generate_mission')->default(true)->after('sequence');
            $table->string('generation_status')->default('pending')->after('auto_generate_mission');
        });

        Schema::table('missions', function (Blueprint $table) {
            $table->foreignId('mission_task_segment_id')->nullable()->after('mission_batch_id')->constrained('mission_task_segments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mission_task_segment_id');
        });

        Schema::table('mission_task_segments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_zone_id');
            $table->dropConstrainedForeignId('service_catalog_id');
            $table->dropColumn(['auto_generate_mission', 'generation_status']);
        });

        Schema::table('mission_batches', function (Blueprint $table) {
            $table->dropColumn(['auto_generate_missions', 'generation_status', 'generated_missions_count', 'total_segment_minutes']);
        });

        Schema::table('enterprise_work_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('generated_batch_id');
            $table->dropColumn([
                'auto_generate_missions',
                'generation_mode',
                'generation_status',
                'generation_started_at',
                'generation_completed_at',
                'generated_missions_count',
            ]);
        });

        Schema::dropIfExists('service_partner_load_snapshots');
        Schema::dropIfExists('field_team_load_snapshots');
    }
};
