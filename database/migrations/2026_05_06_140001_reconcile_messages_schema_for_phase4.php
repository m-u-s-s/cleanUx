<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 — Réconciliation du schéma `messages`.
 *
 * BUG CRITIQUE détecté à l'audit : le model App\Models\Message utilise
 *   user_id, content, parent_id
 * mais la migration v2 a créé :
 *   sender_id, body, (pas de parent_id)
 *
 * Conséquence : en production, le chat équipe lance des erreurs
 *   SQLSTATE: Column 'user_id' not found  /  Column 'content' not found
 *
 * Cette migration aligne la structure DB sur le code (et sur les
 * conventions Laravel), TOUT en migrant les données existantes :
 *   - sender_id  → user_id  (renommé)
 *   - body       → content  (renommé)
 *   - + parent_id (NEW pour les threads)
 *   - + soft_deletes (NEW)
 *
 * Idempotente : utilise hasColumn pour ne rien casser si déjà appliquée.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Ajouter user_id, copier sender_id dedans, supprimer sender_id
        if (Schema::hasColumn('messages', 'sender_id') && ! Schema::hasColumn('messages', 'user_id')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->foreignId('user_id')
                    ->nullable()
                    ->after('channel_id')
                    ->constrained('users')
                    ->nullOnDelete();
            });

            // Copier les valeurs sender_id → user_id
            DB::statement('UPDATE messages SET user_id = sender_id WHERE user_id IS NULL AND sender_id IS NOT NULL');

            Schema::table('messages', function (Blueprint $table) {
                $table->dropConstrainedForeignId('sender_id');
            });
        }

        // 2. Renommer body → content
        if (Schema::hasColumn('messages', 'body') && ! Schema::hasColumn('messages', 'content')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->renameColumn('body', 'content');
            });
        }

        // 3. Ajouter parent_id pour les threads
        if (! Schema::hasColumn('messages', 'parent_id')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->foreignId('parent_id')
                    ->nullable()
                    ->after('content')
                    ->constrained('messages')
                    ->nullOnDelete();

                $table->index(['parent_id', 'created_at'], 'messages_parent_id_created_at_idx');
            });
        }

        // 4. Stats threads (denormalized for perf)
        if (! Schema::hasColumn('messages', 'replies_count')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->unsignedInteger('replies_count')->default(0);
                $table->timestamp('last_reply_at')->nullable();
            });
        }

        // 5. Soft deletes
        if (! Schema::hasColumn('messages', 'deleted_at')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        // Reverse n'est pas strictement réversible (les données dans body/sender_id
        // ont été perdues lors du rename). On ne droppe que les ajouts purs.
        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'parent_id')) {
                try { $table->dropIndex('messages_parent_id_created_at_idx'); } catch (\Throwable $e) {}
                try { $table->dropConstrainedForeignId('parent_id'); } catch (\Throwable $e) {}
            }
            foreach (['replies_count', 'last_reply_at', 'deleted_at'] as $c) {
                if (Schema::hasColumn('messages', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
