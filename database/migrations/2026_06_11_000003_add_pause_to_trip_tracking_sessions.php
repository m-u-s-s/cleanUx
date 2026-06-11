<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit LOW — pause du partage de position par le prestataire en cours de mission.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_tracking_sessions', function (Blueprint $table) {
            $table->boolean('is_paused')->default(false)->after('status');
            $table->timestamp('paused_at')->nullable()->after('is_paused');
        });
    }

    public function down(): void
    {
        Schema::table('trip_tracking_sessions', function (Blueprint $table) {
            $table->dropColumn(['is_paused', 'paused_at']);
        });
    }
};
