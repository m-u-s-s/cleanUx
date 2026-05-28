<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema-drift repair for App\Models\OrganizationSite.
 *
 * The model declares floor_count, preferred_provider_id (FK), the two
 * frequency value fields (cleaning_frequency legacy + service_frequency
 * multi-trade), preferred_time_slot and notes as fillable/cast with no
 * matching column on organization_sites. Added defensively.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('organization_sites')) {
            return;
        }

        Schema::table('organization_sites', function (Blueprint $table) {
            if (! Schema::hasColumn('organization_sites', 'floor_count')) {
                $table->unsignedInteger('floor_count')->nullable();
            }
            if (! Schema::hasColumn('organization_sites', 'preferred_provider_id')) {
                $table->unsignedBigInteger('preferred_provider_id')->nullable()->index();
            }
            if (! Schema::hasColumn('organization_sites', 'cleaning_frequency')) {
                $table->string('cleaning_frequency')->nullable();
            }
            if (! Schema::hasColumn('organization_sites', 'service_frequency')) {
                $table->string('service_frequency')->nullable();
            }
            if (! Schema::hasColumn('organization_sites', 'preferred_time_slot')) {
                $table->string('preferred_time_slot')->nullable();
            }
            if (! Schema::hasColumn('organization_sites', 'notes')) {
                $table->text('notes')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('organization_sites')) {
            return;
        }

        Schema::table('organization_sites', function (Blueprint $table) {
            foreach ([
                'floor_count',
                'preferred_provider_id',
                'cleaning_frequency',
                'service_frequency',
                'preferred_time_slot',
                'notes',
            ] as $col) {
                if (Schema::hasColumn('organization_sites', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
