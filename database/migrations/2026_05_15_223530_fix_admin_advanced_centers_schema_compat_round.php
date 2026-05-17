<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('field_teams')) {
            Schema::table('field_teams', function (Blueprint $table) {
                if (! Schema::hasColumn('field_teams', 'max_concurrent_missions')) {
                    $table->unsignedInteger('max_concurrent_missions')->default(3)->after('is_internal');
                }

                if (! Schema::hasColumn('field_teams', 'color')) {
                    $table->string('color')->nullable()->after('status');
                }

                if (! Schema::hasColumn('field_teams', 'metadata')) {
                    $table->json('metadata')->nullable()->after('color');
                }
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (! Schema::hasColumn('users', 'access_scope')) {
                    $table->string('access_scope')->default('global')->index()->after('role');
                }

                if (! Schema::hasColumn('users', 'primary_service_zone_id')) {
                    $table->unsignedBigInteger('primary_service_zone_id')->nullable()->index()->after('access_scope');
                }

                if (! Schema::hasColumn('users', 'permissions')) {
                    $table->json('permissions')->nullable()->after('primary_service_zone_id');
                }
            });
        }

        if (! Schema::hasTable('field_team_load_snapshots')) {
            Schema::create('field_team_load_snapshots', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('field_team_id')->nullable()->index();
                $table->date('snapshot_date')->index();
                $table->unsignedInteger('active_missions_count')->default(0);
                $table->unsignedInteger('planned_missions_count')->default(0);
                $table->unsignedInteger('available_members_count')->default(0);
                $table->unsignedInteger('max_concurrent_missions')->default(0);
                $table->decimal('utilization_percent', 5, 2)->default(0);
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['field_team_id', 'snapshot_date'], 'ft_load_snapshots_team_date_unique');
            });
        }

        if (! Schema::hasTable('market_launch_readiness')) {
            Schema::create('market_launch_readiness', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('country_id')->nullable()->index();
                $table->string('status')->default('draft')->index();
                $table->unsignedInteger('readiness_score')->default(0);
                $table->json('checks')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();

                $table->unique(['country_id'], 'market_readiness_country_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('field_team_load_snapshots');
        Schema::dropIfExists('market_launch_readiness');

        if (Schema::hasTable('field_teams')) {
            Schema::table('field_teams', function (Blueprint $table) {
                foreach (['max_concurrent_missions', 'color', 'metadata'] as $column) {
                    if (Schema::hasColumn('field_teams', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                foreach (['access_scope', 'primary_service_zone_id', 'permissions'] as $column) {
                    if (Schema::hasColumn('users', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};