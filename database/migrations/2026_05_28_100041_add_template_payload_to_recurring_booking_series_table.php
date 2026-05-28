<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema-drift repair for App\Models\RecurringBookingSeries.
 *
 * template_payload is a real (array cast -> json) field that
 * App\Services\Client\Templates\ApplyRecurringTemplateService writes to when
 * the column exists (it otherwise nests the payload under metadata). Adding the
 * real column lets the service persist the template snapshot directly. The
 * other alias casts (start_date/end_date/settings/days_of_week) remain
 * allowlisted in config/schema_drift.php and are intentionally not added.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('recurring_booking_series')) {
            return;
        }

        Schema::table('recurring_booking_series', function (Blueprint $table) {
            if (! Schema::hasColumn('recurring_booking_series', 'template_payload')) {
                $table->json('template_payload')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('recurring_booking_series')) {
            return;
        }

        Schema::table('recurring_booking_series', function (Blueprint $table) {
            if (Schema::hasColumn('recurring_booking_series', 'template_payload')) {
                $table->dropColumn('template_payload');
            }
        });
    }
};
