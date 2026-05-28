<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema-drift repair for App\Models\MissionChecklistItem.
 *
 * The model declares completed_by_user_id (FK) and notes (free text) as
 * fillable but the mission_checklist_items table is missing both columns.
 * Added defensively with matching types.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mission_checklist_items')) {
            return;
        }

        Schema::table('mission_checklist_items', function (Blueprint $table) {
            if (! Schema::hasColumn('mission_checklist_items', 'completed_by_user_id')) {
                $table->unsignedBigInteger('completed_by_user_id')->nullable()->index();
            }
            if (! Schema::hasColumn('mission_checklist_items', 'notes')) {
                $table->text('notes')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('mission_checklist_items')) {
            return;
        }

        Schema::table('mission_checklist_items', function (Blueprint $table) {
            foreach (['completed_by_user_id', 'notes'] as $col) {
                if (Schema::hasColumn('mission_checklist_items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
