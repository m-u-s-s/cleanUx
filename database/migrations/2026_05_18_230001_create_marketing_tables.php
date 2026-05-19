<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_segments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 191);
            $table->text('description')->nullable();
            $table->json('rules');  // DSL tree
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('member_count')->default(0);
            $table->timestamp('last_computed_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('marketing_segment_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('segment_id')
                ->constrained('marketing_segments')->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')->cascadeOnDelete();
            $table->timestamp('computed_at');
            $table->decimal('score', 8, 4)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['segment_id', 'user_id']);
            $table->index(['segment_id', 'computed_at']);
        });

        Schema::create('marketing_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 191);
            $table->text('description')->nullable();
            $table->string('type', 32);  // single_blast | drip_sequence | triggered
            $table->string('status', 32)->default('draft');  // draft|scheduled|running|paused|completed|cancelled
            $table->foreignId('segment_id')->nullable()
                ->constrained('marketing_segments')->nullOnDelete();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->json('ab_test_config')->nullable();
            $table->boolean('opt_in_required')->default(false);
            $table->string('locale', 8)->nullable();
            $table->foreignId('created_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
            $table->index(['type', 'status']);
        });

        Schema::create('marketing_campaign_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')
                ->constrained('marketing_campaigns')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->unsignedInteger('delay_minutes')->default(0);  // after previous step or campaign start
            $table->string('channel', 16);  // email | sms | push
            $table->string('subject', 191)->nullable();
            $table->string('template_code', 128)->nullable();
            $table->string('variant_label', 32)->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('content_overrides')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'position']);
        });

        Schema::create('marketing_campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')
                ->constrained('marketing_campaigns')->cascadeOnDelete();
            $table->foreignId('step_id')->nullable()
                ->constrained('marketing_campaign_steps')->nullOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')->cascadeOnDelete();
            $table->string('channel', 16);
            $table->string('status', 32)->default('queued');
            // queued | sent | delivered | opened | clicked | failed | opted_out | skipped
            $table->string('idempotency_key', 191)->nullable()->unique();
            $table->timestamp('scheduled_for');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failed_reason')->nullable();
            $table->string('variant_label', 32)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'status']);
            $table->index(['user_id', 'campaign_id']);
            $table->index(['status', 'scheduled_for']);
        });

        Schema::create('marketing_opt_outs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')->cascadeOnDelete();
            $table->string('channel', 16);  // email | sms | push | all
            $table->timestamp('opted_out_at');
            $table->string('reason', 191)->nullable();
            $table->char('ip_hash', 64)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'channel']);
            $table->index('channel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_opt_outs');
        Schema::dropIfExists('marketing_campaign_recipients');
        Schema::dropIfExists('marketing_campaign_steps');
        Schema::dropIfExists('marketing_campaigns');
        Schema::dropIfExists('marketing_segment_members');
        Schema::dropIfExists('marketing_segments');
    }
};
