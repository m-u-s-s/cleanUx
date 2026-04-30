<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rendez_vous', function (Blueprint $table) {

            if (!Schema::hasColumn('rendez_vous', 'stripe_payment_intent_id')) {
                $table->string('stripe_payment_intent_id')->nullable()->after('devis_estime');
            }

            if (!Schema::hasColumn('rendez_vous', 'stripe_connect_account_id')) {
                $table->string('stripe_connect_account_id')->nullable();
            }

            if (!Schema::hasColumn('rendez_vous', 'payment_amount_cents')) {
                $table->unsignedInteger('payment_amount_cents')->nullable();
            }

            if (!Schema::hasColumn('rendez_vous', 'platform_fee_cents')) {
                $table->unsignedInteger('platform_fee_cents')->nullable();
            }

            if (!Schema::hasColumn('rendez_vous', 'provider_amount_cents')) {
                $table->unsignedInteger('provider_amount_cents')->nullable();
            }

            // ⚠️ déjà existant → NE PAS recréer
            // payment_status

            if (!Schema::hasColumn('rendez_vous', 'payment_authorized_at')) {
                $table->timestamp('payment_authorized_at')->nullable();
            }

            if (!Schema::hasColumn('rendez_vous', 'payment_captured_at')) {
                $table->timestamp('payment_captured_at')->nullable();
            }

            if (!Schema::hasColumn('rendez_vous', 'payment_failed_at')) {
                $table->timestamp('payment_failed_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('rendez_vous', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_payment_intent_id',
                'stripe_connect_account_id',
                'payment_amount_cents',
                'platform_fee_cents',
                'provider_amount_cents',
                'payment_status',
                'payment_authorized_at',
                'payment_captured_at',
                'payment_failed_at',
            ]);
        });
    }
};
