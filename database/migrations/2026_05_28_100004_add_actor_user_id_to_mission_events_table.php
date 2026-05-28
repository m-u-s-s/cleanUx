<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds actor_user_id to mission_events.
 *
 * MissionEvent model + MissionHistoryService::log() write actor_user_id, but
 * the table was created (2026_05_04) with a user_id column instead, so
 * completeMission() 500s on the history log. Added defensively; the legacy
 * user_id column is left in place (nullable, unused).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mission_events')) {
            return;
        }

        Schema::table('mission_events', function (Blueprint $table) {
            if (! Schema::hasColumn('mission_events', 'actor_user_id')) {
                $table->unsignedBigInteger('actor_user_id')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('mission_events')) {
            return;
        }

        Schema::table('mission_events', function (Blueprint $table) {
            if (Schema::hasColumn('mission_events', 'actor_user_id')) {
                $table->dropColumn('actor_user_id');
            }
        });
    }
};
