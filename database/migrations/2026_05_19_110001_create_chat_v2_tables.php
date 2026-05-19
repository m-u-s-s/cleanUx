<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_threads', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('context_type', 64)->nullable();   // booking | dispute | admin | generic
            $table->unsignedBigInteger('context_id')->nullable();
            $table->string('title', 191)->nullable();
            $table->string('status', 24)->default('active');   // active | archived | locked
            $table->boolean('is_archived')->default(false);
            $table->timestamp('last_message_at')->nullable();
            $table->string('last_message_preview', 191)->nullable();
            $table->unsignedInteger('message_count')->default(0);
            $table->unsignedInteger('flagged_count')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['context_type', 'context_id']);
            $table->index(['status', 'last_message_at']);
        });

        Schema::create('chat_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('chat_threads')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 24);   // client | provider | admin | observer | system
            $table->boolean('is_muted')->default(false);
            $table->boolean('can_send')->default(true);
            $table->timestamp('joined_at');
            $table->timestamp('last_read_at')->nullable();
            $table->unsignedBigInteger('last_read_message_id')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            $table->unique(['thread_id', 'user_id'], 'chat_participants_thread_user_unique');
            $table->index(['user_id', 'last_read_at']);
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('chat_threads')->cascadeOnDelete();
            $table->foreignId('sender_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sender_role', 24);   // client | provider | admin | system
            $table->longText('body');
            $table->boolean('is_redacted')->default(false);
            $table->char('body_original_hash', 64)->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->foreignId('deleted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('attachment_path', 500)->nullable();
            $table->string('attachment_mime', 96)->nullable();
            $table->unsignedInteger('attachment_size_bytes')->nullable();
            $table->string('moderation_status', 16)->default('clean');   // clean | flagged | blocked
            $table->string('moderation_reason', 191)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['thread_id', 'created_at']);
            $table->index(['moderation_status']);
            $table->index(['sender_user_id', 'created_at']);
        });

        Schema::create('chat_message_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('chat_messages')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('read_at');
            $table->timestamps();

            $table->unique(['message_id', 'user_id'], 'chat_message_reads_msg_user_unique');
            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_message_reads');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_participants');
        Schema::dropIfExists('chat_threads');
    }
};
