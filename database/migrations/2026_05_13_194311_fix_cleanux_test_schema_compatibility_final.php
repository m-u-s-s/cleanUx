<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('missions')) {
            Schema::table('missions', function (Blueprint $table) {
                if (! Schema::hasColumn('missions', 'requires_start_code')) {
                    $table->boolean('requires_start_code')->default(true);
                }

                if (! Schema::hasColumn('missions', 'requires_end_code')) {
                    $table->boolean('requires_end_code')->default(true);
                }

                if (! Schema::hasColumn('missions', 'client_presence_confirmed')) {
                    $table->boolean('client_presence_confirmed')->default(false);
                }

                if (! Schema::hasColumn('missions', 'started_by_user_id')) {
                    $table->unsignedBigInteger('started_by_user_id')->nullable();
                }

                if (! Schema::hasColumn('missions', 'closed_by_user_id')) {
                    $table->unsignedBigInteger('closed_by_user_id')->nullable();
                }

                if (! Schema::hasColumn('missions', 'destination_lat')) {
                    $table->decimal('destination_lat', 10, 7)->nullable();
                }

                if (! Schema::hasColumn('missions', 'destination_lng')) {
                    $table->decimal('destination_lng', 10, 7)->nullable();
                }
            });
        }

        if (Schema::hasTable('bookings')) {
            Schema::table('bookings', function (Blueprint $table) {
                if (! Schema::hasColumn('bookings', 'destination_lat')) {
                    $table->decimal('destination_lat', 10, 7)->nullable();
                }

                if (! Schema::hasColumn('bookings', 'destination_lng')) {
                    $table->decimal('destination_lng', 10, 7)->nullable();
                }

                if (! Schema::hasColumn('bookings', 'cancelled_by')) {
                    $table->unsignedBigInteger('cancelled_by')->nullable();
                }

                if (! Schema::hasColumn('bookings', 'cancellation_reason')) {
                    $table->text('cancellation_reason')->nullable();
                }

                if (! Schema::hasColumn('bookings', 'cancellation_fee_amount')) {
                    $table->decimal('cancellation_fee_amount', 10, 2)->default(0);
                }

                if (! Schema::hasColumn('bookings', 'cancellation_fee_percent')) {
                    $table->unsignedInteger('cancellation_fee_percent')->default(0);
                }

                if (! Schema::hasColumn('bookings', 'series_status')) {
                    $table->string('series_status')->nullable();
                }

                if (! Schema::hasColumn('bookings', 'series_position')) {
                    $table->unsignedInteger('series_position')->nullable();
                }
            });
        }

        if (Schema::hasTable('field_teams')) {
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

                if (! Schema::hasColumn('field_teams', 'is_internal')) {
                    $table->boolean('is_internal')->default(true);
                }
            });
        }

        if (Schema::hasTable('location_geocodes')) {
            Schema::table('location_geocodes', function (Blueprint $table) {
                if (! Schema::hasColumn('location_geocodes', 'lookup_hash')) {
                    $table->string('lookup_hash')->nullable()->index();
                }

                if (! Schema::hasColumn('location_geocodes', 'address_line')) {
                    $table->string('address_line')->nullable();
                }

                if (! Schema::hasColumn('location_geocodes', 'country_code')) {
                    $table->string('country_code', 2)->nullable();
                }

                if (! Schema::hasColumn('location_geocodes', 'raw')) {
                    $table->json('raw')->nullable();
                }
            });
        }

        if (Schema::hasTable('service_catalogs')) {
            Schema::table('service_catalogs', function (Blueprint $table) {
                if (! Schema::hasColumn('service_catalogs', 'service_type')) {
                    $table->string('service_type', 60)->nullable();
                }

                if (! Schema::hasColumn('service_catalogs', 'requires_quote')) {
                    $table->boolean('requires_quote')->default(false);
                }

                if (! Schema::hasColumn('service_catalogs', 'is_entreprise')) {
                    $table->boolean('is_entreprise')->default(false);
                }

                if (! Schema::hasColumn('service_catalogs', 'sort_order')) {
                    $table->unsignedInteger('sort_order')->default(0);
                }

                if (! Schema::hasColumn('service_catalogs', 'settings')) {
                    $table->json('settings')->nullable();
                }
            });

            DB::table('service_catalogs')
                ->whereNull('service_type')
                ->update(['service_type' => 'standard']);
        }

        if (Schema::hasTable('mission_assignments')) {
            Schema::table('mission_assignments', function (Blueprint $table) {
                if (! Schema::hasColumn('mission_assignments', 'notification_sent_at')) {
                    $table->timestamp('notification_sent_at')->nullable();
                }

                if (! Schema::hasColumn('mission_assignments', 'expires_at')) {
                    $table->timestamp('expires_at')->nullable();
                }

                if (! Schema::hasColumn('mission_assignments', 'response_seconds')) {
                    $table->unsignedInteger('response_seconds')->nullable();
                }

                if (! Schema::hasColumn('mission_assignments', 'decline_reason')) {
                    $table->string('decline_reason')->nullable();
                }

                if (! Schema::hasColumn('mission_assignments', 'role_on_mission')) {
                    $table->string('role_on_mission')->nullable();
                }

                if (! Schema::hasColumn('mission_assignments', 'escalated_from_assignment_id')) {
                    $table->unsignedBigInteger('escalated_from_assignment_id')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        //
    }
};