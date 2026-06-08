<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M6 — consolidate customer_credits on the canonical "credit-per-booking" model
 * (client_id / amount / remaining_amount / status — the shape the CustomerCredit model and all
 * consumers actually use). Remove the leftover "wallet" money columns (balance, currency) and the
 * orphan customer_credit_transactions ledger table (zero code references). A defensive backfill
 * recovers amount/remaining_amount from any row that still only carried balance before dropping it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_credits')
            && Schema::hasColumn('customer_credits', 'balance')
            && Schema::hasColumn('customer_credits', 'amount')) {
            DB::table('customer_credits')
                ->whereNotNull('balance')
                ->where('balance', '>', 0)
                ->where(function ($q) {
                    $q->whereNull('amount')->orWhere('amount', 0);
                })
                ->update([
                    'amount' => DB::raw('balance'),
                    'remaining_amount' => DB::raw('balance'),
                ]);
        }

        Schema::table('customer_credits', function (Blueprint $table) {
            foreach (['balance', 'currency'] as $col) {
                if (Schema::hasColumn('customer_credits', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::dropIfExists('customer_credit_transactions');
    }

    public function down(): void
    {
        if (Schema::hasTable('customer_credits')) {
            Schema::table('customer_credits', function (Blueprint $table) {
                if (! Schema::hasColumn('customer_credits', 'balance')) {
                    $table->decimal('balance', 10, 2)->default(0);
                }
                if (! Schema::hasColumn('customer_credits', 'currency')) {
                    $table->string('currency', 3)->default('EUR');
                }
            });
        }

        if (! Schema::hasTable('customer_credit_transactions')) {
            Schema::create('customer_credit_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_credit_id')->constrained('customer_credits')->cascadeOnDelete();
                $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
                $table->string('type');
                $table->decimal('amount', 10, 2);
                $table->text('description')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['customer_credit_id', 'created_at']);
            });
        }
    }
};
