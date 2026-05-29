<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'payout_status')) {
                $table->string('payout_status', 32)->nullable()->after('payment_status');
            }
            // provider_payout_cents — distinct from provider_amount_cents (the gross pre-fee amount)
            if (! Schema::hasColumn('bookings', 'provider_payout_cents')) {
                $table->unsignedBigInteger('provider_payout_cents')->nullable()->after('platform_fee_cents');
            }
            // stripe_transfer_id — set once the Stripe Connect transfer is created
            if (! Schema::hasColumn('bookings', 'stripe_transfer_id')) {
                $table->string('stripe_transfer_id', 128)->nullable()->after('provider_payout_cents');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            foreach (['payout_status', 'provider_payout_cents', 'stripe_transfer_id'] as $col) {
                if (Schema::hasColumn('bookings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
