<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema-drift repair for App\Models\WorkOrderApproval::rejected_at.
 *
 * The model declares rejected_at as fillable and casts it to datetime
 * (mirroring approved_at) but the work_order_approvals table has no such
 * column. Added defensively as a nullable timestamp.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('work_order_approvals')) {
            return;
        }

        Schema::table('work_order_approvals', function (Blueprint $table) {
            if (! Schema::hasColumn('work_order_approvals', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('work_order_approvals')) {
            return;
        }

        Schema::table('work_order_approvals', function (Blueprint $table) {
            if (Schema::hasColumn('work_order_approvals', 'rejected_at')) {
                $table->dropColumn('rejected_at');
            }
        });
    }
};
