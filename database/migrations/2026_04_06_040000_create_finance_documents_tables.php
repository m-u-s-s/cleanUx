<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_quotes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rendez_vous_id')->unique();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('organization_account_id')->nullable();
            $table->string('quote_number')->unique();
            $table->string('status')->default('draft');
            $table->string('currency', 3)->default('EUR');
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(21.00);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->json('snapshot')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('rendez_vous_id', 'fin_quote_rdv_fk')->references('id')->on('rendez_vous')->cascadeOnDelete();
            $table->foreign('client_id', 'fin_quote_client_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('organization_account_id', 'fin_quote_org_fk')->references('id')->on('organization_accounts')->nullOnDelete();
            $table->index(['status', 'issued_at'], 'fin_quote_status_idx');
        });

        Schema::create('finance_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rendez_vous_id')->unique();
            $table->unsignedBigInteger('finance_quote_id')->nullable();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('organization_account_id')->nullable();
            $table->string('invoice_number')->unique();
            $table->string('status')->default('draft');
            $table->string('currency', 3)->default('EUR');
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(21.00);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('balance_due', 10, 2)->default(0);
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('snapshot')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('rendez_vous_id', 'fin_inv_rdv_fk')->references('id')->on('rendez_vous')->cascadeOnDelete();
            $table->foreign('finance_quote_id', 'fin_inv_quote_fk')->references('id')->on('finance_quotes')->nullOnDelete();
            $table->foreign('client_id', 'fin_inv_client_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('organization_account_id', 'fin_inv_org_fk')->references('id')->on('organization_accounts')->nullOnDelete();
            $table->index(['status', 'due_at'], 'fin_inv_status_due_idx');
        });

        Schema::create('finance_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('finance_invoice_id');
            $table->string('payment_reference')->nullable()->unique();
            $table->string('provider')->nullable();
            $table->string('method')->nullable();
            $table->string('status')->default('recorded');
            $table->decimal('amount', 10, 2)->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->string('external_reference')->nullable();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('finance_invoice_id', 'fin_pay_inv_fk')->references('id')->on('finance_invoices')->cascadeOnDelete();
            $table->index(['status', 'paid_at'], 'fin_pay_status_idx');
        });

        Schema::create('finance_reminders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('finance_invoice_id');
            $table->string('reminder_type')->default('gentle');
            $table->string('channel')->default('mail');
            $table->string('status')->default('pending');
            $table->string('recipient_email')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('finance_invoice_id', 'fin_rem_inv_fk')->references('id')->on('finance_invoices')->cascadeOnDelete();
            $table->index(['status', 'sent_at'], 'fin_rem_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_reminders');
        Schema::dropIfExists('finance_payments');
        Schema::dropIfExists('finance_invoices');
        Schema::dropIfExists('finance_quotes');
    }
};
