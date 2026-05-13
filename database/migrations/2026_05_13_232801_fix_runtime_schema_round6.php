<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('conversations')) {
            Schema::table('conversations', function (Blueprint $table) {
                if (! Schema::hasColumn('conversations', 'mission_id')) {
                    $table->unsignedBigInteger('mission_id')->nullable()->index()->after('rendez_vous_id');
                }

                if (! Schema::hasColumn('conversations', 'channel_id')) {
                    $table->unsignedBigInteger('channel_id')->nullable()->index();
                }

                if (! Schema::hasColumn('conversations', 'created_by_user_id')) {
                    $table->unsignedBigInteger('created_by_user_id')->nullable()->index();
                }
            });
        }

        if (Schema::hasTable('finance_quotes')) {
            Schema::table('finance_quotes', function (Blueprint $table) {
                if (! Schema::hasColumn('finance_quotes', 'tax_rate')) {
                    $table->decimal('tax_rate', 8, 2)->default(0)->after('subtotal');
                }

                if (! Schema::hasColumn('finance_quotes', 'tax_amount')) {
                    $table->decimal('tax_amount', 10, 2)->default(0)->after('tax_rate');
                }

                if (! Schema::hasColumn('finance_quotes', 'total_amount')) {
                    $table->decimal('total_amount', 10, 2)->default(0)->after('tax_amount');
                }

                if (! Schema::hasColumn('finance_quotes', 'issued_at')) {
                    $table->timestamp('issued_at')->nullable();
                }

                if (! Schema::hasColumn('finance_quotes', 'valid_until')) {
                    $table->timestamp('valid_until')->nullable();
                }

                if (! Schema::hasColumn('finance_quotes', 'accepted_at')) {
                    $table->timestamp('accepted_at')->nullable();
                }

                if (! Schema::hasColumn('finance_quotes', 'snapshot')) {
                    $table->json('snapshot')->nullable();
                }

                if (! Schema::hasColumn('finance_quotes', 'meta')) {
                    $table->json('meta')->nullable();
                }
            });
        }

        if (Schema::hasTable('finance_invoices')) {
            Schema::table('finance_invoices', function (Blueprint $table) {
                if (! Schema::hasColumn('finance_invoices', 'tax_rate')) {
                    $table->decimal('tax_rate', 8, 2)->default(0)->after('subtotal');
                }

                if (! Schema::hasColumn('finance_invoices', 'tax_amount')) {
                    $table->decimal('tax_amount', 10, 2)->default(0)->after('tax_rate');
                }

                if (! Schema::hasColumn('finance_invoices', 'total_amount')) {
                    $table->decimal('total_amount', 10, 2)->default(0)->after('tax_amount');
                }

                if (! Schema::hasColumn('finance_invoices', 'paid_amount')) {
                    $table->decimal('paid_amount', 10, 2)->default(0);
                }

                if (! Schema::hasColumn('finance_invoices', 'balance_due')) {
                    $table->decimal('balance_due', 10, 2)->default(0);
                }

                if (! Schema::hasColumn('finance_invoices', 'snapshot')) {
                    $table->json('snapshot')->nullable();
                }

                if (! Schema::hasColumn('finance_invoices', 'meta')) {
                    $table->json('meta')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        //
    }
};