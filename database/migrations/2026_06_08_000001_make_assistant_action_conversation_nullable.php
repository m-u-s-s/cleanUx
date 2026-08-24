<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** H1 — AssistantActionExecutor::executeWrite() supports standalone actions not tied to a conversation (its signature is `?AssistantConversation $conversation = null`), but assistant_actions.assistant_conversation_id was NOT NULL, so any write action triggered outside a chat conversation failed with an integrity-constraint violation. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('assistant_actions', 'assistant_conversation_id')) {
            return;
        }

        Schema::table('assistant_actions', function (Blueprint $table) {
            $table->foreignId('assistant_conversation_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Intentionally not reverting to NOT NULL: doing so would fail if any standalone
        // (conversation-less) actions exist, and the original constraint was the bug.
    }
};
