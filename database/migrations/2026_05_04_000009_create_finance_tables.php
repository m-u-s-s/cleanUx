<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            $table->string('invoice_number')->unique();

            $table->foreignId('customer_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('customer_organization_id')
                ->nullable()
                ->constrained('organization_accounts')
                ->nullOnDelete();

            $table->foreignId('booking_id')
                ->nullable()
                ->constrained('bookings')
                ->nullOnDelete();

            $table->foreignId('mission_id')
                ->nullable()
                ->constrained('missions')
                ->nullOnDelete();

            // draft, issued, paid, overdue, cancelled.
            $table->string('status')->default('draft');

            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->string('currency', 3)->default('EUR');

            $table->date('issued_at')->nullable();
            $table->date('due_at')->nullable();
            $table->date('paid_at')->nullable();

            $table->string('pdf_path')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['customer_user_id', 'status']);
            $table->index(['customer_organization_id', 'status']);
            $table->index(['status', 'due_at']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('invoice_id')
                ->constrained('invoices')
                ->cascadeOnDelete();

            $table->string('label');
            $table->text('description')->nullable();

            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);

            $table->json('metadata')->nullable();

            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('invoice_id')
                ->nullable()
                ->constrained('invoices')
                ->nullOnDelete();

            $table->foreignId('booking_id')
                ->nullable()
                ->constrained('bookings')
                ->nullOnDelete();

            $table->foreignId('mission_id')
                ->nullable()
                ->constrained('missions')
                ->nullOnDelete();

            $table->foreignId('payer_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('payer_organization_id')
                ->nullable()
                ->constrained('organization_accounts')
                ->nullOnDelete();

            $table->string('provider')->default('stripe');
            $table->string('provider_payment_id')->nullable()->index();

            // pending, authorized, captured, failed, refunded.
            $table->string('status')->default('pending');

            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('EUR');

            $table->json('payload')->nullable();

            $table->timestamps();

            $table->index(['payer_user_id', 'status']);
            $table->index(['payer_organization_id', 'status']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('customer_credits', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_user_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('customer_organization_id')
                ->nullable()
                ->constrained('organization_accounts')
                ->cascadeOnDelete();

            $table->decimal('balance', 10, 2)->default(0);
            $table->string('currency', 3)->default('EUR');

            $table->timestamps();

            $table->index('customer_user_id');
            $table->index('customer_organization_id');
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
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
