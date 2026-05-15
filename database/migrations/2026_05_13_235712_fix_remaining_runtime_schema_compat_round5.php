<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Analytics
        if (Schema::hasTable('mission_quality_reviews')) {
            Schema::table('mission_quality_reviews', function (Blueprint $table) {
                if (! Schema::hasColumn('mission_quality_reviews', 'overall_rating')) {
                    $table->unsignedTinyInteger('overall_rating')->nullable()->after('mission_id');
                }
            });
        }

        // Finance invoices B2B
        if (Schema::hasTable('finance_invoices')) {
            Schema::table('finance_invoices', function (Blueprint $table) {
                if (! Schema::hasColumn('finance_invoices', 'invoice_type')) {
                    $table->string('invoice_type')->nullable()->after('invoice_number');
                }

                if (! Schema::hasColumn('finance_invoices', 'balance_due')) {
                    $table->decimal('balance_due', 10, 2)->default(0)->after('total_amount');
                }

                if (! Schema::hasColumn('finance_invoices', 'billing_period_start')) {
                    $table->dateTime('billing_period_start')->nullable()->after('due_at');
                }

                if (! Schema::hasColumn('finance_invoices', 'billing_period_end')) {
                    $table->dateTime('billing_period_end')->nullable()->after('billing_period_start');
                }

                if (! Schema::hasColumn('finance_invoices', 'site_breakdown')) {
                    $table->json('site_breakdown')->nullable()->after('billing_period_end');
                }
            });
        }

        // Google Calendar connections
        if (Schema::hasTable('google_calendar_connections')) {
            Schema::table('google_calendar_connections', function (Blueprint $table) {
                if (! Schema::hasColumn('google_calendar_connections', 'google_email')) {
                    $table->string('google_email')->nullable()->after('user_id');
                }

                if (! Schema::hasColumn('google_calendar_connections', 'google_user_id')) {
                    $table->string('google_user_id')->nullable()->after('google_email');
                }

                if (! Schema::hasColumn('google_calendar_connections', 'calendar_id')) {
                    $table->string('calendar_id')->nullable()->after('token_expires_at');
                }

                if (! Schema::hasColumn('google_calendar_connections', 'sync_enabled')) {
                    $table->boolean('sync_enabled')->default(true)->after('calendar_id');
                }

                if (! Schema::hasColumn('google_calendar_connections', 'last_synced_at')) {
                    $table->timestamp('last_synced_at')->nullable()->after('sync_enabled');
                }

                if (! Schema::hasColumn('google_calendar_connections', 'last_sync_status')) {
                    $table->string('last_sync_status')->nullable()->after('last_synced_at');
                }

                if (! Schema::hasColumn('google_calendar_connections', 'last_sync_error')) {
                    $table->text('last_sync_error')->nullable()->after('last_sync_status');
                }
            });
        }

        // Location cache
        if (Schema::hasTable('location_geocodes')) {
            Schema::table('location_geocodes', function (Blueprint $table) {
                if (! Schema::hasColumn('location_geocodes', 'address_hash')) {
                    $table->string('address_hash')->nullable()->after('id');
                }

                if (! Schema::hasColumn('location_geocodes', 'lookup_hash')) {
                    $table->string('lookup_hash')->nullable()->after('address_hash');
                }

                if (! Schema::hasColumn('location_geocodes', 'raw')) {
                    $table->json('raw')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        //
    }
};