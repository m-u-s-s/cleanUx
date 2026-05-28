<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema-drift repair for App\Models\MissionBatch.
 *
 * The model declares several fillable/cast attributes (organisation FKs,
 * reference, batch_type, planning window, estimates, notes) with no matching
 * column on mission_batches. Added defensively with types matching the casts.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mission_batches')) {
            return;
        }

        Schema::table('mission_batches', function (Blueprint $table) {
            if (! Schema::hasColumn('mission_batches', 'organization_account_id')) {
                $table->unsignedBigInteger('organization_account_id')->nullable()->index();
            }
            if (! Schema::hasColumn('mission_batches', 'organization_site_id')) {
                $table->unsignedBigInteger('organization_site_id')->nullable()->index();
            }
            if (! Schema::hasColumn('mission_batches', 'enterprise_work_order_id')) {
                $table->unsignedBigInteger('enterprise_work_order_id')->nullable()->index();
            }
            if (! Schema::hasColumn('mission_batches', 'service_partner_id')) {
                $table->unsignedBigInteger('service_partner_id')->nullable()->index();
            }
            if (! Schema::hasColumn('mission_batches', 'reference')) {
                $table->string('reference')->nullable();
            }
            if (! Schema::hasColumn('mission_batches', 'batch_type')) {
                $table->string('batch_type')->nullable();
            }
            if (! Schema::hasColumn('mission_batches', 'starts_on')) {
                $table->date('starts_on')->nullable();
            }
            if (! Schema::hasColumn('mission_batches', 'ends_on')) {
                $table->date('ends_on')->nullable();
            }
            if (! Schema::hasColumn('mission_batches', 'default_start_time')) {
                $table->string('default_start_time')->nullable();
            }
            if (! Schema::hasColumn('mission_batches', 'default_end_time')) {
                $table->string('default_end_time')->nullable();
            }
            if (! Schema::hasColumn('mission_batches', 'estimated_total_minutes')) {
                $table->unsignedInteger('estimated_total_minutes')->nullable();
            }
            if (! Schema::hasColumn('mission_batches', 'estimated_total_cost')) {
                $table->decimal('estimated_total_cost', 10, 2)->nullable();
            }
            if (! Schema::hasColumn('mission_batches', 'notes')) {
                $table->text('notes')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('mission_batches')) {
            return;
        }

        Schema::table('mission_batches', function (Blueprint $table) {
            foreach ([
                'organization_account_id',
                'organization_site_id',
                'enterprise_work_order_id',
                'service_partner_id',
                'reference',
                'batch_type',
                'starts_on',
                'ends_on',
                'default_start_time',
                'default_end_time',
                'estimated_total_minutes',
                'estimated_total_cost',
                'notes',
            ] as $col) {
                if (Schema::hasColumn('mission_batches', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
