<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_entries', function (Blueprint $table) {
            $table->id();
            $table->string('entry_code', 64)->unique();
            $table->string('batch_id', 64);   // group de lignes formant 1 transaction équilibrée
            $table->date('posting_date');
            $table->string('journal_code', 8);  // VEN | ACH | BANK | OD | INV
            $table->string('account_code', 16);
            $table->string('account_name', 191)->nullable();
            $table->unsignedBigInteger('debit_cents')->default(0);
            $table->unsignedBigInteger('credit_cents')->default(0);
            $table->string('label', 500);
            $table->string('reference', 191)->nullable();   // numéro facture/booking
            $table->string('currency', 3)->default('EUR');
            $table->decimal('exchange_rate', 12, 6)->nullable();
            $table->decimal('vat_rate', 5, 2)->nullable();
            $table->unsignedBigInteger('vat_amount_cents')->nullable();
            $table->string('source_type', 64)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('posted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('counterparty_type', 24)->nullable();   // client | provider | platform | stripe
            $table->unsignedBigInteger('counterparty_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['batch_id']);
            $table->index(['posting_date', 'journal_code']);
            $table->index(['account_code', 'posting_date']);
            $table->index(['source_type', 'source_id']);
            $table->index(['counterparty_type', 'counterparty_id']);
        });

        Schema::create('accounting_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');   // 1..12 ; 0 = annual rolling
            $table->boolean('is_closed')->default(false);
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('total_debit_cents')->default(0);
            $table->unsignedBigInteger('total_credit_cents')->default(0);
            $table->unsignedInteger('entry_count')->default(0);
            $table->json('totals_by_account')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['period_year', 'period_month'], 'accounting_periods_year_month_unique');
            $table->index(['is_closed']);
        });

        Schema::create('accounting_exports', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('format', 24);   // csv | fec | sage | quickbooks_iif | xml
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month')->nullable();
            $table->string('status', 16)->default('pending');  // pending | ready | failed | expired
            $table->string('file_path', 500)->nullable();
            $table->unsignedInteger('file_size_bytes')->nullable();
            $table->char('file_hash', 64)->nullable();
            $table->unsignedInteger('row_count')->default(0);
            $table->foreignId('generated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['format', 'period_year', 'period_month'], 'accounting_exports_filter_idx');
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_exports');
        Schema::dropIfExists('accounting_periods');
        Schema::dropIfExists('accounting_entries');
    }
};
