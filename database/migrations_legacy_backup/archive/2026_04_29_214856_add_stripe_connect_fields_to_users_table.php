<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {

            if (! Schema::hasColumn('users', 'stripe_connect_account_id')) {
                $table->string('stripe_connect_account_id')->nullable()->after('stripe_id');
            }

            if (! Schema::hasColumn('users', 'stripe_connect_status')) {
                $table->string('stripe_connect_status')
                    ->default('not_connected')
                    ->after('stripe_connect_account_id');
            }

            if (! Schema::hasColumn('users', 'stripe_connect_onboarded_at')) {
                $table->timestamp('stripe_connect_onboarded_at')->nullable();
            }

            if (! Schema::hasColumn('users', 'stripe_connect_charges_enabled_at')) {
                $table->timestamp('stripe_connect_charges_enabled_at')->nullable();
            }

            if (! Schema::hasColumn('users', 'stripe_connect_payouts_enabled_at')) {
                $table->timestamp('stripe_connect_payouts_enabled_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_connect_account_id',
                'stripe_connect_status',
                'stripe_connect_onboarded_at',
                'stripe_connect_charges_enabled_at',
                'stripe_connect_payouts_enabled_at',
            ]);
        });
    }
};
