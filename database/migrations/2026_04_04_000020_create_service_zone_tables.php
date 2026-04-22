<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('region_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('province_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('commune_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parent_zone_id')->nullable()->constrained('service_zones')->nullOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->enum('coverage_type', ['national', 'region', 'province', 'commune', 'postal_code', 'custom'])->default('custom');
            $table->enum('status', ['draft', 'active', 'paused', 'archived'])->default('active');
            $table->boolean('is_bookable')->default(true);
            $table->boolean('is_visible')->default(true);
            $table->unsignedSmallInteger('priority')->default(100);
            $table->unsignedInteger('minimum_notice_hours')->default(24);
            $table->unsignedInteger('maximum_daily_jobs')->nullable();
            $table->decimal('travel_surcharge', 10, 2)->default(0);
            $table->unsignedInteger('time_buffer_minutes')->default(0);
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'is_bookable']);
            $table->index(['coverage_type', 'priority']);
        });

        Schema::create('service_zone_postal_code', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_zone_id')->constrained()->cascadeOnDelete();
            $table->foreignId('postal_code_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['service_zone_id', 'postal_code_id']);
        });

        Schema::create('service_catalogs', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('service_type')->nullable()->index();
            $table->boolean('is_active')->default(true);
            $table->boolean('requires_quote')->default(false);
            $table->boolean('requires_manual_validation')->default(false);
            $table->boolean('is_entreprise')->default(false);
            $table->unsignedInteger('default_duration_minutes')->default(90);
            $table->decimal('base_price', 10, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(100);
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('zone_service_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_zone_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_catalog_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->boolean('requires_manual_validation')->nullable();
            $table->decimal('base_price_override', 10, 2)->nullable();
            $table->decimal('price_multiplier', 8, 2)->nullable();
            $table->unsignedInteger('minimum_notice_hours')->nullable();
            $table->unsignedInteger('maximum_daily_capacity')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['service_zone_id', 'service_catalog_id']);
        });

        Schema::create('employee_zone_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('service_zone_id')->constrained()->cascadeOnDelete();
            $table->enum('assignment_type', ['primary', 'secondary', 'backup'])->default('primary');
            $table->unsignedSmallInteger('coverage_priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'service_zone_id', 'assignment_type'], 'employee_zone_unique_assignment');
            $table->index(['user_id', 'is_active']);
            $table->index(['service_zone_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_zone_assignments');
        Schema::dropIfExists('zone_service_rules');
        Schema::dropIfExists('service_catalogs');
        Schema::dropIfExists('service_zone_postal_code');
        Schema::dropIfExists('service_zones');
    }
};
