<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensureCountryBillingProfiles();
        $this->ensureMissionTeamAssignments();
        $this->ensureEnterpriseBookingApprovalsColumns();
        $this->ensureWorkOrderApprovalsColumns();
        $this->ensureCountryServiceCatalogRules();
    }

    private function ensureCountryBillingProfiles(): void
    {
        if (! Schema::hasTable('country_billing_profiles')) {
            Schema::create('country_billing_profiles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('country_id')->nullable()->unique();
                $table->string('currency_code', 3)->default('EUR');
                $table->string('currency_symbol', 8)->default('€');
                $table->decimal('default_tax_rate', 5, 2)->default(21.00);
                $table->string('tax_label')->default('TVA');
                $table->boolean('requires_vat_number')->default(true);
                $table->boolean('supports_invoicing')->default(true);
                $table->boolean('supports_credit_notes')->default(true);
                $table->string('invoice_prefix')->default('CUX');
                $table->string('quote_prefix')->default('DEVIS');
                $table->json('invoice_settings')->nullable();
                $table->json('payment_settings')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });

            return;
        }

        Schema::table('country_billing_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('country_billing_profiles', 'country_id')) {
                $table->unsignedBigInteger('country_id')->nullable()->index();
            }

            if (! Schema::hasColumn('country_billing_profiles', 'currency_code')) {
                $table->string('currency_code', 3)->default('EUR');
            }

            if (! Schema::hasColumn('country_billing_profiles', 'default_tax_rate')) {
                $table->decimal('default_tax_rate', 5, 2)->default(21.00);
            }

            if (! Schema::hasColumn('country_billing_profiles', 'metadata')) {
                $table->json('metadata')->nullable();
            }
        });
    }

    private function ensureMissionTeamAssignments(): void
    {
        if (! Schema::hasTable('mission_team_assignments')) {
            Schema::create('mission_team_assignments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('mission_id')->index();
                $table->unsignedBigInteger('field_team_id')->index();

                $table->string('assignment_status')->default('assigned');
                $table->string('status')->nullable();

                $table->timestamp('assigned_at')->nullable();
                $table->timestamp('accepted_at')->nullable();
                $table->timestamp('declined_at')->nullable();
                $table->timestamp('completed_at')->nullable();

                $table->unsignedBigInteger('assigned_by_user_id')->nullable();

                $table->text('notes')->nullable();
                $table->json('metadata')->nullable();

                $table->timestamps();
            });

            return;
        }

        Schema::table('mission_team_assignments', function (Blueprint $table) {
            if (! Schema::hasColumn('mission_team_assignments', 'assignment_status')) {
                $table->string('assignment_status')->default('assigned');
            }

            if (! Schema::hasColumn('mission_team_assignments', 'assigned_at')) {
                $table->timestamp('assigned_at')->nullable();
            }

            if (! Schema::hasColumn('mission_team_assignments', 'metadata')) {
                $table->json('metadata')->nullable();
            }
        });
    }

    private function ensureEnterpriseBookingApprovalsColumns(): void
    {
        if (! Schema::hasTable('enterprise_booking_approvals')) {
            Schema::create('enterprise_booking_approvals', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('rendez_vous_id')->nullable()->index();
                $table->unsignedBigInteger('booking_id')->nullable()->index();

                $table->unsignedBigInteger('organization_account_id')->nullable()->index();
                $table->unsignedBigInteger('organization_site_id')->nullable()->index();

                $table->unsignedBigInteger('requested_by_user_id')->nullable()->index();
                $table->unsignedBigInteger('approved_by_user_id')->nullable()->index();
                $table->unsignedBigInteger('rejected_by_user_id')->nullable()->index();

                $table->string('status')->default('pending');
                $table->string('approval_status')->default('pending');

                $table->text('request_note')->nullable();
                $table->text('approval_note')->nullable();
                $table->text('rejection_reason')->nullable();

                $table->timestamp('requested_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('rejected_at')->nullable();

                $table->string('purchase_order_number')->nullable();
                $table->string('cost_center')->nullable();

                $table->json('metadata')->nullable();

                $table->timestamps();
            });

            return;
        }

        Schema::table('enterprise_booking_approvals', function (Blueprint $table) {
            if (! Schema::hasColumn('enterprise_booking_approvals', 'request_note')) {
                $table->text('request_note')->nullable();
            }

            if (! Schema::hasColumn('enterprise_booking_approvals', 'approval_note')) {
                $table->text('approval_note')->nullable();
            }

            if (! Schema::hasColumn('enterprise_booking_approvals', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable();
            }

            if (! Schema::hasColumn('enterprise_booking_approvals', 'requested_at')) {
                $table->timestamp('requested_at')->nullable();
            }

            if (! Schema::hasColumn('enterprise_booking_approvals', 'approved_at')) {
                $table->timestamp('approved_at')->nullable();
            }

            if (! Schema::hasColumn('enterprise_booking_approvals', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable();
            }

            if (! Schema::hasColumn('enterprise_booking_approvals', 'metadata')) {
                $table->json('metadata')->nullable();
            }
        });
    }

    private function ensureWorkOrderApprovalsColumns(): void
    {
        if (! Schema::hasTable('work_order_approvals')) {
            Schema::create('work_order_approvals', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('enterprise_work_order_id')->index();
                $table->unsignedBigInteger('approver_user_id')->nullable()->index();
                $table->unsignedBigInteger('approved_by_user_id')->nullable()->index();

                $table->string('approval_status')->default('pending');
                $table->string('status')->nullable();

                $table->timestamp('approved_at')->nullable();
                $table->timestamp('rejected_at')->nullable();

                $table->text('comment')->nullable();
                $table->text('rejection_reason')->nullable();

                $table->json('metadata')->nullable();

                $table->timestamps();
            });

            return;
        }

        Schema::table('work_order_approvals', function (Blueprint $table) {
            if (! Schema::hasColumn('work_order_approvals', 'approver_user_id')) {
                $table->unsignedBigInteger('approver_user_id')->nullable()->index();
            }

            if (! Schema::hasColumn('work_order_approvals', 'approval_status')) {
                $table->string('approval_status')->default('pending');
            }

            if (! Schema::hasColumn('work_order_approvals', 'approved_at')) {
                $table->timestamp('approved_at')->nullable();
            }

            if (! Schema::hasColumn('work_order_approvals', 'comment')) {
                $table->text('comment')->nullable();
            }

            if (! Schema::hasColumn('work_order_approvals', 'metadata')) {
                $table->json('metadata')->nullable();
            }
        });
    }

    private function ensureCountryServiceCatalogRules(): void
    {
        if (Schema::hasTable('country_service_catalog_rules')) {
            return;
        }

        Schema::create('country_service_catalog_rules', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('country_id')->index();
            $table->unsignedBigInteger('service_catalog_id')->index();

            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_bookable')->default(true);
            $table->boolean('requires_quote')->default(false);
            $table->boolean('requires_manual_validation')->default(false);

            $table->unsignedInteger('default_duration_minutes')->nullable();
            $table->decimal('base_price', 10, 2)->nullable();
            $table->decimal('price_multiplier', 8, 4)->default(1);
            $table->decimal('travel_surcharge', 10, 2)->default(0);

            $table->unsignedInteger('minimum_notice_hours')->nullable();
            $table->unsignedInteger('maximum_daily_jobs')->nullable();

            $table->json('settings')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->unique(
                ['country_id', 'service_catalog_id'],
                'country_service_catalog_unique'
            );
        });
    }

    public function down(): void
    {
        //
    }
};