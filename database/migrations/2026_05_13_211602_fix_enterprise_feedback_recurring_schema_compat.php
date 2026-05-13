<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->fixEnterpriseWorkOrders();
        $this->fixFeedback();
        $this->fixOrganizationSites();
        $this->fixFieldTeams();
        $this->fixMissionTrackingSessions();
        $this->fixBookings();
        $this->fixServiceCatalogs();
    }

    private function fixEnterpriseWorkOrders(): void
    {
        if (! Schema::hasTable('enterprise_work_orders')) {
            return;
        }

        Schema::table('enterprise_work_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('enterprise_work_orders', 'service_zone_id')) {
                $table->unsignedBigInteger('service_zone_id')->nullable();
            }

            if (! Schema::hasColumn('enterprise_work_orders', 'service_catalog_id')) {
                $table->unsignedBigInteger('service_catalog_id')->nullable();
            }

            if (! Schema::hasColumn('enterprise_work_orders', 'organization_contract_id')) {
                $table->unsignedBigInteger('organization_contract_id')->nullable();
            }

            if (! Schema::hasColumn('enterprise_work_orders', 'requested_by_user_id')) {
                $table->unsignedBigInteger('requested_by_user_id')->nullable();
            }

            if (! Schema::hasColumn('enterprise_work_orders', 'assigned_field_team_id')) {
                $table->unsignedBigInteger('assigned_field_team_id')->nullable();
            }

            if (! Schema::hasColumn('enterprise_work_orders', 'assigned_service_partner_id')) {
                $table->unsignedBigInteger('assigned_service_partner_id')->nullable();
            }

            if (! Schema::hasColumn('enterprise_work_orders', 'approval_status')) {
                $table->string('approval_status')->default('pending');
            }

            if (! Schema::hasColumn('enterprise_work_orders', 'work_type')) {
                $table->string('work_type')->nullable();
            }

            if (! Schema::hasColumn('enterprise_work_orders', 'requested_start_at')) {
                $table->timestamp('requested_start_at')->nullable();
            }

            if (! Schema::hasColumn('enterprise_work_orders', 'requested_end_at')) {
                $table->timestamp('requested_end_at')->nullable();
            }

            if (! Schema::hasColumn('enterprise_work_orders', 'scheduled_start_at')) {
                $table->timestamp('scheduled_start_at')->nullable();
            }

            if (! Schema::hasColumn('enterprise_work_orders', 'scheduled_end_at')) {
                $table->timestamp('scheduled_end_at')->nullable();
            }

            if (! Schema::hasColumn('enterprise_work_orders', 'purchase_order_number')) {
                $table->string('purchase_order_number')->nullable();
            }

            if (! Schema::hasColumn('enterprise_work_orders', 'cost_center')) {
                $table->string('cost_center')->nullable();
            }

            if (! Schema::hasColumn('enterprise_work_orders', 'budget_amount')) {
                $table->decimal('budget_amount', 10, 2)->nullable();
            }

            if (! Schema::hasColumn('enterprise_work_orders', 'instructions')) {
                $table->text('instructions')->nullable();
            }
        });
    }

    private function fixFeedback(): void
    {
        if (! Schema::hasTable('feedback')) {
            return;
        }

        Schema::table('feedback', function (Blueprint $table) {
            if (! Schema::hasColumn('feedback', 'reponse_admin')) {
                $table->text('reponse_admin')->nullable();
            }

            if (! Schema::hasColumn('feedback', 'answered_by')) {
                $table->unsignedBigInteger('answered_by')->nullable();
            }

            if (! Schema::hasColumn('feedback', 'answered_at')) {
                $table->timestamp('answered_at')->nullable();
            }

            if (! Schema::hasColumn('feedback', 'feedback')) {
                $table->text('feedback')->nullable();
            }
        });
    }

    private function fixOrganizationSites(): void
    {
        if (! Schema::hasTable('organization_sites')) {
            return;
        }

        Schema::table('organization_sites', function (Blueprint $table) {
            if (! Schema::hasColumn('organization_sites', 'client_user_id')) {
                $table->unsignedBigInteger('client_user_id')->nullable();
            }

            if (! Schema::hasColumn('organization_sites', 'site_code')) {
                $table->string('site_code')->nullable();
            }

            if (! Schema::hasColumn('organization_sites', 'email')) {
                $table->string('email')->nullable();
            }

            if (! Schema::hasColumn('organization_sites', 'phone')) {
                $table->string('phone')->nullable();
            }

            if (! Schema::hasColumn('organization_sites', 'address_line_1')) {
                $table->string('address_line_1')->nullable();
            }

            if (! Schema::hasColumn('organization_sites', 'address_line_2')) {
                $table->string('address_line_2')->nullable();
            }

            if (! Schema::hasColumn('organization_sites', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable();
            }

            if (! Schema::hasColumn('organization_sites', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable();
            }

            if (! Schema::hasColumn('organization_sites', 'is_primary')) {
                $table->boolean('is_primary')->default(false);
            }

            if (! Schema::hasColumn('organization_sites', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
        });
    }

    private function fixFieldTeams(): void
    {
        if (! Schema::hasTable('field_teams')) {
            return;
        }

        Schema::table('field_teams', function (Blueprint $table) {
            if (! Schema::hasColumn('field_teams', 'country_id')) {
                $table->unsignedBigInteger('country_id')->nullable();
            }

            if (! Schema::hasColumn('field_teams', 'service_zone_id')) {
                $table->unsignedBigInteger('service_zone_id')->nullable();
            }

            if (! Schema::hasColumn('field_teams', 'team_lead_user_id')) {
                $table->unsignedBigInteger('team_lead_user_id')->nullable();
            }

            if (! Schema::hasColumn('field_teams', 'slug')) {
                $table->string('slug')->nullable();
            }

            if (! Schema::hasColumn('field_teams', 'is_internal')) {
                $table->boolean('is_internal')->default(true);
            }
        });
    }

    private function fixMissionTrackingSessions(): void
    {
        if (! Schema::hasTable('mission_tracking_sessions')) {
            return;
        }

        Schema::table('mission_tracking_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('mission_tracking_sessions', 'employee_user_id')) {
                $table->unsignedBigInteger('employee_user_id')->nullable();
            }

            if (! Schema::hasColumn('mission_tracking_sessions', 'tracking_mode')) {
                $table->string('tracking_mode')->nullable();
            }

            if (! Schema::hasColumn('mission_tracking_sessions', 'is_active')) {
                $table->boolean('is_active')->default(false);
            }

            if (! Schema::hasColumn('mission_tracking_sessions', 'last_lat')) {
                $table->decimal('last_lat', 10, 7)->nullable();
            }

            if (! Schema::hasColumn('mission_tracking_sessions', 'last_lng')) {
                $table->decimal('last_lng', 10, 7)->nullable();
            }
        });
    }

    private function fixBookings(): void
    {
        if (! Schema::hasTable('bookings')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'rappel_24h_envoye_at')) {
                $table->timestamp('rappel_24h_envoye_at')->nullable();
            }

            if (! Schema::hasColumn('bookings', 'rappel_2h_envoye_at')) {
                $table->timestamp('rappel_2h_envoye_at')->nullable();
            }

            if (! Schema::hasColumn('bookings', 'alerte_urgence_envoyee_at')) {
                $table->timestamp('alerte_urgence_envoyee_at')->nullable();
            }

            if (! Schema::hasColumn('bookings', 'recurrence_days')) {
                $table->json('recurrence_days')->nullable();
            }

            if (! Schema::hasColumn('bookings', 'series_status')) {
                $table->string('series_status')->nullable();
            }

            if (! Schema::hasColumn('bookings', 'series_position')) {
                $table->unsignedInteger('series_position')->nullable();
            }
        });
    }

    private function fixServiceCatalogs(): void
    {
        if (! Schema::hasTable('service_catalogs')) {
            return;
        }

        DB::table('service_catalogs')
            ->whereNull('service_type')
            ->update(['service_type' => 'standard']);
    }

    public function down(): void
    {
        //
    }
};