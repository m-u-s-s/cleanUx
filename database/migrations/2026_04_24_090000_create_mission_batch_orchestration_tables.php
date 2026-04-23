<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        if (! Schema::hasTable('mission_batches')) {
            Schema::create('mission_batches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_account_id')->nullable()->constrained('organization_accounts')->nullOnDelete();
                $table->foreignId('organization_site_id')->nullable()->constrained('organization_sites')->nullOnDelete();
                $table->foreignId('enterprise_work_order_id')->nullable()->constrained('enterprise_work_orders')->nullOnDelete();
                $table->foreignId('field_team_id')->nullable()->constrained('field_teams')->nullOnDelete();
                $table->foreignId('service_partner_id')->nullable()->constrained('service_partners')->nullOnDelete();
                $table->string('name');
                $table->string('reference')->unique();
                $table->string('status')->default('draft');
                $table->string('batch_type')->default('multi_day_site');
                $table->date('starts_on');
                $table->date('ends_on');
                $table->time('default_start_time')->nullable();
                $table->time('default_end_time')->nullable();
                $table->unsignedInteger('estimated_total_minutes')->default(0);
                $table->decimal('estimated_total_cost', 12, 2)->default(0);
                $table->json('metadata')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        } else {
            if (! Schema::hasColumn('mission_batches', 'field_team_id')) {
                Schema::table('mission_batches', function (Blueprint $table) {
                    $table->foreignId('field_team_id')->nullable()->after('enterprise_work_order_id')->constrained('field_teams')->nullOnDelete();
                });
            }

            if (! Schema::hasColumn('mission_batches', 'service_partner_id')) {
                Schema::table('mission_batches', function (Blueprint $table) {
                    $table->foreignId('service_partner_id')->nullable()->after('field_team_id')->constrained('service_partners')->nullOnDelete();
                });
            }

            if (! Schema::hasColumn('mission_batches', 'name')) {
                Schema::table('mission_batches', function (Blueprint $table) {
                    $table->string('name')->nullable()->after('service_partner_id');
                });
            }

            if (! Schema::hasColumn('mission_batches', 'reference')) {
                Schema::table('mission_batches', function (Blueprint $table) {
                    $table->string('reference')->nullable()->after('name');
                });

                Schema::table('mission_batches', function (Blueprint $table) {
                    $table->unique('reference');
                });
            }

            if (! Schema::hasColumn('mission_batches', 'batch_type')) {
                Schema::table('mission_batches', function (Blueprint $table) {
                    $table->string('batch_type')->default('multi_day_site')->after('status');
                });
            }

            if (! Schema::hasColumn('mission_batches', 'starts_on')) {
                Schema::table('mission_batches', function (Blueprint $table) {
                    $table->date('starts_on')->nullable()->after('batch_type');
                });
            }

            if (! Schema::hasColumn('mission_batches', 'ends_on')) {
                Schema::table('mission_batches', function (Blueprint $table) {
                    $table->date('ends_on')->nullable()->after('starts_on');
                });
            }

            if (! Schema::hasColumn('mission_batches', 'default_start_time')) {
                Schema::table('mission_batches', function (Blueprint $table) {
                    $table->time('default_start_time')->nullable()->after('ends_on');
                });
            }

            if (! Schema::hasColumn('mission_batches', 'default_end_time')) {
                Schema::table('mission_batches', function (Blueprint $table) {
                    $table->time('default_end_time')->nullable()->after('default_start_time');
                });
            }

            if (! Schema::hasColumn('mission_batches', 'estimated_total_minutes')) {
                Schema::table('mission_batches', function (Blueprint $table) {
                    $table->unsignedInteger('estimated_total_minutes')->default(0)->after('default_end_time');
                });
            }

            if (! Schema::hasColumn('mission_batches', 'estimated_total_cost')) {
                Schema::table('mission_batches', function (Blueprint $table) {
                    $table->decimal('estimated_total_cost', 12, 2)->default(0)->after('estimated_total_minutes');
                });
            }
        }

        Schema::create('mission_batch_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mission_batch_id')->constrained('mission_batches')->cascadeOnDelete();
            $table->foreignId('field_team_id')->nullable()->constrained('field_teams')->nullOnDelete();
            $table->foreignId('service_partner_id')->nullable()->constrained('service_partners')->nullOnDelete();
            $table->string('status')->default('planned');
            $table->date('service_date');
            $table->dateTime('planned_start_at')->nullable();
            $table->dateTime('planned_end_at')->nullable();
            $table->unsignedInteger('target_mission_count')->default(1);
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['mission_batch_id', 'service_date']);
        });

        Schema::create('mission_task_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mission_batch_id')->constrained('mission_batches')->cascadeOnDelete();
            $table->foreignId('mission_batch_day_id')->nullable()->constrained('mission_batch_days')->nullOnDelete();
            $table->foreignId('mission_id')->nullable()->constrained('missions')->nullOnDelete();
            $table->foreignId('field_team_id')->nullable()->constrained('field_teams')->nullOnDelete();
            $table->foreignId('service_partner_id')->nullable()->constrained('service_partners')->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('planned');
            $table->string('segment_type')->default('execution_zone');
            $table->string('title');
            $table->string('zone_label')->nullable();
            $table->date('service_date')->nullable();
            $table->dateTime('planned_start_at')->nullable();
            $table->dateTime('planned_end_at')->nullable();
            $table->unsignedInteger('estimated_minutes')->default(0);
            $table->unsignedInteger('crew_size')->default(1);
            $table->unsignedInteger('sequence')->default(1);
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_task_segments');
        Schema::dropIfExists('mission_batch_days');
        Schema::dropIfExists('mission_batches');
    }
};
