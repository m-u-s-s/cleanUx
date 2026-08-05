<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LE PIVOT DES ASSIGNATIONS N'AVAIT PAS LES COLONNES QU'ON Y ÉCRIVAIT.
 *
 * `Task::assignees()` déclare `withPivot(['assigned_by', 'assigned_at'])` et `TaskBoard::createTask()`
 * les renseigne à l'attachement. La table, elle, ne portait que `task_id`, `user_id` et `status`.
 *
 * Conséquence : lire les assignés d'une tâche produisait « no such column: task_assignees.assigned_by »,
 * et en attacher un échouait de même. Toute la fonction d'assignation du tableau de tâches était
 * inopérante — un tableau d'équipe sans assignation.
 *
 * Migration purement ADDITIVE : deux colonnes nullables, aucune donnée existante touchée.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('task_assignees')) {
            return;
        }

        Schema::table('task_assignees', function (Blueprint $table) {
            // Idempotente : rejouable sans erreur sur une base déjà corrigée.
            if (! Schema::hasColumn('task_assignees', 'assigned_by')) {
                $table->foreignId('assigned_by')->nullable()->after('user_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('task_assignees', 'assigned_at')) {
                $table->timestamp('assigned_at')->nullable()->after('assigned_by');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('task_assignees')) {
            return;
        }

        Schema::table('task_assignees', function (Blueprint $table) {
            if (Schema::hasColumn('task_assignees', 'assigned_by')) {
                $table->dropConstrainedForeignId('assigned_by');
            }

            if (Schema::hasColumn('task_assignees', 'assigned_at')) {
                $table->dropColumn('assigned_at');
            }
        });
    }
};
