<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('customer_credits', function (Blueprint $table) {
            $table->id();

            $table->decimal('balance', 10, 2)->default(0);
            $table->string('currency', 3)->default('EUR');

            $table->timestamps();

        });

        Schema::create('customer_credit_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_credit_id')
                ->constrained('customer_credits')
                ->cascadeOnDelete();

            $table->foreignId('booking_id')
                ->nullable()
                ->constrained('bookings')
                ->nullOnDelete();

            // credit, debit, refund, adjustment.
            $table->string('type');

            $table->decimal('amount', 10, 2);
            $table->text('description')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['customer_credit_id', 'created_at']);
        });

        Schema::create('provider_payouts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('provider_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('provider_organization_id')
                ->nullable()
                ->constrained('organization_accounts')
                ->nullOnDelete();

            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('EUR');

            // pending, processing, paid, failed.
            $table->string('status')->default('pending');

            $table->string('provider')->default('stripe_connect');
            $table->string('provider_payout_id')->nullable();

            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['provider_user_id', 'status']);
            $table->index(['provider_organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_payouts');
        Schema::dropIfExists('customer_credit_transactions');
        Schema::dropIfExists('customer_credits');
    }
};
