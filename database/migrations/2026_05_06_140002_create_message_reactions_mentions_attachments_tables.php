<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 — Tables auxiliaires pour la communication avancée :
 *   - message_reactions : emoji reactions
 *   - message_attachments : pièces jointes (avec scan AV optionnel)
 *   - message_mentions : @user mentions extraites pour notifs ciblées
 *
 * Le model MessageReaction existait dans le code (référencé par Message::reactions)
 * mais SA TABLE N'EXISTAIT PAS — autre bug critique fixé ici.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ──────────────────────────────────────────────────────
        // Reactions emoji (👍 ❤️ 🔥 etc.)
        // ──────────────────────────────────────────────────────
        if (! Schema::hasTable('message_reactions')) {
            Schema::create('message_reactions', function (Blueprint $table) {
                $table->id();

                $table->foreignId('message_id')
                    ->constrained('messages')
                    ->cascadeOnDelete();

                $table->foreignId('user_id')
                    ->constrained('users')
                    ->cascadeOnDelete();

                $table->string('emoji', 32); // unicode ou shortcode

                $table->timestamps();

                // Un user ne peut mettre qu'une fois la même réaction sur un message
                $table->unique(['message_id', 'user_id', 'emoji'], 'msg_react_unique');
                $table->index(['message_id', 'emoji']);
            });
        }

        // ──────────────────────────────────────────────────────
        // Mentions @user
        // ──────────────────────────────────────────────────────
        if (! Schema::hasTable('message_mentions')) {
            Schema::create('message_mentions', function (Blueprint $table) {
                $table->id();

                $table->foreignId('message_id')
                    ->constrained('messages')
                    ->cascadeOnDelete();

                // L'utilisateur mentionné (NULL si mention spéciale ex: @here, @channel)
                $table->foreignId('mentioned_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->cascadeOnDelete();

                // Type spécial : "user" | "here" | "channel" | "team"
                $table->string('mention_type', 16)->default('user');

                // Position (offset char) dans le content — utile pour rendu côté client
                $table->unsignedInteger('start_offset')->nullable();
                $table->unsignedInteger('length')->nullable();

                // Lue par l'utilisateur mentionné ?
                $table->timestamp('read_at')->nullable();

                $table->timestamps();

                $table->index(['mentioned_user_id', 'read_at'], 'mentions_user_unread_idx');
                $table->index('message_id');
            });
        }

        // ──────────────────────────────────────────────────────
        // Pièces jointes
        // ──────────────────────────────────────────────────────
        if (! Schema::hasTable('message_attachments')) {
            Schema::create('message_attachments', function (Blueprint $table) {
                $table->id();

                $table->foreignId('message_id')
                    ->constrained('messages')
                    ->cascadeOnDelete();

                $table->foreignId('uploaded_by')
                    ->constrained('users')
                    ->cascadeOnDelete();

                // Storage
                $table->string('disk', 32)->default('public');
                $table->string('path');                          // chemin sur le disk
                $table->string('original_name');                 // nom déposé par le user
                $table->string('mime_type', 100)->nullable();
                $table->unsignedBigInteger('size_bytes')->nullable();

                // Image-only (pour preview)
                $table->unsignedInteger('image_width')->nullable();
                $table->unsignedInteger('image_height')->nullable();
                $table->string('thumbnail_path')->nullable();

                // Anti-malware (si activé)
                // pending | clean | infected | error
                $table->string('av_status', 20)->default('pending');
                $table->string('av_engine', 64)->nullable();
                $table->timestamp('av_scanned_at')->nullable();
                $table->text('av_details')->nullable();

                $table->json('metadata')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->index(['message_id', 'av_status']);
                $table->index('uploaded_by');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('message_attachments');
        Schema::dropIfExists('message_mentions');
        Schema::dropIfExists('message_reactions');
    }
};
