<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema-drift repair for App\Models\GoogleCalendarConnection.
 *
 * The model declares scope (string) and meta (cast array) as fillable but
 * the google_calendar_connections table is missing both columns. Added
 * defensively with matching types.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('google_calendar_connections')) {
            return;
        }

        Schema::table('google_calendar_connections', function (Blueprint $table) {
            if (! Schema::hasColumn('google_calendar_connections', 'scope')) {
                $table->string('scope')->nullable();
            }
            if (! Schema::hasColumn('google_calendar_connections', 'meta')) {
                $table->json('meta')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('google_calendar_connections')) {
            return;
        }

        Schema::table('google_calendar_connections', function (Blueprint $table) {
            foreach (['scope', 'meta'] as $col) {
                if (Schema::hasColumn('google_calendar_connections', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
