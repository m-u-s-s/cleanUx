<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_accounts', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('slug')->unique();
            $table->unsignedBigInteger('country_id')->nullable();
            $table->unsignedBigInteger('region_id')->nullable();
            $table->unsignedBigInteger('province_id')->nullable();
            $table->unsignedBigInteger('commune_id')->nullable();
            $table->unsignedBigInteger('postal_code_id')->nullable();

            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code')->nullable();

            $table->boolean('is_key_account')->default(false);

            // client_company, provider_company, provider_solo, hybrid.
            $table->string('type');


            // pending, active, suspended, archived.
            $table->string('status')->default('active');

            $table->string('tva_number')->nullable()->index();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            $table->string('billing_email')->nullable();
            $table->string('billing_address')->nullable();
            $table->string('billing_city')->nullable();
            $table->string('billing_postal_code')->nullable();
            $table->string('billing_country', 2)->default('BE');

            $table->string('default_currency', 3)->default('EUR');

            // immediate, monthly, contract.
            $table->string('payment_terms')->default('immediate');

            $table->boolean('is_multisite')->default(false);
            $table->boolean('requires_internal_approval')->default(false);

            $table->json('settings')->nullable();
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index('slug');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('current_organization_id')
                ->references('id')
                ->on('organization_accounts')
                ->nullOnDelete();
        });

        Schema::create('organization_members', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_account_id')
                ->constrained('organization_accounts')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            /*
             * Client company roles:
             * owner, manager, site_manager, finance, requester, viewer
             *
             * Provider company roles:
             * owner, operations_manager, dispatcher, team_lead, worker, quality_manager, finance, viewer
             */
            $table->string('role');

            $table->json('permissions')->nullable();

            // invited, active, suspended, left.
            $table->string('status')->default('active');

            $table->foreignId('invited_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('invited_at')->nullable();
            $table->timestamp('joined_at')->nullable();

            $table->timestamps();

            $table->unique(['organization_account_id', 'user_id']);
            $table->index(['organization_account_id', 'role']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('customer_profiles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();

            // personal = client normal / particulier.
            $table->string('customer_type')->default('personal');

            $table->string('default_phone')->nullable();
            $table->string('default_address')->nullable();
            $table->string('default_city')->nullable();
            $table->string('default_postal_code')->nullable();
            $table->string('default_country', 2)->default('BE');

            $table->string('plan_type')->default('standard');
            $table->string('plan_status')->default('inactive');
            $table->timestamp('premium_started_at')->nullable();
            $table->timestamp('premium_renewal_at')->nullable();

            $table->json('preferences')->nullable();

            $table->timestamps();

            $table->index(['customer_type', 'plan_type']);
        });

        Schema::create('provider_profiles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('organization_account_id')
                ->nullable()
                ->constrained('organization_accounts')
                ->nullOnDelete();

            // independent, company_worker.
            $table->string('provider_type')->default('independent');

            // pending, active, suspended, rejected.
            $table->string('status')->default('pending');

            // unverified, pending, verified, rejected.
            $table->string('verification_status')->default('unverified');

            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->decimal('commission_rate', 5, 2)->nullable();

            $table->integer('default_slot_duration')->default(90);

            $table->decimal('current_lat', 10, 7)->nullable();
            $table->decimal('current_lng', 10, 7)->nullable();
            $table->timestamp('last_location_at')->nullable();

            $table->string('stripe_connect_account_id')->nullable();
            $table->string('stripe_connect_status')->default('not_connected');
            $table->timestamp('stripe_connect_onboarded_at')->nullable();

            $table->json('skills')->nullable();
            $table->json('settings')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['provider_type', 'status']);
            $table->index(['status', 'verification_status']);
            $table->index('organization_account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_profiles');
        Schema::dropIfExists('customer_profiles');
        Schema::dropIfExists('organization_members');

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'current_organization_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['current_organization_id']);
            });
        }

        Schema::dropIfExists('organization_accounts');
    }
};
