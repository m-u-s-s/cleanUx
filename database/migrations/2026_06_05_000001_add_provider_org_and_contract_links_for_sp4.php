<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('organization_contracts') && ! Schema::hasColumn('organization_contracts', 'provider_organization_id')) {
            Schema::table('organization_contracts', function (Blueprint $table) {
                $table->unsignedBigInteger('provider_organization_id')->nullable()->after('organization_account_id')->index();
            });
        }

        if (Schema::hasTable('bookings') && ! Schema::hasColumn('bookings', 'organization_contract_id')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->unsignedBigInteger('organization_contract_id')->nullable()->index();
            });
        }

        if (Schema::hasTable('missions')) {
            Schema::table('missions', function (Blueprint $table) {
                if (! Schema::hasColumn('missions', 'organization_contract_id')) {
                    $table->unsignedBigInteger('organization_contract_id')->nullable()->index();
                }
                if (! Schema::hasColumn('missions', 'sla_response_due_at')) {
                    $table->dateTime('sla_response_due_at')->nullable();
                }
                if (! Schema::hasColumn('missions', 'sla_resolution_due_at')) {
                    $table->dateTime('sla_resolution_due_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        // Idempotent additive migration — no destructive rollback (cohérent avec le projet).
    }
};
