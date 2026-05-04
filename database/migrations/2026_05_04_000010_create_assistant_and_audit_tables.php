<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_conversations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('organization_account_id')
                ->nullable()
                ->constrained('organization_accounts')
                ->nullOnDelete();

            // client_personal, client_company, provider_independent, provider_company, admin.
            $table->string('context_role')->nullable();

            // open, closed, archived.
            $table->string('status')->default('open');

            $table->json('context_snapshot')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['organization_account_id', 'status']);
        });

        Schema::create('assistant_messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('assistant_conversation_id')
                ->constrained('assistant_conversations')
                ->cascadeOnDelete();

            // user, assistant, system.
            $table->string('sender_type');

            $table->longText('content');

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['assistant_conversation_id', 'created_at']);
        });

        Schema::create('assistant_actions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('assistant_conversation_id')
                ->constrained('assistant_conversations')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // create_booking, assign_mission, invite_member, create_task, explain_invoice...
            $table->string('action_type');

            // pending_confirmation, confirmed, executed, cancelled, failed.
            $table->string('status')->default('pending_confirmation');

            $table->json('payload');

            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('executed_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['action_type', 'status']);
        });

        Schema::create('knowledge_articles', function (Blueprint $table) {
            $table->id();

            $table->string('title');
            $table->string('slug')->unique();

            // all, client_personal, client_company, provider_independent, provider_company, admin.
            $table->string('audience')->default('all');

            $table->longText('content');

            $table->boolean('is_published')->default(false);

            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['audience', 'is_published']);
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('organization_account_id')
                ->nullable()
                ->constrained('organization_accounts')
                ->nullOnDelete();

            $table->string('event');

            $table->nullableMorphs('target');

            // security, booking, mission, finance, quality, system.
            $table->string('domain')->nullable();

            $table->json('data')->nullable();

            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamps();

            $table->index(['event', 'created_at']);
            $table->index(['domain', 'created_at']);
            $table->index(['organization_account_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('knowledge_articles');
        Schema::dropIfExists('assistant_actions');
        Schema::dropIfExists('assistant_messages');
        Schema::dropIfExists('assistant_conversations');
    }
};
