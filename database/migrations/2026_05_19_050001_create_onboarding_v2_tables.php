<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_journeys', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 191);
            $table->text('description')->nullable();
            $table->string('role', 24);              // client | provider | enterprise
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->json('applies_to_country')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['role', 'is_active']);
        });

        Schema::create('onboarding_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journey_id')
                ->constrained('onboarding_journeys')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('code', 64);
            $table->string('label', 191);
            $table->text('description')->nullable();
            $table->string('step_type', 32);
            // form | kyc_check | insurance_purchase | payouts_setup
            // | contract_sign | profile_complete | skill_declare | document_upload
            $table->boolean('required')->default(true);
            $table->string('validator_class', 191)->nullable();
            $table->json('depends_on')->nullable();   // list of step.code strings
            $table->boolean('is_skippable')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['journey_id', 'code'], 'onboarding_steps_journey_code_unique');
            $table->index(['journey_id', 'position']);
        });

        Schema::create('onboarding_journeys_user_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')->cascadeOnDelete();
            $table->foreignId('journey_id')
                ->constrained('onboarding_journeys')->cascadeOnDelete();
            $table->string('status', 24)->default('not_started');
            // not_started | in_progress | completed | abandoned
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('abandoned_at')->nullable();
            $table->string('current_step_code', 64)->nullable();
            $table->decimal('percent_complete', 5, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'journey_id'], 'onboarding_progress_user_journey_unique');
            $table->index(['status', 'updated_at']);
        });

        Schema::create('onboarding_step_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('progress_id')
                ->constrained('onboarding_journeys_user_progress')->cascadeOnDelete();
            $table->foreignId('step_id')
                ->constrained('onboarding_steps')->cascadeOnDelete();
            $table->string('status', 24)->default('pending');
            // pending | in_progress | completed | skipped | failed
            $table->json('data')->nullable();
            $table->json('validator_payload')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['progress_id', 'step_id'], 'onboarding_step_completions_unique');
            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_step_completions');
        Schema::dropIfExists('onboarding_journeys_user_progress');
        Schema::dropIfExists('onboarding_steps');
        Schema::dropIfExists('onboarding_journeys');
    }
};
