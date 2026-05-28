<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema-drift repair: create the `conversation_messages` table.
 *
 * App\Models\ConversationMessage is used by
 * App\Notifications\NewConversationMessageNotification and the
 * Conversation hasMany relation, but no migration ever created its table.
 * Columns are derived from the model's $fillable / $casts.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('conversation_messages')) {
            Schema::create('conversation_messages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('conversation_id')->nullable()->index();
                $table->unsignedBigInteger('sender_id')->nullable()->index();
                $table->text('message')->nullable();
                $table->json('attachments')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_messages');
    }
};
