<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
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

    public function down(): void
    {
        Schema::dropIfExists('mission_reinforcement_requests');
        Schema::dropIfExists('mission_task_segments');
        Schema::dropIfExists('mission_batches');
    }
};
