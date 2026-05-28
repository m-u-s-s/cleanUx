<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema-drift repair for App\Models\Mission::client_final_status.
 *
 * The model declares client_final_status as fillable and it is written by
 * App\Services\Mission\MissionQualityService when the client validates the
 * mission, but the missions table has no such column. Added defensively as a
 * nullable string (it stores a status value, e.g. validated/rejected).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('missions')) {
            return;
        }

        Schema::table('missions', function (Blueprint $table) {
            if (! Schema::hasColumn('missions', 'client_final_status')) {
                $table->string('client_final_status')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('missions')) {
            return;
        }

        Schema::table('missions', function (Blueprint $table) {
            if (Schema::hasColumn('missions', 'client_final_status')) {
                $table->dropColumn('client_final_status');
            }
        });
    }
};
