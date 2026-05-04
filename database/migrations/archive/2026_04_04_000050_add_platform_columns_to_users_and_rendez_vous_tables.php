<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organization_account_id')->nullable()->after('premium_renewal_at')->constrained('organization_accounts')->nullOnDelete();
            $table->foreignId('postal_code_id')->nullable()->after('organization_account_id')->constrained('postal_codes')->nullOnDelete();
            $table->foreignId('primary_service_zone_id')->nullable()->after('postal_code_id')->constrained('service_zones')->nullOnDelete();
            $table->string('phone', 30)->nullable()->after('primary_service_zone_id');
            $table->string('locale', 10)->default('fr_BE')->after('phone');
            $table->string('timezone')->default('Europe/Brussels')->after('locale');
            $table->string('status')->default('active')->after('timezone');
            $table->boolean('is_active')->default(true)->after('status');
            $table->json('metadata')->nullable()->after('is_active');

            $table->index(['organization_account_id', 'role']);
            $table->index(['primary_service_zone_id', 'role']);
            $table->index(['status', 'is_active']);
        });

        Schema::table('rendez_vous', function (Blueprint $table) {
            $table->foreignId('organization_account_id')->nullable()->after('employe_id')->constrained('organization_accounts')->nullOnDelete();
            $table->foreignId('organization_site_id')->nullable()->after('organization_account_id')->constrained('organization_sites')->nullOnDelete();
            $table->foreignId('service_catalog_id')->nullable()->after('organization_site_id')->constrained('service_catalogs')->nullOnDelete();
            $table->foreignId('service_zone_id')->nullable()->after('service_catalog_id')->constrained('service_zones')->nullOnDelete();
            $table->foreignId('postal_code_id')->nullable()->after('service_zone_id')->constrained('postal_codes')->nullOnDelete();
            $table->string('booking_channel', 30)->default('web')->after('postal_code_id');
            $table->string('booking_reference')->nullable()->after('booking_channel')->unique();
            $table->json('zone_snapshot')->nullable()->after('booking_reference');
            $table->json('pricing_snapshot')->nullable()->after('zone_snapshot');

            $table->index(['service_zone_id', 'date']);
            $table->index(['organization_account_id', 'date']);
            $table->index(['service_catalog_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('rendez_vous', function (Blueprint $table) {
            $table->dropIndex(['service_zone_id', 'date']);
            $table->dropIndex(['organization_account_id', 'date']);
            $table->dropIndex(['service_catalog_id', 'status']);
            $table->dropConstrainedForeignId('organization_account_id');
            $table->dropConstrainedForeignId('organization_site_id');
            $table->dropConstrainedForeignId('service_catalog_id');
            $table->dropConstrainedForeignId('service_zone_id');
            $table->dropConstrainedForeignId('postal_code_id');
            $table->dropColumn(['booking_channel', 'booking_reference', 'zone_snapshot', 'pricing_snapshot']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['organization_account_id', 'role']);
            $table->dropIndex(['primary_service_zone_id', 'role']);
            $table->dropIndex(['status', 'is_active']);
            $table->dropConstrainedForeignId('organization_account_id');
            $table->dropConstrainedForeignId('postal_code_id');
            $table->dropConstrainedForeignId('primary_service_zone_id');
            $table->dropColumn(['phone', 'locale', 'timezone', 'status', 'is_active', 'metadata']);
        });
    }
};
