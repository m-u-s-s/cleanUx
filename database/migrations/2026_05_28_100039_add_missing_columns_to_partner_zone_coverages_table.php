<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema-drift repair for App\Models\PartnerZoneCoverage.
 *
 * The model declares service_catalog_id (FK), coverage_status, priority,
 * max_daily_capacity and sla_response_hours as fillable/cast with no matching
 * column on partner_zone_coverages. Added defensively with types matching the
 * casts.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('partner_zone_coverages')) {
            return;
        }

        Schema::table('partner_zone_coverages', function (Blueprint $table) {
            if (! Schema::hasColumn('partner_zone_coverages', 'service_catalog_id')) {
                $table->unsignedBigInteger('service_catalog_id')->nullable()->index();
            }
            if (! Schema::hasColumn('partner_zone_coverages', 'coverage_status')) {
                $table->string('coverage_status')->nullable();
            }
            if (! Schema::hasColumn('partner_zone_coverages', 'priority')) {
                $table->unsignedInteger('priority')->nullable();
            }
            if (! Schema::hasColumn('partner_zone_coverages', 'max_daily_capacity')) {
                $table->unsignedInteger('max_daily_capacity')->nullable();
            }
            if (! Schema::hasColumn('partner_zone_coverages', 'sla_response_hours')) {
                $table->unsignedInteger('sla_response_hours')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('partner_zone_coverages')) {
            return;
        }

        Schema::table('partner_zone_coverages', function (Blueprint $table) {
            foreach ([
                'service_catalog_id',
                'coverage_status',
                'priority',
                'max_daily_capacity',
                'sla_response_hours',
            ] as $col) {
                if (Schema::hasColumn('partner_zone_coverages', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
