<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema-drift repair for App\Models\Task::completed_at.
 *
 * The model declares completed_at as fillable and casts it to datetime but
 * the tasks table has no such column. Added defensively as a nullable
 * timestamp.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tasks')) {
            return;
        }

        Schema::table('tasks', function (Blueprint $table) {
            if (! Schema::hasColumn('tasks', 'completed_at')) {
                $table->timestamp('completed_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tasks')) {
            return;
        }

        Schema::table('tasks', function (Blueprint $table) {
            if (Schema::hasColumn('tasks', 'completed_at')) {
                $table->dropColumn('completed_at');
            }
        });
    }
};
