<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedbacks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('booking_id')
                ->nullable()
                ->constrained('bookings')
                ->cascadeOnDelete();

            $table->foreignId('mission_id')
                ->nullable()
                ->constrained('missions')
                ->cascadeOnDelete();

            $table->foreignId('client_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('client_organization_id')
                ->nullable()
                ->constrained('organization_accounts')
                ->nullOnDelete();

            $table->unsignedTinyInteger('note');
            $table->text('commentaire')->nullable();

            $table->text('reponse_admin')->nullable();

            $table->foreignId('answered_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('answered_at')->nullable();

            // published, hidden, archived.
            $table->string('status')->default('published');

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['mission_id', 'note']);
            $table->index(['client_user_id', 'created_at']);
            $table->index(['client_organization_id', 'created_at']);
        });

        Schema::create('channels', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_account_id')
                ->nullable()
                ->constrained('organization_accounts')
                ->cascadeOnDelete();

            $table->foreignId('mission_id')
                ->nullable()
                ->constrained('missions')
                ->cascadeOnDelete();

            $table->foreignId('booking_id')
                ->nullable()
                ->constrained('bookings')
                ->cascadeOnDelete();

            $table->string('name');

            // team, mission, support, private, announcement.
            $table->string('type')->default('team');

            $table->boolean('is_private')->default(false);

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->json('settings')->nullable();

            $table->timestamps();

            $table->index(['organization_account_id', 'type']);
            $table->index(['mission_id', 'type']);
            $table->index(['booking_id', 'type']);
        });

        Schema::create('channel_members', function (Blueprint $table) {
            $table->id();

            $table->foreignId('channel_id')
                ->constrained('channels')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // owner, moderator, member, readonly.
            $table->string('role')->default('member');

            $table->timestamp('last_read_at')->nullable();

            $table->timestamps();

            $table->unique(['channel_id', 'user_id']);
            $table->index(['user_id', 'role']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('channel_id')
                ->constrained('channels')
                ->cascadeOnDelete();

            $table->foreignId('sender_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->longText('body')->nullable();

            // text, system, file, task, mission_update.
            $table->string('type')->default('text');

            $table->json('attachments')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamp('edited_at')->nullable();

            $table->timestamps();

            $table->index(['channel_id', 'created_at']);
            $table->index('sender_id');
        });

        Schema::create('message_reactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('message_id')
                ->constrained('messages')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('emoji');

            $table->timestamps();

            $table->unique(['message_id', 'user_id', 'emoji']);
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_account_id')
                ->nullable()
                ->constrained('organization_accounts')
                ->cascadeOnDelete();

            $table->foreignId('mission_id')
                ->nullable()
                ->constrained('missions')
                ->cascadeOnDelete();

            $table->foreignId('booking_id')
                ->nullable()
                ->constrained('bookings')
                ->cascadeOnDelete();

            $table->foreignId('channel_id')
                ->nullable()
                ->constrained('channels')
                ->nullOnDelete();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            // todo, in_progress, blocked, done, cancelled.
            $table->string('status')->default('todo');

            // low, normal, high, urgent.
            $table->string('priority')->default('normal');

            $table->date('due_date')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['organization_account_id', 'status']);
            $table->index(['mission_id', 'status']);
            $table->index(['booking_id', 'status']);
            $table->index(['status', 'priority']);
        });

        Schema::create('task_assignees', function (Blueprint $table) {
            $table->id();

            $table->foreignId('task_id')
                ->constrained('tasks')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // assigned, accepted, done.
            $table->string('status')->default('assigned');

            $table->timestamps();

            $table->unique(['task_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_assignees');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('message_reactions');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('channel_members');
        Schema::dropIfExists('channels');
        Schema::dropIfExists('feedbacks');
    }
};
