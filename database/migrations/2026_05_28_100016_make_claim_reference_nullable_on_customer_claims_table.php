<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema-drift repair for App\Models\CustomerClaim::claim_reference.
 *
 * customer_claims.claim_reference was created NOT NULL UNIQUE with no default
 * (2026_05_04 v2 feature extensions), but nothing in the application generates
 * it: the only insert path — App\Livewire\Client\LitigesClient::create() — does
 * not set it, the model has no observer or booted creating hook, the factory
 * does not set it, and no service writes it. A claim insert therefore fails the
 * NOT NULL constraint. Relax to nullable (UNIQUE is preserved on non-null
 * values) rather than allowlisting a constraint that genuinely breaks inserts.
 */
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
