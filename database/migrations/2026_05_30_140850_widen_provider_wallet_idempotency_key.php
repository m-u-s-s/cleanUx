<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Widen provider_wallet_transactions.idempotency_key from VARCHAR(64) to VARCHAR(128). */
return new class extends Migration
{
    public function up(): void
    {
        // Drop the existing unique index before widening so that ->change() does
        // not attempt to create a duplicate index (the original create migration
        // already created provider_wallet_transactions_idempotency_key_unique).
        Schema::table('provider_wallet_transactions', function (Blueprint $table) {
            $table->dropUnique('provider_wallet_transactions_idempotency_key_unique');
        });

        Schema::table('provider_wallet_transactions', function (Blueprint $table) {
            $table->string('idempotency_key', 128)->nullable()->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('provider_wallet_transactions', function (Blueprint $table) {
            $table->dropUnique('provider_wallet_transactions_idempotency_key_unique');
        });

        Schema::table('provider_wallet_transactions', function (Blueprint $table) {
            $table->string('idempotency_key', 64)->nullable()->unique()->change();
        });
    }
};
