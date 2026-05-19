<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_rules', function (Blueprint $table) {
            $table->id();

            $table->string('code', 64)->unique();
            $table->string('name', 191);
            $table->text('description')->nullable();

            $table->string('severity', 16)->default('medium');  // low | medium | high | critical
            $table->integer('score_delta')->default(10);

            $table->boolean('is_active')->default(true);
            $table->json('params')->nullable();

            $table->timestamps();
        });

        Schema::create('risk_evaluations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->string('subject_type', 64)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            $table->string('context', 32);  // booking_create | payment_attempt | login | signup

            $table->integer('score')->default(0);
            $table->string('decision', 16)->default('allow');  // allow | review | block

            $table->text('reason')->nullable();
            $table->json('triggered_rules')->nullable();

            $table->char('ip_hash', 64)->nullable();
            $table->string('user_agent_short', 191)->nullable();

            $table->string('idempotency_key', 128)->nullable()->unique();

            $table->timestamp('evaluated_at');

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'evaluated_at']);
            $table->index(['decision', 'evaluated_at']);
            $table->index(['context', 'evaluated_at']);
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('risk_holds', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->string('subject_type', 64)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            $table->foreignId('risk_evaluation_id')->nullable()
                ->constrained('risk_evaluations')->nullOnDelete();

            $table->string('status', 32)->default('active');
            //  active | reviewed_approved | reviewed_rejected | expired | auto_released

            $table->string('reason', 191)->nullable();

            $table->timestamp('expires_at')->nullable();

            $table->foreignId('reviewed_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['status', 'expires_at']);
            $table->index(['user_id', 'status']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_holds');
        Schema::dropIfExists('risk_evaluations');
        Schema::dropIfExists('risk_rules');
    }
};
