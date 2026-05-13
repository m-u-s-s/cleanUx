<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->rebuildFeedbackTable();
        $this->createCountryOperationalSettings();
        $this->createEnterpriseBookingApprovals();
        $this->createServicePartners();
        $this->fixFieldTeamMembers();
    }

    private function rebuildFeedbackTable(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('feedback');

        Schema::create('feedback', function (Blueprint $table) {
            $table->id();

            // Colonnes volontairement sans FK pour compat legacy/tests.
            // Le code métier les relie à Booking/User via Eloquent.
            $table->unsignedBigInteger('rendez_vous_id')->nullable();
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->unsignedBigInteger('mission_id')->nullable();

            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('employe_id')->nullable();

            $table->unsignedTinyInteger('note')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();

            $table->text('commentaire')->nullable();
            $table->text('comment')->nullable();
            $table->text('feedback')->nullable();
            $table->text('reponse_admin')->nullable();

            $table->unsignedBigInteger('answered_by')->nullable();
            $table->timestamp('answered_at')->nullable();

            $table->string('status')->default('published');
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index('rendez_vous_id');
            $table->index('booking_id');
            $table->index('client_id');
        });

        Schema::enableForeignKeyConstraints();
    }

    private function createCountryOperationalSettings(): void
    {
        if (Schema::hasTable('country_operational_settings')) {
            return;
        }

        Schema::create('country_operational_settings', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('country_id')->nullable()->unique();

            $table->boolean('booking_enabled')->default(true);
            $table->boolean('mission_enabled')->default(true);
            $table->boolean('billing_enabled')->default(true);
            $table->boolean('partner_network_enabled')->default(false);

            $table->string('readiness_stage')->default('booking_enabled');

            $table->decimal('default_tax_rate', 5, 2)->default(21.00);
            $table->string('currency_code', 3)->default('EUR');
            $table->string('currency_symbol', 8)->default('€');

            $table->string('date_format')->default('d/m/Y');
            $table->string('time_format')->default('H:i');

            $table->string('address_format')->nullable();
            $table->string('phone_format')->nullable();

            $table->boolean('requires_vat_number_for_companies')->default(true);

            $table->string('default_distance_unit')->default('km');
            $table->string('default_surface_unit')->default('m2');

            $table->json('local_rules')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();
        });
    }

    private function createEnterpriseBookingApprovals(): void
    {
        if (Schema::hasTable('enterprise_booking_approvals')) {
            return;
        }

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

            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();

            $table->text('approval_note')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->string('purchase_order_number')->nullable();
            $table->string('cost_center')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();
        });
    }

    private function createServicePartners(): void
    {
        if (Schema::hasTable('service_partners')) {
            return;
        }

        Schema::create('service_partners', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('slug')->nullable()->unique();

            $table->string('status')->default('active');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            $table->unsignedBigInteger('country_id')->nullable();
            $table->unsignedBigInteger('service_zone_id')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();
        });
    }

    private function fixFieldTeamMembers(): void
    {
        if (! Schema::hasTable('field_team_members')) {
            return;
        }

        Schema::table('field_team_members', function (Blueprint $table) {
            if (! Schema::hasColumn('field_team_members', 'role_on_team')) {
                $table->string('role_on_team')->nullable();
            }

            if (! Schema::hasColumn('field_team_members', 'is_team_lead')) {
                $table->boolean('is_team_lead')->default(false);
            }

            if (! Schema::hasColumn('field_team_members', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }

            if (! Schema::hasColumn('field_team_members', 'joined_at')) {
                $table->timestamp('joined_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        //
    }
};