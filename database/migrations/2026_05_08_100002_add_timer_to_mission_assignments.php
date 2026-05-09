<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 11 — Colonnes pour le flow accept/decline avec timer.
 *
 * Ajoute à mission_assignments :
 *   - notification_sent_at : quand on a notifié le prestataire (push)
 *   - expires_at : deadline pour répondre (notification_sent_at + 15s)
 *   - response_seconds : combien de temps il a mis à répondre (pour stats reliability)
 *   - decline_reason : motif optionnel de refus
 *   - escalated_from_assignment_id : ref vers l'assignment précédent qui a timeout
 *
 * Approche défensive : Schema::hasColumn pour idempotence.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('mission_assignments')) {
            return;
        }

        Schema::table('mission_assignments', function (Blueprint $table) {
            if (! Schema::hasColumn('mission_assignments', 'notification_sent_at')) {
                $table->timestamp('notification_sent_at')->nullable()->after('assigned_at');
            }
            if (! Schema::hasColumn('mission_assignments', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('notification_sent_at');
            }
            if (! Schema::hasColumn('mission_assignments', 'response_seconds')) {
                $table->unsignedSmallInteger('response_seconds')->nullable()->after('expires_at');
            }
            if (! Schema::hasColumn('mission_assignments', 'decline_reason')) {
                $table->string('decline_reason', 255)->nullable()->after('declined_at');
            }
            if (! Schema::hasColumn('mission_assignments', 'escalated_from_assignment_id')) {
                $table->foreignId('escalated_from_assignment_id')
                    ->nullable()
                    ->after('decline_reason')
                    ->constrained('mission_assignments')
                    ->nullOnDelete();
            }
        });

        // Index pour la recherche "assignments expirés à escalader"
        Schema::table('mission_assignments', function (Blueprint $table) {
            try {
                $table->index(['expires_at', 'assignment_status'], 'mission_assign_expiry_idx');
            } catch (\Throwable $e) {
                // Index existe déjà
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('mission_assignments')) {
            return;
        }

        Schema::table('mission_assignments', function (Blueprint $table) {
            try {
                $table->dropIndex('mission_assign_expiry_idx');
            } catch (\Throwable $e) {
                // n/a
            }

            // Drop FK avant la colonne
            if (Schema::hasColumn('mission_assignments', 'escalated_from_assignment_id')) {
                try {
                    $table->dropForeign(['escalated_from_assignment_id']);
                } catch (\Throwable $e) {
                    // n/a
                }
            }

            $columns = [
                'notification_sent_at',
                'expires_at',
                'response_seconds',
                'decline_reason',
                'escalated_from_assignment_id',
            ];

            foreach ($columns as $col) {
                if (Schema::hasColumn('mission_assignments', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
