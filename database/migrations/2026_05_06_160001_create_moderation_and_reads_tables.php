<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4.1 — Tables et colonnes pour la modération des canaux et messages.
 *
 *   - messages.deleted_by         : qui a supprimé (auteur, mod, admin)
 *   - messages.deleted_reason     : motif modération
 *   - messages.is_pinned          : épinglé en tête de canal
 *   - messages.pinned_at / by     : audit pin
 *
 *   - channels.is_archived        : archivé (lecture seule)
 *   - channels.is_locked          : verrouillé (modos+ peuvent encore poster)
 *   - channels.archived_at / by   : audit
 *
 *   - message_reads               : lectures par utilisateur (read receipts)
 *   - moderation_actions          : journal d'actions modération
 *
 * Tout est additif et idempotent (Schema::hasColumn / hasTable).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ──────────────────────────────────────────────────────
        // messages : colonnes modération
        // ──────────────────────────────────────────────────────
        Schema::table('messages', function (Blueprint $table) {
            if (! Schema::hasColumn('messages', 'deleted_by')) {
                $table->foreignId('deleted_by')
                    ->nullable()
                    ->after('deleted_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('messages', 'deleted_reason')) {
                $table->string('deleted_reason', 255)->nullable()->after('deleted_by');
            }
            if (! Schema::hasColumn('messages', 'is_pinned')) {
                $table->boolean('is_pinned')->default(false)->after('deleted_reason');
            }
            if (! Schema::hasColumn('messages', 'pinned_at')) {
                $table->timestamp('pinned_at')->nullable()->after('is_pinned');
            }
            if (! Schema::hasColumn('messages', 'pinned_by')) {
                $table->foreignId('pinned_by')
                    ->nullable()
                    ->after('pinned_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });

        Schema::table('messages', function (Blueprint $table) {
            try {
                $table->index(['channel_id', 'is_pinned'], 'messages_channel_pinned_idx');
            } catch (\Throwable $e) {
                // index probablement déjà présent
            }
        });

        // ──────────────────────────────────────────────────────
        // channels : drapeaux archivage / lock
        // ──────────────────────────────────────────────────────
        Schema::table('channels', function (Blueprint $table) {
            if (! Schema::hasColumn('channels', 'is_archived')) {
                $table->boolean('is_archived')->default(false);
            }
            if (! Schema::hasColumn('channels', 'is_locked')) {
                $table->boolean('is_locked')->default(false);
            }
            if (! Schema::hasColumn('channels', 'archived_at')) {
                $table->timestamp('archived_at')->nullable();
            }
            if (! Schema::hasColumn('channels', 'archived_by')) {
                $table->foreignId('archived_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });

        // ──────────────────────────────────────────────────────
        // message_reads : lectures par utilisateur
        // ──────────────────────────────────────────────────────
        if (! Schema::hasTable('message_reads')) {
            Schema::create('message_reads', function (Blueprint $table) {
                $table->id();

                $table->foreignId('message_id')
                    ->constrained('messages')
                    ->cascadeOnDelete();

                $table->foreignId('user_id')
                    ->constrained('users')
                    ->cascadeOnDelete();

                $table->timestamp('read_at')->useCurrent();

                $table->unique(['message_id', 'user_id']);
                $table->index(['user_id', 'read_at']);
            });
        }

        // ──────────────────────────────────────────────────────
        // moderation_actions : journal d'audit modération
        // ──────────────────────────────────────────────────────
        if (! Schema::hasTable('moderation_actions')) {
            Schema::create('moderation_actions', function (Blueprint $table) {
                $table->id();

                $table->foreignId('actor_user_id')
                    ->constrained('users')
                    ->cascadeOnDelete();

                $table->foreignId('channel_id')
                    ->nullable()
                    ->constrained('channels')
                    ->cascadeOnDelete();

                $table->foreignId('message_id')
                    ->nullable()
                    ->constrained('messages')
                    ->nullOnDelete();

                $table->foreignId('target_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                // delete_message, pin_message, unpin_message, lock_channel,
                // archive_channel, kick_member, mute_member, role_change
                $table->string('action_type', 32);

                $table->string('reason', 500)->nullable();
                $table->json('payload')->nullable();

                $table->timestamps();

                $table->index(['channel_id', 'created_at']);
                $table->index(['actor_user_id', 'created_at']);
                $table->index('action_type');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_actions');
        Schema::dropIfExists('message_reads');

        Schema::table('messages', function (Blueprint $table) {
            try { $table->dropIndex('messages_channel_pinned_idx'); } catch (\Throwable $e) {}
            foreach (['deleted_by', 'deleted_reason', 'is_pinned', 'pinned_at', 'pinned_by'] as $c) {
                if (Schema::hasColumn('messages', $c)) {
                    if (in_array($c, ['deleted_by', 'pinned_by'])) {
                        $table->dropConstrainedForeignId($c);
                    } else {
                        $table->dropColumn($c);
                    }
                }
            }
        });

        Schema::table('channels', function (Blueprint $table) {
            foreach (['is_archived', 'is_locked', 'archived_at', 'archived_by'] as $c) {
                if (Schema::hasColumn('channels', $c)) {
                    if ($c === 'archived_by') {
                        $table->dropConstrainedForeignId($c);
                    } else {
                        $table->dropColumn($c);
                    }
                }
            }
        });
    }
};
