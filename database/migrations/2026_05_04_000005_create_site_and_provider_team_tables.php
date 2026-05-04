<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_sites', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_account_id')
                ->constrained('organization_accounts')
                ->cascadeOnDelete();

            $table->string('name');

            // bureau, commerce, école, dépôt, restaurant...
            $table->string('type')->nullable();

            $table->string('address');
            $table->string('city');
            $table->string('postal_code');
            $table->string('country', 2)->default('BE');

            $table->foreignId('postal_code_id')
                ->nullable()
                ->constrained('postal_codes')
                ->nullOnDelete();

            $table->foreignId('service_zone_id')
                ->nullable()
                ->constrained('service_zones')
                ->nullOnDelete();

            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();

            $table->integer('surface_m2')->nullable();
            $table->string('floor')->nullable();

            $table->string('contact_name')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_email')->nullable();

            $table->text('access_instructions')->nullable();
            $table->text('cleaning_instructions')->nullable();

            $table->json('allowed_days')->nullable();
            $table->time('access_start_time')->nullable();
            $table->time('access_end_time')->nullable();

            $table->boolean('parking_available')->default(false);
            $table->boolean('badge_required')->default(false);
            $table->boolean('alarm_code_required')->default(false);
            $table->boolean('has_sensitive_areas')->default(false);

            $table->string('default_frequency')->nullable();
            $table->decimal('monthly_budget', 10, 2)->nullable();
            $table->string('cost_center')->nullable();

            $table->string('status')->default('active');

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['organization_account_id', 'status']);
            $table->index(['postal_code', 'city']);
            $table->index('service_zone_id');
        });

        Schema::create('organization_member_site_access', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_member_id')
                ->constrained('organization_members')
                ->cascadeOnDelete();

            $table->foreignId('organization_site_id')
                ->constrained('organization_sites')
                ->cascadeOnDelete();

            // view, request, manage.
            $table->string('access_level')->default('manage');

            $table->timestamps();

            $table->unique(['organization_member_id', 'organization_site_id']);
        });

        Schema::create('provider_teams', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_account_id')
                ->constrained('organization_accounts')
                ->cascadeOnDelete();

            $table->string('name');
            $table->text('description')->nullable();

            $table->foreignId('team_lead_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('service_zone_id')
                ->nullable()
                ->constrained('service_zones')
                ->nullOnDelete();

            $table->string('status')->default('active');

            $table->json('settings')->nullable();

            $table->timestamps();

            $table->index(['organization_account_id', 'status']);
            $table->index('service_zone_id');
        });

        Schema::create('provider_team_members', function (Blueprint $table) {
            $table->id();

            $table->foreignId('provider_team_id')
                ->constrained('provider_teams')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // team_lead, worker, trainee.
            $table->string('role')->default('worker');

            $table->string('status')->default('active');

            $table->timestamps();

            $table->unique(['provider_team_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('provider_availabilities', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('organization_account_id')
                ->nullable()
                ->constrained('organization_accounts')
                ->nullOnDelete();

            // weekly, exception, unavailable.
            $table->string('type')->default('weekly');

            // 1 = Monday, 7 = Sunday.
            $table->unsignedTinyInteger('weekday')->nullable();

            $table->date('date')->nullable();

            $table->time('start_time');
            $table->time('end_time');

            $table->boolean('is_available')->default(true);

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'type']);
            $table->index(['organization_account_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_availabilities');
        Schema::dropIfExists('provider_team_members');
        Schema::dropIfExists('provider_teams');
        Schema::dropIfExists('organization_member_site_access');
        Schema::dropIfExists('organization_sites');
    }
};
