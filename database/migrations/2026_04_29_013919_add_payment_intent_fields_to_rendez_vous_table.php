<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rendez_vous', function (Blueprint $table) {
            $table->string('payment_status')->default('unpaid')->after('status');
            $table->string('stripe_payment_intent_id')->nullable()->after('payment_status');
            $table->timestamp('payment_authorized_at')->nullable()->after('stripe_payment_intent_id');
            $table->timestamp('payment_captured_at')->nullable()->after('payment_authorized_at');
            $table->timestamp('payment_cancelled_at')->nullable()->after('payment_captured_at');
        });
    }

    public function down(): void
    {
        Schema::table('rendez_vous', function (Blueprint $table) {
            $table->dropColumn([
                'payment_status',
                'stripe_payment_intent_id',
                'payment_authorized_at',
                'payment_captured_at',
                'payment_cancelled_at',
            ]);
        });
    }
};
