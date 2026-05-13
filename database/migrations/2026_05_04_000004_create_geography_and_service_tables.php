<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();

            $table->string('iso_code', 2)->unique();
            $table->string('iso3_code', 3)->nullable()->unique();
            $table->string('name');
            $table->string('official_name')->nullable();

            $table->string('default_locale')->default('fr_BE');
            $table->string('currency_code', 3)->default('EUR');
            $table->string('phone_code')->nullable();
            $table->string('timezone')->default('Europe/Brussels');

            $table->boolean('is_active')->default(true);
            $table->boolean('booking_enabled')->default(false);

            // planned, beta, active, paused.
            $table->string('market_stage')->default('planned');

            $table->json('settings')->nullable();

            $table->timestamps();

            $table->index(['is_active', 'booking_enabled']);
        });

        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('country_id')->nullable();

            $table->string('code')->nullable()->index();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('type')->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('provinces', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('country_id')->nullable();
            $table->unsignedBigInteger('region_id')->nullable();

            $table->string('code')->nullable()->index();
            $table->string('name');
            $table->string('slug')->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('communes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('country_id')->nullable();
            $table->unsignedBigInteger('region_id')->nullable();
            $table->unsignedBigInteger('province_id')->nullable();

            $table->string('code')->nullable()->index();
            $table->string('nis_code')->nullable()->index();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('postal_code')->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('service_zones', function (Blueprint $table) {
            $table->id();

            $table->foreignId('country_id')
                ->nullable()
                ->constrained('countries')
                ->nullOnDelete();

            $table->string('name');
            $table->string('slug')->unique();

            // active, inactive, planned.
            $table->string('status')->default('active');

            $table->json('coverage_postal_codes')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['country_id', 'status']);



            $table->unsignedBigInteger('region_id')->nullable();
            $table->unsignedBigInteger('province_id')->nullable();
            $table->unsignedBigInteger('commune_id')->nullable();
            $table->unsignedBigInteger('parent_zone_id')->nullable();

            $table->string('code')->nullable()->index();
            $table->string('coverage_type')->nullable();

            $table->boolean('is_bookable')->default(true);
            $table->boolean('is_visible')->default(true);

            $table->unsignedInteger('priority')->default(0);
            $table->unsignedInteger('minimum_notice_hours')->nullable();
            $table->unsignedInteger('maximum_daily_jobs')->nullable();

            $table->decimal('travel_surcharge', 10, 2)->default(0);
            $table->unsignedInteger('time_buffer_minutes')->default(0);

            $table->text('notes')->nullable();

            $table->timestamp('activated_at')->nullable();
            $table->timestamp('deactivated_at')->nullable();
        });

        Schema::create('postal_codes', function (Blueprint $table) {
            $table->id();



            $table->foreignId('country_id')
                ->nullable()
                ->constrained('countries')
                ->nullOnDelete();

            $table->unsignedBigInteger('region_id')->nullable();
            $table->unsignedBigInteger('province_id')->nullable();
            $table->unsignedBigInteger('commune_id')->nullable();
            $table->foreignId('service_zone_id')
                ->nullable()
                ->constrained('service_zones')
                ->nullOnDelete();

            $table->string('code');
            $table->string('city_name');

            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['country_id', 'code', 'city_name']);
            $table->index(['code', 'city_name']);
            $table->index('service_zone_id');
        });

        Schema::create('service_catalogs', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('slug')->unique();
            $table->string('code')->unique();

            $table->text('description')->nullable();

            // home, office, deep_cleaning, b2b, urgent...
            $table->string('category')->nullable();

            $table->integer('default_duration_minutes')->default(90);
            $table->decimal('base_price', 10, 2)->default(0);
            $table->string('currency', 3)->default('EUR');

            $table->boolean('is_active')->default(true);
            $table->boolean('requires_manual_validation')->default(false);
            $table->boolean('is_b2b_available')->default(true);
            $table->boolean('is_personal_available')->default(true);

            $table->json('options')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['category', 'is_active']);
        });

        Schema::create('zone_service_rules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('service_zone_id')
                ->constrained('service_zones')
                ->cascadeOnDelete();

            $table->foreignId('service_catalog_id')
                ->constrained('service_catalogs')
                ->cascadeOnDelete();

            $table->boolean('is_enabled')->default(true);
            $table->boolean('requires_manual_validation')->default(false);

            $table->decimal('price_multiplier', 8, 4)->default(1);
            $table->integer('minimum_notice_hours')->default(24);
            $table->integer('max_bookings_per_day')->nullable();

            $table->json('settings')->nullable();

            $table->timestamps();

            $table->unique(['service_zone_id', 'service_catalog_id']);
            $table->index(['service_zone_id', 'is_enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zone_service_rules');
        Schema::dropIfExists('service_catalogs');
        Schema::dropIfExists('postal_codes');
        Schema::dropIfExists('service_zones');
        Schema::dropIfExists('countries');
    }
};
