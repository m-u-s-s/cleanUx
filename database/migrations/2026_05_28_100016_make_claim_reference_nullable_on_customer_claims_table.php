<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Schema-drift repair for App\Models\CustomerClaim::claim_reference. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customer_claims')) {
            return;
        }

        if (Schema::hasColumn('customer_claims', 'claim_reference')) {
            Schema::table('customer_claims', function (Blueprint $table) {
                $table->string('claim_reference')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // No-op: re-applying NOT NULL would fail on any rows inserted without a
        // claim_reference, and restoring the original constraint is not safe.
    }
};
