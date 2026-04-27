<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('finance_invoices', 'billing_period_start')) {
                $table->date('billing_period_start')->nullable()->after('organization_account_id');
            }

            if (! Schema::hasColumn('finance_invoices', 'billing_period_end')) {
                $table->date('billing_period_end')->nullable()->after('billing_period_start');
            }

            if (! Schema::hasColumn('finance_invoices', 'invoice_type')) {
                $table->string('invoice_type')->default('single')->after('invoice_number');
            }

            if (! Schema::hasColumn('finance_invoices', 'site_breakdown')) {
                $table->json('site_breakdown')->nullable()->after('snapshot');
            }
        });
    }

    public function down(): void
    {
        Schema::table('finance_invoices', function (Blueprint $table) {
            if (Schema::hasColumn('finance_invoices', 'billing_period_start')) {
                $table->dropColumn('billing_period_start');
            }

            if (Schema::hasColumn('finance_invoices', 'billing_period_end')) {
                $table->dropColumn('billing_period_end');
            }

            if (Schema::hasColumn('finance_invoices', 'invoice_type')) {
                $table->dropColumn('invoice_type');
            }

            if (Schema::hasColumn('finance_invoices', 'site_breakdown')) {
                $table->dropColumn('site_breakdown');
            }
        });
    }
};