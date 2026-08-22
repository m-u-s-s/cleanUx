<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->corpsInitial();
        $this->fusion20260528100029AddMissingColumnsToMissionBatchesTable();
        $this->fusion20260528100033AddMissingColumnsToMissionReinforcementRequest();
        $this->fusion20260528100034AddMissingColumnsToMissionTaskSegmentsTable();
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_reinforcement_requests');
        Schema::dropIfExists('mission_task_segments');
        Schema::dropIfExists('mission_batches');
    }

    /** Le corps d origine, extrait pour que son `return` ne quitte que lui. */
    private function corpsInitial(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Pivot zones <-> codes postaux
        |--------------------------------------------------------------------------
        */
        if (! Schema::hasTable('service_zone_postal_code')) {
            Schema::create('service_zone_postal_code', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('service_zone_id');
                $table->unsignedBigInteger('postal_code_id');
                $table->boolean('is_primary')->default(false);
                $table->unsignedInteger('priority')->default(0);
                $table->timestamps();

                $table->unique(['service_zone_id', 'postal_code_id'], 'szpc_zone_postal_unique');
            });
        } else {
            Schema::table('service_zone_postal_code', function (Blueprint $table) {
                if (! Schema::hasColumn('service_zone_postal_code', 'is_primary')) {
                    $table->boolean('is_primary')->default(false);
                }

                if (! Schema::hasColumn('service_zone_postal_code', 'priority')) {
                    $table->unsignedInteger('priority')->default(0);
                }
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Règles zone/service
        |--------------------------------------------------------------------------
        */
        if (Schema::hasTable('zone_service_rules')) {
            Schema::table('zone_service_rules', function (Blueprint $table) {
                if (! Schema::hasColumn('zone_service_rules', 'base_price_override')) {
                    $table->decimal('base_price_override', 10, 2)->nullable();
                }

                if (! Schema::hasColumn('zone_service_rules', 'price_multiplier')) {
                    $table->decimal('price_multiplier', 8, 2)->default(1);
                }

                if (! Schema::hasColumn('zone_service_rules', 'minimum_notice_hours')) {
                    $table->unsignedInteger('minimum_notice_hours')->nullable();
                }

                if (! Schema::hasColumn('zone_service_rules', 'maximum_daily_capacity')) {
                    $table->unsignedInteger('maximum_daily_capacity')->nullable();
                }

                if (! Schema::hasColumn('zone_service_rules', 'settings')) {
                    $table->json('settings')->nullable();
                }
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Bookings : colonnes legacy/compatibilité utilisées par les factories/tests
        |--------------------------------------------------------------------------
        */
        if (Schema::hasTable('bookings')) {
            Schema::table('bookings', function (Blueprint $table) {
                if (! Schema::hasColumn('bookings', 'organization_account_id')) {
                    $table->unsignedBigInteger('organization_account_id')->nullable();
                }

                if (! Schema::hasColumn('bookings', 'organization_site_id')) {
                    $table->unsignedBigInteger('organization_site_id')->nullable();
                }

                if (! Schema::hasColumn('bookings', 'booking_channel')) {
                    $table->string('booking_channel')->nullable();
                }
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Segments opérationnels mission
        |--------------------------------------------------------------------------
        */
        if (! Schema::hasTable('mission_task_segments')) {
            Schema::create('mission_task_segments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('mission_id')->nullable();
                $table->unsignedBigInteger('assigned_to_user_id')->nullable();
                $table->string('title')->nullable();
                $table->string('status')->default('pending');
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['mission_id', 'status']);
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Demandes de renfort mission
        |--------------------------------------------------------------------------
        */
        if (! Schema::hasTable('mission_reinforcement_requests')) {
            Schema::create('mission_reinforcement_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('mission_id')->nullable();
                $table->unsignedBigInteger('requested_by_user_id')->nullable();
                $table->unsignedBigInteger('assigned_to_user_id')->nullable();
                $table->unsignedBigInteger('provider_team_id')->nullable();

                $table->string('status')->default('pending');
                $table->string('priority')->default('normal');
                $table->unsignedInteger('required_people')->default(1);

                $table->text('reason')->nullable();
                $table->text('notes')->nullable();

                $table->timestamp('needed_at')->nullable();
                $table->timestamp('resolved_at')->nullable();

                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['mission_id', 'status']);
                $table->index(['status', 'priority']);
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Mission batches : sécurité si le centre team lead les lit
        |--------------------------------------------------------------------------
        */
        if (! Schema::hasTable('mission_batches')) {
            Schema::create('mission_batches', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('field_team_id')->nullable();
                $table->unsignedBigInteger('team_lead_user_id')->nullable();

                $table->string('name')->nullable();
                $table->string('status')->default('planned');
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();

                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['team_lead_user_id', 'status']);
                $table->index(['field_team_id', 'status']);
            });
        }
    }

    /** Fusionne depuis 2026_05_28_100029_add_missing_columns_to_mission_batches_table */
    private function fusion20260528100029AddMissingColumnsToMissionBatchesTable(): void
    {
        if (! Schema::hasTable('mission_batches')) {
            return;
        }

        Schema::table('mission_batches', function (Blueprint $table) {
            if (! Schema::hasColumn('mission_batches', 'organization_account_id')) {
                $table->unsignedBigInteger('organization_account_id')->nullable()->index();
            }
            if (! Schema::hasColumn('mission_batches', 'organization_site_id')) {
                $table->unsignedBigInteger('organization_site_id')->nullable()->index();
            }
            if (! Schema::hasColumn('mission_batches', 'enterprise_work_order_id')) {
                $table->unsignedBigInteger('enterprise_work_order_id')->nullable()->index();
            }
            if (! Schema::hasColumn('mission_batches', 'service_partner_id')) {
                $table->unsignedBigInteger('service_partner_id')->nullable()->index();
            }
            if (! Schema::hasColumn('mission_batches', 'reference')) {
                $table->string('reference')->nullable();
            }
            if (! Schema::hasColumn('mission_batches', 'batch_type')) {
                $table->string('batch_type')->nullable();
            }
            if (! Schema::hasColumn('mission_batches', 'starts_on')) {
                $table->date('starts_on')->nullable();
            }
            if (! Schema::hasColumn('mission_batches', 'ends_on')) {
                $table->date('ends_on')->nullable();
            }
            if (! Schema::hasColumn('mission_batches', 'default_start_time')) {
                $table->string('default_start_time')->nullable();
            }
            if (! Schema::hasColumn('mission_batches', 'default_end_time')) {
                $table->string('default_end_time')->nullable();
            }
            if (! Schema::hasColumn('mission_batches', 'estimated_total_minutes')) {
                $table->unsignedInteger('estimated_total_minutes')->nullable();
            }
            if (! Schema::hasColumn('mission_batches', 'estimated_total_cost')) {
                $table->decimal('estimated_total_cost', 10, 2)->nullable();
            }
            if (! Schema::hasColumn('mission_batches', 'notes')) {
                $table->text('notes')->nullable();
            }
        });
    }

    /** Fusionne depuis 2026_05_28_100033_add_missing_columns_to_mission_reinforcement_requests_table */
    private function fusion20260528100033AddMissingColumnsToMissionReinforcementRequest(): void
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

    /** Fusionne depuis 2026_05_28_100034_add_missing_columns_to_mission_task_segments_table */
    private function fusion20260528100034AddMissingColumnsToMissionTaskSegmentsTable(): void
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
};
