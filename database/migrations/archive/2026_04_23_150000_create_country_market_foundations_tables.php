<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('country_operational_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('booking_enabled')->default(false);
            $table->boolean('mission_enabled')->default(false);
            $table->boolean('billing_enabled')->default(false);
            $table->boolean('partner_network_enabled')->default(false);
            $table->string('readiness_stage')->default('draft');
            $table->decimal('default_tax_rate', 8, 2)->default(0);
            $table->string('currency_symbol', 10)->nullable();
            $table->string('date_format', 30)->default('d/m/Y');
            $table->string('time_format', 30)->default('H:i');
            $table->string('address_format', 100)->default('line1_postal_city_country');
            $table->string('phone_format', 50)->default('international');
            $table->boolean('requires_vat_number_for_companies')->default(false);
            $table->string('default_distance_unit', 10)->default('km');
            $table->string('default_surface_unit', 10)->default('m2');
            $table->json('local_rules')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('country_billing_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('invoice_prefix', 20)->default('INV');
            $table->string('quote_prefix', 20)->default('QUO');
            $table->string('tax_label', 30)->default('TVA');
            $table->decimal('default_tax_rate', 8, 2)->default(0);
            $table->boolean('prices_include_tax')->default(false);
            $table->string('rounding_mode', 30)->default('half_up');
            $table->string('decimal_separator', 5)->default(',');
            $table->string('thousands_separator', 5)->default(' ');
            $table->unsignedInteger('payment_terms_days')->default(30);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('country_service_catalog_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_catalog_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->boolean('requires_manual_validation')->default(false);
            $table->boolean('requires_quote')->default(false);
            $table->unsignedInteger('minimum_notice_hours')->default(24);
            $table->unsignedInteger('sla_response_hours')->nullable();
            $table->unsignedInteger('sla_resolution_hours')->nullable();
            $table->unsignedBigInteger('default_team_id')->nullable();
            $table->unsignedBigInteger('default_partner_id')->nullable();
            $table->decimal('pricing_multiplier', 8, 2)->default(1);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->unique(['country_id', 'service_catalog_id'], 'country_service_catalog_unique');
        });

        Schema::create('market_launch_readiness', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('catalog_ready')->default(false);
            $table->boolean('booking_ready')->default(false);
            $table->boolean('mission_ready')->default(false);
            $table->boolean('billing_ready')->default(false);
            $table->boolean('partner_network_ready')->default(false);
            $table->boolean('compliance_ready')->default(false);
            $table->boolean('support_ready')->default(false);
            $table->text('notes')->nullable();
            $table->timestamp('last_audited_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_launch_readiness');
        Schema::dropIfExists('country_service_catalog_rules');
        Schema::dropIfExists('country_billing_profiles');
        Schema::dropIfExists('country_operational_settings');
    }
};
