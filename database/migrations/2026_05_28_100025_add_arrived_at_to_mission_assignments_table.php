<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema-drift repair for App\Models\MissionAssignment::arrived_at.
 *
 * The model declares arrived_at as fillable and casts it to datetime but the
 * mission_assignments table has no such column. Added defensively as a
 * nullable timestamp.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mission_assignments')) {
            return;
        }

        Schema::table('mission_assignments', function (Blueprint $table) {
            if (! Schema::hasColumn('mission_assignments', 'arrived_at')) {
                $table->timestamp('arrived_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('mission_assignments')) {
            return;
        }

        Schema::table('mission_assignments', function (Blueprint $table) {
            if (Schema::hasColumn('mission_assignments', 'arrived_at')) {
                $table->dropColumn('arrived_at');
            }
        });
    }
};
