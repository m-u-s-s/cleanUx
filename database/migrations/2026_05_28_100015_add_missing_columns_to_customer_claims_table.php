<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema-drift repair for App\Models\CustomerClaim.
 *
 * The model declares client_id, rendez_vous_id, title and sla_due_at as
 * fillable (written by App\Livewire\Client\LitigesClient) but the
 * customer_claims table — created with a customer_user_id/booking_id naming —
 * is missing these alternate columns. Added defensively with matching types.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customer_claims')) {
            return;
        }

        Schema::table('customer_claims', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_claims', 'client_id')) {
                $table->unsignedBigInteger('client_id')->nullable()->index();
            }
            if (! Schema::hasColumn('customer_claims', 'rendez_vous_id')) {
                $table->unsignedBigInteger('rendez_vous_id')->nullable()->index();
            }
            if (! Schema::hasColumn('customer_claims', 'title')) {
                $table->string('title')->nullable();
            }
            if (! Schema::hasColumn('customer_claims', 'sla_due_at')) {
                $table->timestamp('sla_due_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('customer_claims')) {
            return;
        }

        Schema::table('customer_claims', function (Blueprint $table) {
            foreach (['client_id', 'rendez_vous_id', 'title', 'sla_due_at'] as $col) {
                if (Schema::hasColumn('customer_claims', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
