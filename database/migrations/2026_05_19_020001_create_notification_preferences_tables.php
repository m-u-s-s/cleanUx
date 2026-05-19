<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')->cascadeOnDelete();

            $table->string('channel', 16);     // email | sms | push | inapp | webhook
            $table->string('category', 24);    // transactional | verification | reminder | marketing | support | security | product

            $table->boolean('is_allowed');

            $table->unsignedInteger('version')->default(1);

            $table->string('source', 16)->default('default');
            // default | user | admin | webhook | system

            $table->char('updated_via_ip_hash', 64)->nullable();
            $table->timestamp('last_changed_at')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'channel', 'category'], 'notif_prefs_user_channel_cat_unique');
            $table->index(['channel', 'category']);
            $table->index(['user_id', 'is_allowed']);
        });

        Schema::create('notification_preference_audits', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')->cascadeOnDelete();

            $table->string('channel', 16);
            $table->string('category', 24);

            $table->boolean('old_value')->nullable();
            $table->boolean('new_value');

            $table->unsignedInteger('version_from')->nullable();
            $table->unsignedInteger('version_to');

            $table->string('source', 16);
            $table->foreignId('actor_user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->char('ip_hash', 64)->nullable();
            $table->string('user_agent_short', 191)->nullable();

            $table->timestamp('changed_at');
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'changed_at']);
            $table->index(['channel', 'category', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preference_audits');
        Schema::dropIfExists('notification_preferences');
    }
};
