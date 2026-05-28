<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema-drift repair for App\Models\OrganizationContract.
 *
 * The model declares allowed_service_catalog_ids and metadata (both array
 * casts -> json) as fillable/cast with no matching column on
 * organization_contracts. Added defensively.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('organization_contracts')) {
            return;
        }

        Schema::table('organization_contracts', function (Blueprint $table) {
            if (! Schema::hasColumn('organization_contracts', 'allowed_service_catalog_ids')) {
                $table->json('allowed_service_catalog_ids')->nullable();
            }
            if (! Schema::hasColumn('organization_contracts', 'metadata')) {
                $table->json('metadata')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('organization_contracts')) {
            return;
        }

        Schema::table('organization_contracts', function (Blueprint $table) {
            foreach ([
                'allowed_service_catalog_ids',
                'metadata',
            ] as $col) {
                if (Schema::hasColumn('organization_contracts', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
