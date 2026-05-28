<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema-drift repair for App\Models\GoogleCalendarEventLink.
 *
 * The model declares google_calendar_id, etag and last_error as fillable but
 * the google_calendar_event_links table is missing the columns. Added
 * defensively (last_error as text for error payloads, the rest as strings).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('google_calendar_event_links')) {
            return;
        }

        Schema::table('google_calendar_event_links', function (Blueprint $table) {
            if (! Schema::hasColumn('google_calendar_event_links', 'google_calendar_id')) {
                $table->string('google_calendar_id')->nullable();
            }
            if (! Schema::hasColumn('google_calendar_event_links', 'etag')) {
                $table->string('etag')->nullable();
            }
            if (! Schema::hasColumn('google_calendar_event_links', 'last_error')) {
                $table->text('last_error')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('google_calendar_event_links')) {
            return;
        }

        Schema::table('google_calendar_event_links', function (Blueprint $table) {
            foreach (['google_calendar_id', 'etag', 'last_error'] as $col) {
                if (Schema::hasColumn('google_calendar_event_links', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
